<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $fromSession = $request->filled('from_session_id')
            ? $sessions->firstWhere('id', $request->integer('from_session_id'))
            : current_session();
        $fromClass = $request->filled('from_class_id')
            ? SchoolClass::find($request->integer('from_class_id'))
            : null;

        $preview = collect();

        if ($fromSession && $fromClass) {
            $preview = Enrollment::query()
                ->with(['student', 'section'])
                ->where('academic_session_id', $fromSession->id)
                ->where('class_id', $fromClass->id)
                ->where('status', 'active')
                ->orderBy('section_id')
                ->orderBy('roll_no')
                ->get();
        }

        return view('students.promotion', [
            'sessions' => $sessions,
            'classes' => SchoolClass::ordered()->get(),
            'sections' => Section::orderBy('name')->get(),
            'fromSession' => $fromSession,
            'fromClass' => $fromClass,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_session_id' => ['required', 'exists:academic_sessions,id'],
            'to_session_id' => ['required', 'different:from_session_id', 'exists:academic_sessions,id'],
            'from_class_id' => ['required', 'exists:classes,id'],
            'to_class_id' => ['required', 'exists:classes,id'],
            'to_section_id' => ['required', 'exists:sections,id'],
            'only_student_ids' => ['nullable', 'array'],
            'only_student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $fromSession = AcademicSession::findOrFail($data['from_session_id']);
        $toSession = AcademicSession::findOrFail($data['to_session_id']);
        $fromClass = SchoolClass::findOrFail($data['from_class_id']);
        $toClass = SchoolClass::findOrFail($data['to_class_id']);
        $section = Section::findOrFail($data['to_section_id']);

        if (! $toClass->sections()->whereKey($section->id)->exists()) {
            throw ValidationException::withMessages(['to_section_id' => 'The section is not available for the destination class.']);
        }

        if ($toSession->start_date->lt($fromSession->start_date)) {
            throw ValidationException::withMessages(['to_session_id' => 'The destination session cannot start before the source session.']);
        }

        $promoted = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $fromSession, $toSession, $fromClass, $toClass, $section, &$promoted, &$skipped) {
            $enrollments = Enrollment::query()
                ->where('academic_session_id', $fromSession->id)
                ->where('class_id', $fromClass->id)
                ->where('status', 'active')
                ->when(! empty($data['only_student_ids']), fn ($query) => $query->whereIn('student_id', $data['only_student_ids']))
                ->lockForUpdate()
                ->get();

            $nextRoll = (int) Enrollment::query()
                ->where('academic_session_id', $toSession->id)
                ->where('class_id', $toClass->id)
                ->where('section_id', $section->id)
                ->max('roll_no');

            foreach ($enrollments as $enrollment) {
                $exists = Enrollment::query()
                    ->where('student_id', $enrollment->student_id)
                    ->where('academic_session_id', $toSession->id)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $nextRoll++;

                $new = Enrollment::create([
                    'student_id' => $enrollment->student_id,
                    'academic_session_id' => $toSession->id,
                    'class_id' => $toClass->id,
                    'section_id' => $section->id,
                    'roll_no' => $nextRoll,
                    'status' => 'active',
                    'promoted_from_enrollment_id' => $enrollment->id,
                ]);

                $enrollment->update(['status' => 'promoted', 'remarks' => 'Promoted to '.$toClass->name.' for '.$toSession->name]);
                $enrollment->student->update(['status' => 'active']);
                $promoted++;

                $this->audit->record(
                    'student_promoted',
                    'Students',
                    "Promoted {$enrollment->student?->full_name} from {$fromClass->name} to {$toClass->name} ({$toSession->name})",
                    $new,
                    ['enrollment_id' => $enrollment->id, 'status' => 'active'],
                    ['enrollment_id' => $new->id, 'status' => 'active']
                );
            }

            $this->audit->record(
                'promotion_run',
                'Students',
                "Promotion run {$fromClass->name} ({$fromSession->name}) to {$toClass->name} ({$toSession->name}): {$promoted} promoted, {$skipped} skipped",
                null,
                null,
                ['promoted' => $promoted, 'skipped' => $skipped]
            );
        });

        return redirect()->route('promotion.index', [
            'from_session_id' => $fromSession->id,
            'from_class_id' => $fromClass->id,
        ])->with('success', "Promotion complete: {$promoted} student(s) promoted, {$skipped} skipped because they were already enrolled.");
    }
}
