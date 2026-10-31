<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('academics.attendance.report', $this->data($request));
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
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $classId = $request->filled('class_id') ? $request->integer('class_id') : null;
        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;

        $query = AttendanceRecord::query()
            ->with(['student', 'class', 'section'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('attendance_date', [$from, $to])
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId));
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->whereHas('student', fn ($q) => $q
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('admission_no', 'like', "%{$term}%"));
        }

        $records = $query->get();

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
                    'admission_no' => $first->student?->admission_no,
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

        $byClass = DB::table('attendance_records')
            ->join('classes', 'classes.id', '=', 'attendance_records.class_id')
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('attendance_date', [$from, $to])
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->selectRaw('classes.name as class, attendance_records.status, COUNT(*) as total')
            ->groupBy('classes.name', 'attendance_records.status')
            ->get()
            ->groupBy('class')
            ->map(fn ($rows) => [
                'class' => $rows->first()->class,
                'present' => (int) $rows->where('status', 'present')->sum('total'),
                'absent' => (int) $rows->where('status', 'absent')->sum('total'),
                'late' => (int) $rows->where('status', 'late')->sum('total'),
                'excused' => (int) $rows->where('status', 'excused')->sum('total'),
            ])
            ->values();

        return [
            'session' => $session,
            'from' => $from,
            'to' => $to,
            'classId' => $classId,
            'sectionId' => $sectionId,
            'rows' => $rows,
            'byClass' => $byClass,
            'classes' => SchoolClass::ordered()->get(),
            'sections' => Section::orderBy('name')->get(),
            'summary' => [
                'students' => $rows->count(),
                'records' => $records->count(),
                'present' => $records->whereIn('status', ['present', 'late'])->count(),
                'absent' => $records->where('status', 'absent')->count(),
            ],
        ];
    }
}
