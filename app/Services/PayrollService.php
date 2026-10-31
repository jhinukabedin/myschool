<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentReversal;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Generate pending payroll rows for every active staff member with an
     * effective salary structure. Existing rows are never duplicated.
     */
    public function generate(AcademicSession $session, string $month, User $actor): int
    {
        try {
            $period = Carbon::createFromFormat('Y-m', $month);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['month' => 'Choose a valid payroll month.']);
        }

        if ($period->copy()->endOfMonth()->lt($session->start_date) || $period->copy()->startOfMonth()->gt($session->end_date)) {
            throw ValidationException::withMessages([
                'month' => 'The payroll month falls outside the selected academic session.',
            ]);
        }

        $created = 0;
        $periodEnd = $period->copy()->endOfMonth();

        DB::transaction(function () use ($session, $month, $periodEnd, &$created) {
            $staffMembers = Staff::query()
                ->where('status', 'active')
                ->with(['salaryStructures' => fn ($query) => $query
                    ->where('effective_from', '<=', $periodEnd->toDateString())
                    ->orderByDesc('effective_from')])
                ->get();

            foreach ($staffMembers as $staff) {
                $structure = $staff->salaryStructures->first();

                if (! $structure) {
                    continue;
                }

                if (Payroll::query()->where('staff_id', $staff->id)->where('month', $month)->exists()) {
                    continue;
                }

                $allowances = [
                    'House allowance' => (float) $structure->house_allowance,
                    'Transport allowance' => (float) $structure->transport_allowance,
                    'Medical allowance' => (float) $structure->medical_allowance,
                    'Other allowance' => (float) $structure->other_allowance,
                ];
                $deductions = [
                    'Tax deduction' => (float) $structure->tax_deduction,
                    'Provident fund' => (float) $structure->provident_fund,
                    'Other deduction' => (float) $structure->other_deduction,
                ];

                $totalAllowances = array_sum($allowances);
                $totalDeductions = array_sum($deductions);

                $payroll = Payroll::create([
                    'academic_session_id' => $session->id,
                    'staff_id' => $staff->id,
                    'month' => $month,
                    'basic_salary' => $structure->basic_salary,
                    'total_allowances' => $totalAllowances,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => (float) $structure->basic_salary + $totalAllowances - $totalDeductions,
                    'status' => 'pending',
                ]);

                foreach ($allowances as $name => $amount) {
                    if ($amount > 0) {
                        PayrollItem::create([
                            'payroll_id' => $payroll->id,
                            'type' => 'allowance',
                            'name' => $name,
                            'amount' => $amount,
                        ]);
                    }
                }

                foreach ($deductions as $name => $amount) {
                    if ($amount > 0) {
                        PayrollItem::create([
                            'payroll_id' => $payroll->id,
                            'type' => 'deduction',
                            'name' => $name,
                            'amount' => $amount,
                        ]);
                    }
                }

                $created++;
            }

            $this->audit->record(
                'payroll_generated',
                'Payroll',
                "Generated payroll for {$month} ({$created} staff)",
                null,
                null,
                ['month' => $month, 'created' => $created]
            );
        });

        return $created;
    }

    public function process(Payroll $payroll, User $actor): Payroll
    {
        if ($payroll->status !== 'pending') {
            throw ValidationException::withMessages(['payroll' => 'Only pending payrolls can be processed.']);
        }

        $payroll->update([
            'status' => 'processed',
            'processed_by' => $actor->id,
            'processed_at' => now(),
        ]);

        $this->audit->record(
            'payroll_processed',
            'Payroll',
            "Processed payroll #{$payroll->id} for {$payroll->staff?->full_name} ({$payroll->month})",
            $payroll,
            ['status' => 'pending'],
            ['status' => 'processed']
        );

        return $payroll;
    }

    /**
     * Cancel a payroll that has not received any completed installment.
     * The row is retained for audit and excluded from payable totals.
     */
    public function cancel(Payroll $payroll, string $reason, User $actor): Payroll
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for audit purposes.']);
        }

        return DB::transaction(function () use ($payroll, $reason) {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);

            if (! in_array($locked->status, ['pending', 'processed'], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Only pending or processed payrolls without installments can be cancelled. Reverse the installments first.',
                ]);
            }

            if ($this->lockedPaidAmount($locked) > 0.009) {
                throw ValidationException::withMessages([
                    'payroll' => 'This payroll already has salary installments. Reverse them before cancelling.',
                ]);
            }

            $beforeStatus = $locked->status;
            $locked->update(['status' => 'cancelled']);

            $this->audit->record(
                'payroll_cancelled',
                'Payroll',
                "Cancelled payroll #{$locked->id} for {$locked->staff?->full_name} ({$locked->month}): {$reason}",
                $locked,
                ['status' => $beforeStatus],
                ['status' => 'cancelled', 'reason' => $reason]
            );

            return $locked;
        });
    }

    /**
     * Record a salary payment installment.
     *
     * A payroll may receive several partial installments. The payable balance
     * is always the net salary minus the sum of valid (completed) installments,
     * never the presence of a single payment row.
     */
    public function pay(Payroll $payroll, array $data, User $actor): SalaryPayment
    {
        $amount = round((float) ($data['amount'] ?? $payroll->net_salary), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The payment amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($payroll, $data, $amount, $actor) {
            // Lock the payroll first. Every payment/reversal path takes this
            // lock, so concurrent submissions serialize and cannot overpay.
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);

            if (! in_array($locked->status, ['processed', 'partially_paid'], true)) {
                throw ValidationException::withMessages(['payroll' => 'Process the payroll before recording a salary payment.']);
            }

            $paid = $this->lockedPaidAmount($locked);
            $remaining = round((float) $locked->net_salary - $paid, 2);

            if ($remaining <= 0.009) {
                throw ValidationException::withMessages(['payroll' => 'This salary has already been paid in full.']);
            }

            if ($amount > $remaining + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment exceeds the outstanding salary balance of '.money($remaining).'.',
                ]);
            }

            $payment = SalaryPayment::create([
                'payroll_id' => $locked->id,
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'paid_by' => $actor->id,
                'status' => 'completed',
            ]);

            $newPaid = round($paid + $amount, 2);
            $status = $newPaid >= (float) $locked->net_salary - 0.009 ? 'paid' : 'partially_paid';
            $locked->update(['status' => $status]);

            $this->audit->record(
                'salary_paid',
                'Payroll',
                'Recorded salary installment of '.money($payment->amount)." to {$locked->staff?->full_name} for {$locked->month}",
                $payment,
                ['paid_before' => $paid],
                ['paid_after' => $newPaid, 'status' => $status, 'method' => $payment->payment_method]
            );

            return $payment;
        });
    }

    /**
     * Reverse the most recent completed installment on a payroll.
     *
     * Kept for backwards compatibility; delegates to reversePayment().
     */
    public function reverse(Payroll $payroll, string $reason, User $actor): SalaryPayment
    {
        $payment = SalaryPayment::query()
            ->where('payroll_id', $payroll->id)
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages(['payroll' => 'This payroll has no completed salary payment to reverse.']);
        }

        return $this->reversePayment($payment, $reason, $actor);
    }

    /**
     * Reverse a single salary installment without deleting the original record.
     *
     * The payroll status is recomputed from the remaining valid installments
     * (processed / partially_paid / paid), so a corrected payment can be
     * recorded while the reversed row and its reason remain auditable.
     */
    public function reversePayment(SalaryPayment $payment, string $reason, User $actor): SalaryPayment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for audit purposes.']);
        }

        return DB::transaction(function () use ($payment, $reason, $actor) {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payment->payroll_id);

            $locked = SalaryPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== 'completed') {
                throw ValidationException::withMessages(['payment' => 'This salary payment is no longer active and cannot be reversed again.']);
            }

            if ($locked->reversals()->exists()) {
                throw ValidationException::withMessages(['payment' => 'This salary payment has already been reversed.']);
            }

            SalaryPaymentReversal::create([
                'salary_payment_id' => $locked->id,
                'amount' => $locked->amount,
                'reason' => $reason,
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
            ]);

            $locked->update(['status' => 'reversed', 'reversed_at' => now()]);

            // Recompute the payroll lifecycle from the remaining valid installments.
            $remainingPaid = $this->lockedPaidAmount($lockedPayroll);
            $net = (float) $lockedPayroll->net_salary;

            $status = match (true) {
                $remainingPaid <= 0.009 => 'processed',
                $remainingPaid >= $net - 0.009 => 'paid',
                default => 'partially_paid',
            };

            $lockedPayroll->update(['status' => $status]);

            $this->audit->record(
                'salary_payment_reversed',
                'Payroll',
                'Reversed salary installment of '.money($locked->amount)." to {$lockedPayroll->staff?->full_name} for {$lockedPayroll->month}: {$reason}",
                $locked,
                ['status' => 'completed'],
                ['status' => 'reversed', 'reason' => $reason, 'payroll_status' => $status]
            );

            return $locked;
        });
    }

    /**
     * Sum completed installments using a locking read so the value reflects the
     * latest committed state and not a stale snapshot under concurrent writes.
     */
    private function lockedPaidAmount(Payroll $locked): float
    {
        $sum = SalaryPayment::query()
            ->where('payroll_id', $locked->id)
            ->where('status', 'completed')
            ->lockForUpdate()
            ->get()
            ->sum('amount');

        return round((float) $sum, 2);
    }
}
