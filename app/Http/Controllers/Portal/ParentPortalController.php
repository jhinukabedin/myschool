<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\Payment;
use App\Models\Student;
use App\Services\PortalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentPortalController extends Controller
{
    public function __construct(private readonly PortalService $portal) {}

    public function dashboard(Request $request): View
    {
        $children = $this->portal->childrenFor($request->user());
        $child = $this->portal->selectedChild($request->user(), $request->integer('child') ?: null);
        $session = current_session();

        return view('portal.parent.dashboard', [
            'children' => $children,
            'child' => $child,
            'session' => $session,
            'enrollment' => $child ? $this->portal->enrollment($child, $session) : null,
            'attendance' => $child ? $this->portal->attendance($child, $session) : null,
            'results' => $child ? $this->portal->publishedResults($child, $session)->take(3) : collect(),
            'fees' => $child ? $this->portal->fees($child, $session) : null,
            'exams' => $child ? $this->portal->examSchedule($child, $session)->take(5) : collect(),
            'notices' => $child ? $this->portal->notices($child, $session)->take(5) : collect(),
        ]);
    }

    public function profile(Request $request): View
    {
        $children = $this->portal->childrenFor($request->user());

        return view('portal.parent.profile', [
            'user' => $request->user(),
            'children' => $children->load(['guardians', 'enrollments.class', 'enrollments.section', 'enrollments.academicSession']),
            'links' => $request->user()->parentStudentLinks()->active()->with('guardian')->get(),
        ]);
    }

    public function results(Request $request, Student $student): View
    {
        $this->portal->authorizeChild($request->user(), $student);
        $session = current_session();

        return view('portal.results.index', [
            'student' => $student,
            'session' => $session,
            'results' => $this->portal->publishedResults($student, $session),
            'portalPrefix' => 'parent',
            'enrollment' => $this->portal->enrollment($student, $session),
            'children' => $this->portal->childrenFor($request->user()),
            'child' => $student,
        ]);
    }

    public function resultSheet(Request $request, Student $student, Examination $exam): View
    {
        $this->portal->authorizeChild($request->user(), $student);

        abort_unless(
            $exam->status === 'published'
            && $exam->result_published_at !== null
            && $exam->resultSnapshots()->where('student_id', $student->id)->exists(),
            404
        );

        $payload = $this->portal->result($exam, $student);
        abort_unless($payload, 404);

        return view('portal.results.sheet', $payload + [
            'backUrl' => route('parent.results.index', $student),
            'portalRole' => 'Parent',
        ]);
    }

    public function fees(Request $request, Student $student): View
    {
        $this->portal->authorizeChild($request->user(), $student);
        $session = current_session();

        return view('portal.fees.index', [
            'student' => $student,
            'session' => $session,
            'fees' => $this->portal->fees($student, $session),
            'portalRole' => 'parent',
            'receiptRoute' => 'parent.payments.receipt',
            'children' => $this->portal->childrenFor($request->user()),
            'child' => $student,
        ]);
    }

    public function receipt(Request $request, Student $student, Payment $payment): View
    {
        $this->portal->authorizeChild($request->user(), $student);
        $payment = $this->portal->paymentForStudent($student, $payment);

        return view('fees.receipt', [
            'payment' => $payment,
            'backUrl' => route('parent.fees.index', $student),
        ]);
    }

    public function attendance(Request $request, Student $student): View
    {
        $this->portal->authorizeChild($request->user(), $student);
        $session = current_session();

        return view('portal.attendance.index', [
            'student' => $student,
            'session' => $session,
            'attendance' => $this->portal->attendance($student, $session),
            'enrollment' => $this->portal->enrollment($student, $session),
            'portalRole' => 'parent',
            'children' => $this->portal->childrenFor($request->user()),
            'child' => $student,
        ]);
    }
}
