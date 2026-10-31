<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Mark or update attendance for a class. The unique database index and
     * updateOrCreate both guarantee one row per student per session per day.
     *
     * @param  array<int, string>  $statuses  student_id => status
     */
    public function mark(
        AcademicSession $session,
        SchoolClass $class,
        Section $section,
        string $date,
        array $statuses,
        User $actor,
        array $notes = []
    ): int {
        $allowed = ['present', 'absent', 'late', 'excused'];

        foreach ($statuses as $status) {
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['attendance' => 'Invalid attendance status submitted.']);
            }
        }

        $studentIds = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('class_id', $class->id)
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->all();

        if (empty($studentIds)) {
            throw ValidationException::withMessages(['attendance' => 'No active students found for this class and section.']);
        }

        $saved = 0;

        DB::transaction(function () use ($studentIds, $statuses, $session, $class, $section, $date, $actor, $notes, &$saved) {
            foreach ($studentIds as $studentId) {
                $status = $statuses[$studentId] ?? null;

                if (! $status) {
                    continue;
                }

                AttendanceRecord::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'academic_session_id' => $session->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'class_id' => $class->id,
                        'section_id' => $section->id,
                        'status' => $status,
                        'note' => $notes[$studentId] ?? null,
                        'marked_by' => $actor->id,
                    ]
                );

                $saved++;
            }

            $this->audit->record(
                'attendance_marked',
                'Attendance',
                "Marked attendance for {$class->name} - {$section->name} on {$date}",
                null,
                null,
                ['date' => $date, 'records' => $saved]
            );
        });

        return $saved;
    }
}
