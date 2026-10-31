<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\ResultSnapshot;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Models\WebsiteContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared read model for the student and parent portals.
 *
 * Ownership is always checked here using the authenticated account's explicit
 * links. Controllers never trust a student id supplied in a URL or form.
 */
class PortalService
{
    public function __construct(private readonly ExamResultService $results) {}

    public function studentFor(User $user): Student
    {
        $link = $user->studentPortalLink()->active()->with('student')->first();
        abort_unless($link?->student, 403, 'No student record is linked to this account.');

        return $link->student;
    }

    /**
     * @return Collection<int, Student>
     */
    public function childrenFor(User $user): Collection
    {
        return $user->linkedChildren();
    }

    public function authorizeChild(User $user, Student $student): Student
    {
        $linked = $user->parentStudentLinks()
            ->active()
            ->where('student_id', $student->id)
            ->exists();

        abort_unless($linked, 403, 'This student is not linked to your guardian account.');

        return $student;
    }

    public function selectedChild(User $user, ?int $requestedId = null): ?Student
    {
        $children = $this->childrenFor($user);

        if ($children->isEmpty()) {
            return null;
        }

        if ($requestedId) {
            $student = $children->firstWhere('id', $requestedId);
            abort_unless($student, 403, 'This student is not linked to your guardian account.');

            return $student;
        }

        return $children->first();
    }

    public function enrollment(Student $student, ?AcademicSession $session = null): ?Enrollment
    {
        return Enrollment::query()
            ->with(['class', 'section', 'academicSession'])
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->orderByDesc('academic_session_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The structured routine for the student's enrolled class and section.
     * Only a routine matching all three identifiers is returned.
     */
    public function classRoutine(Student $student, ?AcademicSession $session): ?ClassRoutine
    {
        $enrollment = $this->enrollment($student, $session);

        if (! $session || ! $enrollment) {
            return null;
        }

        return ClassRoutine::query()
            ->with([
                'academicSession',
                'class',
                'section',
                'entries.subject',
                'entries.teacher',
                'entries.timeSlot',
            ])
            ->forContext($session->id, $enrollment->class_id, $enrollment->section_id)
            ->first();
    }

    /**
     * @return array{records:int,present:int,absent:int,late:int,excused:int,percentage:float,recent:Collection,monthly:Collection}
     */
    public function attendance(Student $student, ?AcademicSession $session): array
    {
        $query = AttendanceRecord::query()
            ->with('class')
            ->where('student_id', $student->id)
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id));

        $records = (clone $query)->get();
        $total = $records->count();
        $attended = $records->whereIn('status', ['present', 'late'])->count();

        $monthly = $records
            ->filter(fn ($record) => $record->attendance_date !== null)
            ->groupBy(fn ($record) => $record->attendance_date->format('Y-m'))
            ->map(function (Collection $rows, string $month) {
                $total = $rows->count();
                $attended = $rows->whereIn('status', ['present', 'late'])->count();

                return [
                    'month' => $month,
                    'label' => $rows->first()->attendance_date->format('F Y'),
                    'total' => $total,
                    'present' => $rows->where('status', 'present')->count(),
                    'absent' => $rows->where('status', 'absent')->count(),
                    'late' => $rows->where('status', 'late')->count(),
                    'excused' => $rows->where('status', 'excused')->count(),
                    'percentage' => $total > 0 ? round(($attended / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortKeysDesc()
            ->values();

        return [
            'records' => $total,
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'percentage' => $total > 0 ? round(($attended / $total) * 100, 1) : 0.0,
            'recent' => (clone $query)->latest('attendance_date')->limit(10)->get(),
            'monthly' => $monthly,
        ];
    }

    /**
     * @return array{assignments:Collection,total_charged:float,total_paid:float,total_discount:float,balance:float,overdue:float,payments:Collection}
     */
    public function fees(Student $student, ?AcademicSession $session): array
    {
        $assignments = StudentFeeAssignment::query()
            ->with('category')
            ->where('student_id', $student->id)
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->orderBy('due_date')
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get();

        $payments = Payment::query()
            ->with(['allocations.assignment.category', 'reversals.reversedBy'])
            ->where('student_id', $student->id)
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->latest('paid_at')
            ->get();

        $charged = round($assignments->sum(fn ($row) => (float) $row->net_amount), 2);
        $paid = round($assignments->sum(fn ($row) => (float) $row->paid_amount), 2);
        $discount = round($assignments->sum(fn ($row) => (float) $row->discount_amount + (float) $row->waiver_amount), 2);
        $balance = round($assignments->sum(fn ($row) => (float) $row->balance), 2);
        $overdue = round($assignments
            ->filter(fn ($row) => $row->balance > 0 && $row->due_date && $row->due_date->isPast())
            ->sum(fn ($row) => (float) $row->balance), 2);

        return [
            'assignments' => $assignments,
            'total_charged' => $charged,
            'total_paid' => $paid,
            'total_discount' => $discount,
            'balance' => $balance,
            'overdue' => $overdue,
            'payments' => $payments,
        ];
    }

    /**
     * Only examinations explicitly published with a result publication time are
     * returned. Draft marks and unpublished exams never reach a portal.
     *
     * @return Collection<int, Examination>
     */
    public function publishedExams(Student $student, ?AcademicSession $session): Collection
    {
        $enrollment = $this->enrollment($student, $session);
        if (! $enrollment) {
            return collect();
        }

        return Examination::query()
            ->with('academicSession')
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('status', 'published')
            ->whereNotNull('result_published_at')
            ->whereHas('subjects', fn ($query) => $query->where('class_id', $enrollment->class_id))
            ->orderByDesc('result_published_at')
            ->get();
    }

    /**
     * Build a result from marks, using the grading rules stored when the result
     * was published. Administrators may correct a published result, but grading
     * thresholds remain those of the original scheme.
     *
     * @return array<string, mixed>|null
     */
    public function result(Examination $exam, Student $student): ?array
    {
        $enrollment = Enrollment::query()
            ->with(['class', 'section', 'academicSession'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $exam->academic_session_id)
            ->first();

        if (! $enrollment) {
            return null;
        }

        $exam->loadMissing(['subjects.subject', 'gradingScheme']);
        $snapshot = ResultSnapshot::query()
            ->where('examination_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        // A snapshot is the proof that the result was deliberately published
        // for this student. Never fall back to live marks in a portal.
        if (! $snapshot) {
            return null;
        }

        $result = $this->results->resultFromSnapshot($snapshot);
        $result['student'] = $student;

        return [
            'exam' => $exam,
            'student' => $student,
            'enrollment' => $enrollment,
            'result' => $result,
            'snapshot' => $snapshot,
            'published_at' => $snapshot->published_at ?? $exam->result_published_at,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publishedResults(Student $student, ?AcademicSession $session): Collection
    {
        return $this->publishedExams($student, $session)
            ->map(fn (Examination $exam) => $this->result($exam, $student))
            ->filter()
            ->values();
    }

    public function examSchedule(Student $student, ?AcademicSession $session): Collection
    {
        $enrollment = $this->enrollment($student, $session);
        if (! $enrollment) {
            return collect();
        }

        return ExaminationSubject::query()
            ->with(['examination', 'subject'])
            ->where('class_id', $enrollment->class_id)
            ->whereHas('examination', fn ($query) => $query
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->whereIn('status', ['published', 'completed']))
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Published notices visible to a student: everyone, all students and the
     * student's own class. Draft, expired and other-class notices are never
     * returned.
     */
    public function noticeQuery(Student $student, ?AcademicSession $session): Builder
    {
        $enrollment = $this->enrollment($student, $session);

        return Notice::query()
            ->published()
            ->where(function ($query) use ($enrollment) {
                $query->whereIn('audience', ['all', 'students'])
                    ->when($enrollment, fn ($q) => $q->orWhere(fn ($classQuery) => $classQuery
                        ->where('audience', 'classes')
                        ->whereHas('classes', fn ($classes) => $classes->where('classes.id', $enrollment->class_id))));
            });
    }

    public function notices(Student $student, ?AcademicSession $session, int $limit = 10): Collection
    {
        return $this->noticeQuery($student, $session)
            ->latest('publish_at')
            ->limit($limit)
            ->get();
    }

    public function noticeForStudent(Student $student, ?AcademicSession $session, Notice $notice): Notice
    {
        abort_unless(
            $this->noticeQuery($student, $session)->whereKey($notice->id)->exists(),
            404
        );

        return $notice;
    }

    /**
     * CMS-backed content for the student's class. Records without a category
     * are school-wide; a category must match the student's class name.
     *
     * @return Collection<int, WebsiteContent>
     */
    public function classContents(string $type, Student $student, ?AcademicSession $session): Collection
    {
        $enrollment = $this->enrollment($student, $session);
        $className = $enrollment?->class?->name;

        return WebsiteContent::query()
            ->ofType($type)
            ->publiclyVisible()
            ->when($className, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('category')
                ->orWhere('category', '')
                ->orWhere('category', $className)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Syllabus files and general downloads available to the student's class.
     *
     * @return Collection<int, WebsiteContent>
     */
    public function studyMaterials(Student $student, ?AcademicSession $session): Collection
    {
        $enrollment = $this->enrollment($student, $session);
        $className = $enrollment?->class?->name;

        return WebsiteContent::query()
            ->whereIn('type', ['syllabus', 'download'])
            ->publiclyVisible()
            ->when($className, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('category')
                ->orWhere('category', '')
                ->orWhere('category', $className)
                ->orWhere('category', 'like', $className.'%')))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    /**
     * @return Collection<int, WebsiteContent>
     */
    public function calendarItems(): Collection
    {
        return WebsiteContent::query()
            ->ofType('academic_calendar')
            ->publiclyVisible()
            ->orderBy('sort_order')
            ->orderBy('published_at')
            ->get();
    }

    public function downloads(): Collection
    {
        return WebsiteContent::query()
            ->ofType('download')
            ->publiclyVisible()
            ->orderBy('sort_order')
            ->limit(12)
            ->get();
    }

    public function paymentForStudent(Student $student, Payment $payment): Payment
    {
        abort_unless($payment->student_id === $student->id, 404);

        return $payment->load([
            'student.guardians',
            'academicSession',
            'allocations.assignment.category',
            'receivedBy',
            'reversals.reversedBy',
        ]);
    }
}
