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
