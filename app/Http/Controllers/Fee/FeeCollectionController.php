<?php

namespace App\Http\Controllers\Fee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\FeeAssignmentService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FeeCollectionController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly FeeAssignmentService $fees,
        private readonly \App\Services\ScholarshipService $scholarships
    ) {}

    /**
     * Scholarship desk — pick a student, see their fee rows for the current
     * session and grant a percentage / fixed concession (or waive) per fee,
     * mirroring the collection ledger but without collecting money.
     */
    public function scholarships(Request $request): View
    {
        $session = current_session();

        $enrollments = Enrollment::query()
            ->with(['student.guardians', 'class', 'section'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->where('status', 'active')
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->whereHas('student', fn ($q) => $q
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('admission_no', 'like', "%{$term}%")
                    ->orWhere('student_code', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%")));
            })
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->paginate(20)
            ->withQueryString();

        $studentIds = $enrollments->pluck('student_id');
        $selectedStudent = null;
        if ($request->filled('student_id')) {
            $selectedStudent = Student::query()
                ->with('guardians')
                ->find($request->integer('student_id'));
            if ($selectedStudent && ! $studentIds->contains($selectedStudent->id)) {
                $studentIds = $studentIds->push($selectedStudent->id);
            }
        }

        $assignments = collect();
        if ($selectedStudent && $session) {
            $assignments = StudentFeeAssignment::query()
                ->with('category')
                ->where('student_id', $selectedStudent->id)
                ->where('academic_session_id', $session->id)
                ->orderBy('due_date')
                ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                    ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
                ->get();
        }

        $stats = [
            'students_with_scholarship' => StudentFeeAssignment::query()
                ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
                ->where('scholarship_amount', '>', 0)
                ->distinct('student_id')
                ->count('student_id'),
            'total_scholarship' => (float) StudentFeeAssignment::query()
                ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
                ->sum('scholarship_amount'),
            'open_rows' => StudentFeeAssignment::query()
                ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
                ->whereIn('status', ['pending', 'partial'])
                ->count(),
        ];

        return view('fees.scholarships.index', [
            'enrollments' => $enrollments,
            'selectedStudent' => $selectedStudent,
            'assignments' => $assignments,
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
            'stats' => $stats,
        ]);
    }

    public function scholarshipsApply(Student $student, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:student_fee_assignments,id'],
            'scholarship_type' => ['required', 'in:percent,fixed,none'],
            'scholarship_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $session = current_session();
        abort_unless($session, 404);

        $assignments = StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->whereIn('id', $data['assignment_ids'])
            ->whereIn('status', ['pending', 'partial'])
            ->get();

        if ($assignments->isEmpty()) {
            return back()->with('error', 'No open fee rows were selected for this student.');
        }

        $type = $data['scholarship_type'] === 'none' ? 'fixed' : $data['scholarship_type'];
        $value = $data['scholarship_type'] === 'none' ? 0 : (float) ($data['scholarship_value'] ?? 0);

        if ($data['scholarship_type'] !== 'none' && $value <= 0) {
            return back()->with('error', 'Enter the scholarship percentage or fixed amount you want to grant.');
        }

        foreach ($assignments as $assignment) {
            $this->fees->applyScholarship($assignment, $type, $value, $request->user());
        }

        return redirect()
            ->route('fees.scholarships.index', ['student_id' => $student->id])
            ->with('success', $value > 0
                ? 'Scholarship applied to '.$assignments->count().' fee row(s) for '.$student->full_name.'.'
                : 'Scholarship removed from '.$assignments->count().' fee row(s) for '.$student->full_name.'.');
    }

    public function index(Request $request): View
    {
        $session = current_session();

        $enrollments = Enrollment::query()
            ->with(['student.guardians', 'class', 'section'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->where('status', 'active')
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->whereHas('student', fn ($q) => $q
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('student_code', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%")));
            })
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->paginate(20)
            ->withQueryString();

        $dues = $this->dueMap($session, $enrollments->pluck('student_id'));

        $totals = [
            'collected' => $session ? Payment::where('academic_session_id', $session->id)->where('status', 'completed')->sum('amount') : 0,
            'outstanding' => round($dues->sum(), 2),
        ];

        return view('fees.collection.index', [
            'enrollments' => $enrollments,
            'dues' => $dues,
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
            'totals' => $totals,
        ]);
    }

    public function show(Request $request, Student $student): View
    {
        $session = current_session();
        abort_unless($session, 404, 'No academic session is configured.');

        $enrollment = Enrollment::query()
            ->with(['class', 'section'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->firstOrFail();

        // Advance payment is opt-in and only for staff allowed to collect fees.
        $includeFuture = $request->boolean('include_future')
            && ($request->user()?->can('fees.collect') ?? false);

        $allAssignments = StudentFeeAssignment::query()
            ->with('category')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderBy('due_date')
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get();

        $horizon = \App\Support\FeeWindow::horizonDate();

        $hidden = $allAssignments
            ->filter(fn (StudentFeeAssignment $row) => $row->due_date
                && $row->due_date->toDateString() > $horizon);

        $assignments = $includeFuture
            ? $allAssignments
            : $allAssignments->reject(fn (StudentFeeAssignment $row) => $row->due_date
                && $row->due_date->toDateString() > $horizon)->values();

        $hiddenDue = $hidden
            ->whereIn('status', ['pending', 'partial'])
            ->sum(fn (StudentFeeAssignment $row) => $row->balance);

        $payments = Payment::query()
            ->withCount('allocations')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->latest('paid_at')
            ->get();

        $scholarships = \App\Models\StudentScholarship::query()
            ->with(['items.category'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderByDesc('created_at')
            ->get();

        return view('fees.collection.show', [
            'student' => $student->load('guardians'),
            'enrollment' => $enrollment,
            'assignments' => $assignments,
            'hiddenFutureCount' => $hidden->count(),
            'hiddenFutureDue' => round((float) $hiddenDue, 2),
            'includeFuture' => $includeFuture,
            'windowLabel' => \App\Support\FeeWindow::label(),
            'payments' => $payments,
            'scholarships' => $scholarships,
            'session' => $session,
            'structures' => FeeStructure::with('items')
                ->where('academic_session_id', $session->id)
                ->where('class_id', $enrollment->class_id)
                ->where('status', 'active')
                ->get(),
        ]);
    }

    public function store(StorePaymentRequest $request, Student $student): RedirectResponse
    {
        $session = current_session();
        abort_unless($session, 404);

        $payment = $this->payments->collect(
            $student,
            $session,
            $request->input('allocations', []),
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('payments.receipt', $payment)
            ->with('success', 'Payment collected successfully. Receipt '.$payment->receipt_no.' is ready to print.');
    }

    /**
     * Grant (or remove) a percentage or fixed scholarship against one fee row.
     * Removing keeps the audit trail: the concession is logged either way.
     */
    public function scholarship(Request $request, StudentFeeAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'scholarship_type' => ['required', 'in:percent,fixed,none'],
            'scholarship_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $value = $data['scholarship_type'] === 'none' ? 0 : (float) ($data['scholarship_value'] ?? 0);

        if ($data['scholarship_type'] !== 'none' && $value <= 0) {
            return back()->with('error', 'Enter the scholarship percentage or fixed amount you want to grant.');
        }

        $this->fees->applyScholarship(
            $assignment,
            $data['scholarship_type'] === 'none' ? 'fixed' : $data['scholarship_type'],
            $value,
            $request->user()
        );

        return back()->with('success', $value > 0
            ? 'Scholarship applied to this fee row.'
            : 'Scholarship removed from this fee row.');
    }

    public function waiver(Request $request, StudentFeeAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'waiver_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->fees->applyWaiver($assignment, (float) $data['waiver_amount'], $data['reason'], $request->user());

        return back()->with('success', 'Waiver approved and recorded against the fee row.');
    }

    public function assignStructure(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'fee_structure_id' => ['required', 'exists:fee_structures,id'],
            'optional_items' => ['nullable', 'array'],
            'optional_items.*' => ['integer', 'exists:fee_structure_items,id'],
        ]);

        $session = current_session();
        abort_unless($session, 404);

        $structure = FeeStructure::findOrFail($data['fee_structure_id']);
        $created = $this->fees->assignStructure(
            $student,
            $structure,
            $session,
            array_map('intval', $data['optional_items'] ?? []),
            $request->user()
        );

        return back()->with('success', $created > 0
            ? "{$created} new fee row(s) assigned from {$structure->name}."
            : 'No new fee rows were needed; this structure is already assigned.');
    }

    private function dueMap(?AcademicSession $session, Collection $studentIds): Collection
    {
        if (! $session) {
            return collect();
        }

        return StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['pending', 'partial'])
            // Only the current billing window counts towards "outstanding":
            // far-future invoices are not due yet and must not inflate totals.
            ->where(fn ($query) => $query
                ->whereNull('due_date')
                ->orWhere('due_date', '<=', \App\Support\FeeWindow::horizonDate()))
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2));
    }
}
