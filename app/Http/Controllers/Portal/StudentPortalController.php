<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Student;
use App\Services\PortalService;
use App\Services\RoutineScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function __construct(
        private readonly PortalService $portal,
        private readonly RoutineScheduleService $schedule
    ) {}

    public function dashboard(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();
        $enrollment = $this->portal->enrollment($student, $session);
        $fees = $this->portal->fees($student, $session);

        return view('portal.student.dashboard', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $enrollment,
            'attendance' => $this->portal->attendance($student, $session),
            'results' => $this->portal->publishedResults($student, $session)->take(3),
            'fees' => $fees,
            'exams' => $this->portal->examSchedule($student, $session)->take(5),
            'notices' => $this->portal->notices($student, $session)->take(5),
            'downloads' => $this->portal->downloads()->take(4),
            'assignments' => $this->portal->classContents('assignment', $student, $session)
                ->sortBy(fn ($item) => $item->due_date?->timestamp ?? PHP_INT_MAX)
                ->take(4)
                ->values(),
        ]);
    }

    public function profile(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());

        return view('portal.student.profile', [
            'student' => $student->load(['guardians', 'enrollments.class', 'enrollments.section', 'enrollments.academicSession']),
            'session' => current_session(),
            'enrollment' => $this->portal->enrollment($student, current_session()),
        ]);
    }

    public function results(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        return view('portal.results.index', [
            'student' => $student,
            'session' => $session,
            'results' => $this->portal->publishedResults($student, $session),
            'portalPrefix' => 'student',
            'enrollment' => $this->portal->enrollment($student, $session),
        ]);
    }

    public function resultSheet(Request $request, Examination $exam): View
    {
        $student = $this->portal->studentFor($request->user());
        $payload = $this->publishedResult($exam, $student->id, $student);

        return view('portal.results.sheet', $payload + [
            'backUrl' => route('student.results'),
            'portalRole' => 'Student',
        ]);
    }

    public function fees(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        return view('portal.fees.index', [
            'student' => $student,
            'session' => $session,
            'fees' => $this->portal->fees($student, $session),
            'portalRole' => 'student',
            'receiptRoute' => 'student.payments.receipt',
        ]);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $student = $this->portal->studentFor($request->user());
        $payment = $this->portal->paymentForStudent($student, $payment);

        return view('fees.receipt', [
            'payment' => $payment,
            'backUrl' => route('student.fees'),
        ]);
    }

    public function attendance(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        return view('portal.attendance.index', [
            'student' => $student,
            'session' => $session,
            'attendance' => $this->portal->attendance($student, $session),
            'enrollment' => $this->portal->enrollment($student, $session),
            'portalRole' => 'student',
        ]);
    }

    public function downloads(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());

        return view('portal.downloads', [
            'student' => $student,
            'downloads' => $this->portal->downloads(),
            'portalRole' => 'student',
        ]);
    }

    public function routine(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();
        $routine = $this->portal->classRoutine($student, $session);
        $enrollment = $this->portal->enrollment($student, $session);

        return view('portal.student.routine', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $enrollment,
            'routine' => $routine,
            'days' => $this->schedule->workingDays(),
            'slots' => $this->schedule->activeSlots($enrollment?->class_id),
            'entryMap' => $routine ? $this->schedule->entryMap($routine) : collect(),
        ]);
    }

    public function assignments(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        $assignments = $this->portal->classContents('assignment', $student, $session)
            ->sortBy(fn ($item) => $item->due_date?->timestamp ?? PHP_INT_MAX)
            ->values();

        return view('portal.student.assignments', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $this->portal->enrollment($student, $session),
            'assignments' => $assignments,
            'pending' => $assignments->filter(fn ($item) => ! $item->due_date || $item->due_date->gte(today()))->count(),
            'overdue' => $assignments->filter(fn ($item) => $item->due_date && $item->due_date->lt(today()))->count(),
        ]);
    }

    public function materials(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        $materials = $this->portal->studyMaterials($student, $session);

        if ($request->filled('category')) {
            $materials = $materials->filter(fn ($item) => $item->category === $request->string('category')->toString());
        }

        if ($request->filled('q')) {
            $term = Str::lower($request->string('q')->toString());
            $materials = $materials->filter(fn ($item) => Str::contains(Str::lower(implode(' ', [
                $item->title, $item->category, $item->excerpt,
            ])), $term));
        }

        return view('portal.student.materials', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $this->portal->enrollment($student, $session),
            'materials' => $materials->values(),
            'categories' => $this->portal->studyMaterials($student, $session)
                ->pluck('category')->filter()->unique()->sort()->values(),
        ]);
    }

    public function notices(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        return view('portal.student.notices', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $this->portal->enrollment($student, $session),
            'notices' => $this->portal->noticeQuery($student, $session)
                ->latest('publish_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function notice(Request $request, Notice $notice): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();
        $notice = $this->portal->noticeForStudent($student, $session, $notice);

        return view('portal.student.notice', [
            'student' => $student,
            'session' => $session,
            'notice' => $notice,
        ]);
    }

    public function calendar(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        $session = current_session();

        return view('portal.student.calendar', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $this->portal->enrollment($student, $session),
            'items' => $this->portal->calendarItems(),
            'exams' => $this->portal->examSchedule($student, $session),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedResult(Examination $exam, int $studentId, Student $student): array
    {
        abort_unless(
            $exam->status === 'published'
            && $exam->result_published_at !== null
            && $exam->resultSnapshots()->where('student_id', $studentId)->exists(),
            404
        );

        $payload = $this->portal->result($exam, $student);
        abort_unless($payload, 404);

        return $payload;
    }
}
