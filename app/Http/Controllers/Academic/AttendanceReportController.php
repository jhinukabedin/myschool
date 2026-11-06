<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AttendanceAuthorizationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function __construct(private readonly AttendanceAuthorizationService $authorization) {}

    public function index(Request $request): View
    {
        return view('academics.attendance.report', $this->data($request));
    }

    public function print(Request $request): View
    {
        $data = $this->data($request);
        $type = $request->validate(['type' => ['required', 'in:class,monthly,student']])['type'];

        return view('academics.attendance.print', [...$data, 'printType' => $type]);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['EduSphere School - Attendance Report']);
            fputcsv($out, ['Session', $data['session']?->name]);
            fputcsv($out, ['From', $data['from']]);
            fputcsv($out, ['To', $data['to']]);
            fputcsv($out, []);
            fputcsv($out, ['Student', 'Admission No', 'Class', 'Section', 'Present', 'Absent', 'Late', 'Excused', 'Attendance %']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['student'], $row['admission_no'], $row['class'], $row['section'],
                    $row['present'], $row['absent'], $row['late'], $row['excused'], $row['percentage'],
                ]);
            }
            fclose($out);
        }, 'attendance-report-'.$data['from'].'-to-'.$data['to'].'.csv', ['Content-Type' => 'text/csv']);
    }

    private function data(Request $request): array
    {
        $session = current_session();
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'month' => ['nullable', 'date_format:Y-m'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'show' => ['nullable', 'boolean'],
        ]);
        $month = $filters['month'] ?? null;
        $from = $month
            ? Carbon::createFromFormat('!Y-m', $month)->startOfMonth()->toDateString()
            : ($filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = $month
            ? Carbon::createFromFormat('!Y-m', $month)->endOfMonth()->toDateString()
            : ($filters['to'] ?? now()->toDateString());
        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;
        $sectionId = isset($filters['section_id']) ? (int) $filters['section_id'] : null;
        $studentId = null;
        $user = $request->user();
        $classSectionMap = $this->authorization->classSectionMap($user, $session, $from, $to);

        if ($classId && ! $this->authorization->isAdministrator($user) && ! $classSectionMap->has($classId)) {
            abort(403, 'You are not assigned to this class.');
        }
        if ($classId && $sectionId && ! SchoolClass::findOrFail($classId)->sections()->whereKey($sectionId)->exists()) {
            abort(422, 'The selected section does not belong to this class.');
        }

        $query = AttendanceRecord::query()
            ->with(['student', 'class', 'section'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('attendance_date', [$from, $to])
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId));
        $this->authorization->constrainQuery($query, $user, $session, $from, $to);
        $records = $query->get();

        $enrollments = Enrollment::query()
            ->with('student')
            ->where('academic_session_id', $session?->id)
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId));
        if (! $this->authorization->isAdministrator($user)) {
            if ($classSectionMap->isEmpty()) {
                $enrollments->whereRaw('1 = 0');
            } else {
                $enrollments->where(function ($scope) use ($classSectionMap) {
                    foreach ($classSectionMap as $assignedClassId => $sectionIds) {
                        $scope->orWhere(fn ($pair) => $pair->where('class_id', $assignedClassId)->whereIn('section_id', $sectionIds));
                    }
                });
            }
        }
        $enrollmentRows = $enrollments->get();
        $studentOptions = $enrollmentRows->pluck('student')
            ->filter()
            ->merge($records->pluck('student')->filter())
            ->unique('id')
            ->sortBy('full_name')
            ->values();

        $studentSearch = trim((string) $request->input('q', ''));
        $searchMessage = null;
        $searchAmbiguous = false;
        if ($studentSearch !== '') {
            $normalize = fn ($value) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
            $normalizeRoll = fn ($value) => ctype_digit((string) $value)
                ? (ltrim((string) $value, '0') ?: '0')
                : $normalize($value);
            $normalizedTerm = $normalize($studentSearch);
            $normalizedRollTerm = $normalizeRoll($studentSearch);
            $exactMatches = $enrollmentRows->filter(fn (Enrollment $enrollment) =>
                $normalizeRoll($enrollment->roll_no) === $normalizedRollTerm
                || $normalize($enrollment->student?->student_code) === $normalizedTerm
                || $normalize($enrollment->student?->full_name) === $normalizedTerm
            );
            $matches = $exactMatches->isNotEmpty()
                ? $exactMatches
                : $enrollmentRows->filter(fn (Enrollment $enrollment) =>
                    str_contains($normalizeRoll($enrollment->roll_no), $normalizedRollTerm)
                    || str_contains($normalize($enrollment->student?->student_code), $normalizedTerm)
                    || str_contains($normalize($enrollment->student?->full_name), $normalizedTerm)
                );
            $matchedStudentIds = $matches->pluck('student_id')->unique()->values();

            if ($matchedStudentIds->count() === 1) {
                $studentId = (int) $matchedStudentIds->first();
                $records = $records->where('student_id', $studentId)->values();
            } elseif ($matchedStudentIds->count() > 1) {
                $searchAmbiguous = true;
                $searchMessage = 'More than one student matches. Enter the full name or roll number to show one student only.';
                $records = collect();
            } else {
                $searchMessage = 'No student matches that name, roll number or Student ID.';
                $records = collect();
            }
        }

        $rows = $records
            ->groupBy('student_id')
            ->map(function ($studentRecords) {
                $first = $studentRecords->first();
                $present = $studentRecords->where('status', 'present')->count();
                $absent = $studentRecords->where('status', 'absent')->count();
                $late = $studentRecords->where('status', 'late')->count();
                $excused = $studentRecords->where('status', 'excused')->count();
                $total = $studentRecords->count();
                $attended = $present + $late;

                return [
                    'student' => $first->student?->full_name ?? 'Unknown',
                    'student_id' => $first->student?->student_code,
                    'admission_no' => $first->student?->student_code,
                    'class' => $first->class?->name,
                    'section' => $first->section?->name,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $excused,
                    'total' => $total,
                    'percentage' => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
                ];
            })
            ->sortBy('student')
            ->values();

        $summarize = function (Collection $set): array {
            $present = $set->where('status', 'present')->count();
            $late = $set->where('status', 'late')->count();
            $absent = $set->where('status', 'absent')->count();
            $excused = $set->where('status', 'excused')->count();
            $total = $set->count();

            return [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'total' => $total,
                'percentage' => $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0,
            ];
        };

        $classRows = $records->groupBy(fn (AttendanceRecord $record) => $record->class_id.'|'.$record->section_id)
            ->map(function (Collection $set) use ($summarize) {
                $first = $set->first();

                return [
                    'class' => $first->class?->name ?? 'Unknown',
                    'section' => $first->section?->name ?? 'Unknown',
                    'students' => $set->pluck('student_id')->unique()->count(),
                    ...$summarize($set),
                ];
            })
            ->sortBy(fn ($row) => $row['class'].' '.$row['section'])
            ->values();

        $monthlyRows = $records->groupBy(fn (AttendanceRecord $record) => $record->attendance_date->format('Y-m'))
            ->map(fn (Collection $set, string $key) => [
                'key' => $key,
                'month' => Carbon::createFromFormat('!Y-m', $key)->format('F Y'),
                ...$summarize($set),
            ])
            ->sortBy('key')
            ->values();

        $enrollmentByClassSectionStudent = $enrollmentRows->keyBy(fn (Enrollment $enrollment) =>
            $enrollment->class_id.'|'.$enrollment->section_id.'|'.$enrollment->student_id
        );
        $studentDailyRows = $records->sortBy(fn (AttendanceRecord $record) => ($record->student?->full_name ?? '').$record->attendance_date->toDateString())
            ->map(fn (AttendanceRecord $record) => [
                'date' => $record->attendance_date,
                'student' => $record->student?->full_name ?? 'Unknown',
                'roll_no' => $enrollmentByClassSectionStudent->get($record->class_id.'|'.$record->section_id.'|'.$record->student_id)?->roll_no,
                'student_id' => $record->student?->student_code,
                'admission_no' => $record->student?->student_code,
                'class' => $record->class?->name,
                'section' => $record->section?->name,
                'status' => $record->status,
                'note' => $record->note,
            ])
            ->values();

        $matrixDates = CarbonPeriod::create($from, $to)->toArray();
        $matrixDays = collect($matrixDates)->map(fn ($date) => $date->toDateString())->all();
        $recordMap = $records->keyBy(fn (AttendanceRecord $record) =>
            $record->student_id.'|'.$record->class_id.'|'.$record->section_id.'|'.$record->attendance_date->toDateString()
        );
        $infoMatrixRows = $enrollmentRows
            ->when($classId, fn (Collection $rows) => $rows->where('class_id', $classId))
            ->when($sectionId, fn (Collection $rows) => $rows->where('section_id', $sectionId))
            ->sortBy(fn (Enrollment $enrollment) => str_pad((string) $enrollment->roll_no, 10, '0', STR_PAD_LEFT).'|'.($enrollment->student?->full_name ?? ''))
            ->map(function (Enrollment $enrollment) use ($matrixDays, $recordMap) {
                $keyPrefix = $enrollment->student_id.'|'.$enrollment->class_id.'|'.$enrollment->section_id.'|';
                $statuses = collect($matrixDays)->mapWithKeys(fn (string $date) => [
                    $date => $recordMap->get($keyPrefix.$date)?->status,
                ]);

                return [
                    'class_id' => $enrollment->class_id,
                    'student' => $enrollment->student?->full_name ?? 'Unknown',
                    'student_id' => $enrollment->student?->student_code,
                    'admission_no' => $enrollment->student?->student_code,
                    'roll_no' => $enrollment->roll_no,
                    'section' => $enrollment->section?->name,
                    'statuses' => $statuses,
                    'present' => $statuses->filter(fn ($status) => $status === 'present')->count(),
                    'absent' => $statuses->filter(fn ($status) => $status === 'absent')->count(),
                    'late' => $statuses->filter(fn ($status) => $status === 'late')->count(),
                    'excused' => $statuses->filter(fn ($status) => $status === 'excused')->count(),
                ];
            })
            ->values();

        $byClass = $records->groupBy(fn (AttendanceRecord $record) => $record->class?->name ?? 'Unknown')
            ->map(fn (Collection $set, string $className) => [
                'class' => $className,
                'present' => $set->where('status', 'present')->count(),
                'absent' => $set->where('status', 'absent')->count(),
                'late' => $set->where('status', 'late')->count(),
                'excused' => $set->where('status', 'excused')->count(),
            ])
            ->values();

        $classes = SchoolClass::with('sections')->ordered()->get();
        $sectionIds = $classId
            ? ($this->authorization->isAdministrator($user)
                ? (SchoolClass::with('sections')->find($classId)?->sections->modelKeys() ?? [])
                : ($classSectionMap->get($classId, []) ?? []))
            : $classSectionMap->flatten()->unique()->values()->all();

        if (! $this->authorization->isAdministrator($user)) {
            $classes = $classes->whereIn('id', $classSectionMap->keys())->values();
            $classes->each(fn (SchoolClass $class) => $class->setRelation(
                'sections',
                $class->sections->whereIn('id', $classSectionMap->get($class->id, []))->values()
            ));
        } elseif (! $classId) {
            $sectionIds = Section::query()->pluck('id')->all();
        }

        $reportSections = $classId
            ? ($classes->firstWhere('id', $classId)?->sections ?? collect())
            : $classes->flatMap(fn (SchoolClass $option) => $option->sections)->unique('id')->values();
        $sectionOptions = $reportSections->map(function (Section $section) use ($classes) {
            $section->setAttribute('class_ids', $classes
                ->filter(fn (SchoolClass $class) => $class->sections->contains('id', $section->id))
                ->modelKeys());

            return $section;
        });

        return [
            'session' => $session,
            'from' => $from,
            'to' => $to,
            'month' => $month,
            'showResults' => $request->boolean('show'),
            'matrixDays' => $matrixDays,
            'infoMatrixRows' => $infoMatrixRows,
            'classId' => $classId,
            'sectionId' => $sectionId,
            'studentId' => $studentId,
            'studentSearch' => $studentSearch,
            'searchMessage' => $searchMessage,
            'searchAmbiguous' => $searchAmbiguous,
            'rows' => $rows,
            'byClass' => $byClass,
            'classRows' => $classRows,
            'monthlyRows' => $monthlyRows,
            'studentDailyRows' => $studentDailyRows,
            'studentOptions' => $studentOptions,
            'classes' => $classes,
            'sections' => Section::query()->whereIn('id', $sectionIds)->ordered()->get(),
            'sectionOptions' => $sectionOptions,
            'summary' => [
                'students' => $rows->count(),
                'records' => $records->count(),
                'present' => $records->whereIn('status', ['present', 'late'])->count(),
                'absent' => $records->where('status', 'absent')->count(),
            ],
        ];
    }
}
