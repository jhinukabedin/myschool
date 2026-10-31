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
        private readonly FeeAssignmentService $fees
    ) {}

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
                    ->orWhere('admission_no', 'like', "%{$term}%")
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

    public function show(Student $student): View
    {
        $session = current_session();
        abort_unless($session, 404, 'No academic session is configured.');

        $enrollment = Enrollment::query()
            ->with(['class', 'section'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->firstOrFail();

        $assignments = StudentFeeAssignment::query()
            ->with('category')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderBy('due_date')
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get();

        $payments = Payment::query()
            ->withCount('allocations')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->latest('paid_at')
            ->get();

        return view('fees.collection.show', [
            'student' => $student->load('guardians'),
            'enrollment' => $enrollment,
            'assignments' => $assignments,
            'payments' => $payments,
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
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2));
    }
}
