<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReversal;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly FeeAssignmentService $assignments,
        private readonly AuditLogService $audit
    ) {}

    /**
     * Collect a payment and allocate it across outstanding fee assignments.
     *
     * @param  array<int|string, float|string>  $lines  assignment_id => amount
     */
    public function collect(Student $student, AcademicSession $session, array $lines, array $data, User $actor): Payment
    {
        $normalized = collect($lines)
            ->mapWithKeys(fn ($amount, $id) => [(int) $id => round((float) $amount, 2)])
            ->filter(fn ($amount, $id) => $id > 0 && $amount > 0)
            ->all();

        if (empty($normalized)) {
            throw ValidationException::withMessages(['allocations' => 'Enter an amount against at least one fee.']);
        }

        return DB::transaction(function () use ($student, $session, $normalized, $data, $actor) {
            $assignments = StudentFeeAssignment::query()
                ->whereIn('id', array_keys($normalized))
                ->where('student_id', $student->id)
                ->where('academic_session_id', $session->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($assignments->count() !== count($normalized)) {
                throw ValidationException::withMessages([
                    'allocations' => 'One or more selected fees do not belong to this student and session.',
                ]);
            }

            $total = 0.0;

            foreach ($normalized as $id => $amount) {
                $assignment = $assignments[$id];

                if (! in_array($assignment->status, ['pending', 'partial'], true)) {
                    throw ValidationException::withMessages([
                        'allocations' => "The fee for {$assignment->period} is not outstanding.",
                    ]);
                }

                $balance = round((float) $assignment->net_amount - (float) $assignment->activeAllocations()->sum('amount'), 2);

                if ($amount > $balance + 0.001) {
                    throw ValidationException::withMessages([
                        'allocations' => "Amount for {$assignment->period} exceeds the unpaid balance of ".money($balance).'.',
                    ]);
                }

                $total += $amount;
            }

            $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();
            $receipt = $this->numbers->receiptNumber((int) $paidAt->format('Y'));

            $payment = Payment::create([
                'receipt_no' => $receipt,
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'amount' => $total,
                'discount_amount' => 0,
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'paid_at' => $paidAt,
                'status' => 'completed',
                'note' => $data['note'] ?? null,
                'received_by' => $actor->id,
            ]);

            foreach ($normalized as $id => $amount) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'student_fee_assignment_id' => $id,
                    'amount' => $amount,
                    'discount_amount' => 0,
                ]);

                $this->assignments->recalcStatus($assignments[$id]);
            }

            $this->audit->record(
                'payment_collected',
                'Fees',
                'Collected '.money($total)." from {$student->full_name} (receipt {$receipt})",
                $payment,
                null,
                ['receipt_no' => $receipt, 'amount' => $total, 'method' => $data['payment_method']]
            );

            return $payment->load('allocations.assignment.category');
        });
    }

    /**
     * Build allocation lines for a lump-sum quick collection, oldest dues first.
     *
     * @return array<int, float>
     */
    public function autoAllocate(Student $student, AcademicSession $session, float $amount): array
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        $direction = SchoolSetting::get('fee_allocation_order', 'oldest') === 'newest' ? 'desc' : 'asc';

        $assignments = StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date', $direction)
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get();

        $lines = [];
        $remaining = round($amount, 2);

        foreach ($assignments as $assignment) {
            if ($remaining <= 0) {
                break;
            }

            $balance = round((float) $assignment->net_amount - (float) $assignment->paid_amount, 2);

            if ($balance <= 0) {
                continue;
            }

            $allocated = min($balance, $remaining);
            $lines[$assignment->id] = round($allocated, 2);
            $remaining = round($remaining - $allocated, 2);
        }

        if ($remaining > 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'The amount exceeds the total outstanding balance of '.money($amount - $remaining).'.',
            ]);
        }

        return $lines;
    }

    public function reverse(Payment $payment, string $type, string $reason, User $actor): Payment
    {
        if (! in_array($type, ['cancellation', 'refund'], true)) {
            throw ValidationException::withMessages(['type' => 'Invalid reversal type.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for audit purposes.']);
        }

        return DB::transaction(function () use ($payment, $type, $reason, $actor) {
            $locked = Payment::query()->lockForUpdate()->with('allocations')->findOrFail($payment->id);

            if ($locked->status === 'reversed') {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }

            PaymentReversal::create([
                'payment_id' => $locked->id,
                'amount' => $locked->amount,
                'type' => $type,
                'reason' => $reason,
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
            ]);

            $locked->update(['status' => 'reversed', 'reversed_at' => now()]);

            foreach ($locked->allocations as $allocation) {
                $assignment = StudentFeeAssignment::find($allocation->student_fee_assignment_id);
                if ($assignment) {
                    $this->assignments->recalcStatus($assignment);
                }
            }

            $this->audit->record(
                'payment_reversed',
                'Fees',
                ucfirst($type).' of payment '.$locked->receipt_no.' for '.money($locked->amount).': '.$reason,
                $locked,
                ['status' => 'completed'],
                ['status' => 'reversed', 'type' => $type, 'reason' => $reason]
            );

            return $locked;
        });
    }
}
