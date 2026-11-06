<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StudentFeeAssignment;
use Carbon\Carbon;

class PaymentSlipService
{
    /**
     * Build the data for the locked payment-slip design.
     *
     * @return array<string, mixed>
     */
    public function build(Payment $payment): array
    {
        $payment->load([
            'student.guardians',
            'student.enrollments.class',
            'student.enrollments.section',
            'academicSession',
            'allocations.assignment.category',
            'receivedBy',
        ]);

        $student = $payment->student;
        $enrollment = $student?->enrollments?->firstWhere('academic_session_id', $payment->academic_session_id);

        $lines = $payment->allocations->map(function ($allocation) {
            $scholarship = round((float) $allocation->discount_amount, 2);
            $payable = round((float) $allocation->amount, 2);

            return [
                'fee' => $allocation->assignment?->category?->name ?? 'Fee',
                'period' => $this->periodLabel($allocation->assignment?->period),
                'amount' => round($payable + $scholarship, 2),
                'scholarship' => $scholarship,
                'payable' => $payable,
            ];
        })->values()->all();

        $totalFee = round(array_sum(array_column($lines, 'amount')), 2);
        $totalScholarship = round(array_sum(array_column($lines, 'scholarship')), 2);
        $netPayable = round(array_sum(array_column($lines, 'payable')), 2);

        $outstanding = round((float) StudentFeeAssignment::query()
            ->where('student_id', $payment->student_id)
            ->where('academic_session_id', $payment->academic_session_id)
            ->whereIn('status', ['pending', 'partial'])
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($inner) => $inner->where('status', 'completed'))], 'amount')
            ->get()
            ->sum(fn (StudentFeeAssignment $row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2);

        $paidToDate = round((float) Payment::query()
            ->where('student_id', $payment->student_id)
            ->where('academic_session_id', $payment->academic_session_id)
            ->where('status', 'completed')
            ->sum('amount'), 2);

        $isCompleted = $payment->status === 'completed';
        $previousDue = round($outstanding + ($isCompleted ? (float) $payment->amount : 0), 2);

        $status = match (true) {
            $payment->status === 'reversed' => 'REVERSED',
            $outstanding <= 0.009 => 'PAID',
            $paidToDate > 0 => 'PARTIAL',
            default => 'UNPAID',
        };

        return [
            'receipt_no' => $payment->receipt_no,
            'paid_at' => $payment->paid_at,
            'method' => $payment->method_label,
            'reference' => $payment->reference_no,
            'note' => $payment->note,
            'received_by' => $payment->receivedBy?->name ?? 'System',
            'session' => $payment->academicSession?->name,
            'student' => [
                'name' => $student?->full_name,
                'student_code' => $student?->student_code,
                'class_section' => collect([$enrollment?->class?->name, $enrollment?->section?->name])->filter()->implode(' - ') ?: '—',
                'guardian' => $student?->primaryGuardian()?->name,
            ],
            'lines' => $lines,
            'total_fee' => $totalFee,
            'total_scholarship' => $totalScholarship,
            'net_payable' => $netPayable,
            'previous_due' => $previousDue,
            'amount_paid' => (float) $payment->amount,
            'paid_to_date' => $paidToDate,
            'outstanding' => $outstanding,
            'advance' => 0.0,
            'status' => $status,
            'words' => amount_in_words((float) $payment->amount),
            'qr_payload' => $this->qrPayload($payment, $status),
        ];
    }

    private function qrPayload(Payment $payment, string $status): string
    {
        return implode('|', array_filter([
            'EDUSPHERE',
            (string) $payment->receipt_no,
            (string) $payment->student?->student_code,
            number_format((float) $payment->amount, 2, '.', ''),
            $payment->paid_at?->format('Y-m-d'),
            $status,
        ]));
    }

    private function periodLabel(?string $period): string
    {
        if (! $period) {
            return 'One-time';
        }

        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m', $period)->format('M Y');
        }

        return $period;
    }
}
