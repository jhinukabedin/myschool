<?php

namespace App\Http\Controllers\Fee;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function receipt(Payment $payment): View
    {
        $payment->load([
            'student.guardians',
            'academicSession',
            'allocations.assignment.category',
            'receivedBy',
            'reversals.reversedBy',
        ]);

        return view('fees.receipt', ['payment' => $payment]);
    }

    public function reversals(Request $request): View
    {
        $reversals = PaymentReversal::query()
            ->with(['payment.student', 'reversedBy'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest('reversed_at')
            ->paginate(20)
            ->withQueryString();

        return view('fees.deletion-log', [
            'reversals' => $reversals,
            'totals' => [
                'count' => PaymentReversal::count(),
                'amount' => PaymentReversal::sum('amount'),
            ],
        ]);
    }

    /**
     * Global payment history: every receipt in the school, searchable by RCP
     * number, student, Student ID or reference, with a link to the printable
     * receipt for each row.
     */
    public function history(Request $request): View
    {
        $query = $this->filteredPayments($request);

        $payments = $query
            ->with(['student', 'academicSession', 'receivedBy'])
            ->withCount('allocations')
            ->latest('paid_at')
            ->paginate(25)
            ->withQueryString();

        $totals = [
            'receipts' => (clone $query)->count(),
            'collected' => (float) (clone $query)->where('status', 'completed')->sum('amount'),
            'scholarship' => (float) (clone $query)->where('status', 'completed')->sum('discount_amount'),
            'reversed' => (float) (clone $query)->where('status', 'reversed')->sum('amount'),
        ];

        return view('fees.payment-history', [
            'payments' => $payments,
            'totals' => $totals,
            'sessions' => \App\Models\AcademicSession::orderByDesc('start_date')->get(),
            'methods' => ['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cheque' => 'Cheque', 'card' => 'Card'],
            'currentSession' => current_session(),
        ]);
    }

    /**
     * Apply the history filters to a fresh Payment query.
     */
    private function filteredPayments(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        return Payment::query()
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('receipt_no', 'like', "%{$term}%")
                        ->orWhere('reference_no', 'like', "%{$term}%")
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('student_code', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('payment_method', $request->string('method')))
            ->when($request->filled('session_id'), fn ($query) => $query->where('academic_session_id', $request->integer('session_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('paid_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('paid_at', '<=', $request->date('to')));
    }

    /**
     * JSON payload for the receipt-details modal on the payment history page.
     */
    public function details(Payment $payment): \Illuminate\Http\JsonResponse
    {
        $payment->load([
            'student.guardians',
            'student.enrollments.class',
            'student.enrollments.section',
            'academicSession',
            'allocations.assignment.category',
            'receivedBy',
            'reversals.reversedBy',
        ]);

        $enrollment = $payment->student?->enrollments
            ?->firstWhere('academic_session_id', $payment->academic_session_id);

        return response()->json([
            'id' => $payment->id,
            'receipt_no' => $payment->receipt_no,
            'paid_at' => $payment->paid_at?->format('d M Y, h:i A'),
            'status' => $payment->status,
            'method' => $payment->method_label,
            'reference_no' => $payment->reference_no,
            'note' => $payment->note,
            'received_by' => $payment->receivedBy?->name ?? 'System',
            'session' => $payment->academicSession?->name,
            'amount' => (float) $payment->amount,
            'discount_amount' => (float) $payment->discount_amount,
            'student' => [
                'name' => $payment->student?->full_name,
                'student_code' => $payment->student?->student_code,
                'guardian' => $payment->student?->primaryGuardian()?->name,
                'class' => $enrollment?->class?->name,
                'section' => $enrollment?->section?->name,
                'roll' => $enrollment?->roll_no,
            ],
            'lines' => $payment->allocations->map(fn ($allocation) => [
                'fee' => $allocation->assignment?->category?->name ?? 'Fee',
                'period' => $allocation->assignment?->period,
                'scholarship' => (float) $allocation->discount_amount,
                'amount' => (float) $allocation->amount,
            ])->values(),
            'reversals' => $payment->reversals->map(fn ($reversal) => [
                'type' => ucfirst($reversal->type),
                'reason' => $reversal->reason,
                'at' => $reversal->reversed_at?->format('d M Y, h:i A'),
                'by' => $reversal->reversedBy?->name ?? 'System',
            ])->values(),
            'receipt_url' => route('payments.receipt', $payment),
            'print_url' => route('payments.receipt', ['payment' => $payment, 'print' => 1]),
        ]);
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:cancellation,refund'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->payments->reverse($payment, $data['type'], $data['reason'], $request->user());

        return redirect()
            ->route('payments.receipt', $payment)
            ->with('success', 'Payment reversed. The original receipt and a full audit record have been retained.');
    }
}
