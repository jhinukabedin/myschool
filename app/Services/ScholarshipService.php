<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\StudentScholarship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScholarshipService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Active (or approved) scholarships for a student in a session that cover
     * the given date. Used to auto-apply discounts when fee rows are created.
     *
     * @return array<int, StudentScholarship>
     */
    public function activeFor(int $studentId, int $sessionId, ?string $onDate = null): array
    {
        $date = $onDate ?? now()->toDateString();

        return StudentScholarship::query()
            ->with('items')
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', ['active', 'approved'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->get()
            ->all();
    }

    /**
     * Resolve the combined scholarship discount for one fee row under the given
     * combination policy. The result is capped at the row's gross-minus-discount
     * so the payable never goes negative.
     */
    public function combinedDiscountFor(
        StudentFeeAssignment $assignment,
        array $scholarships,
        string $policy = 'highest_only',
        ?string $onDate = null
    ): float {
        $date = $onDate ?? ($assignment->due_date?->toDateString() ?? now()->toDateString());
        $gross = max(0, (float) $assignment->amount - (float) $assignment->discount_amount);

        if ($gross <= 0) {
            return 0.0;
        }

        $perScholarship = [];

        foreach ($scholarships as $scholarship) {
            $discount = $scholarship->discountFor($assignment, $date);
            if ($discount > 0) {
                $perScholarship[spl_object_id($scholarship)] = $discount;
            }
        }

        if ($perScholarship === []) {
            return 0.0;
        }

        $combined = match ($policy) {
            'no_combine' => max($perScholarship),
            'approved_stacking' => array_sum($perScholarship),
            default => max($perScholarship),
        };

        return round(min($combined, $gross), 2);
    }

    /**
     * Apply a scholarship to an existing unpaid fee row. Preserves payment
     * history: if part is already paid, only the remaining balance is reduced
     * and the adjustment is recorded.
     */
    public function applyToExistingAssignment(
        StudentScholarship $scholarship,
        StudentFeeAssignment $assignment,
        string $policy = 'highest_only',
        ?User $actor = null,
        string $reason = 'Scholarship applied to existing unpaid invoice'
    ): void {
        DB::transaction(function () use ($scholarship, $assignment, $policy, $actor, $reason) {
            $locked = StudentFeeAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if (! in_array($locked->status, ['pending', 'partial'], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only unpaid invoices can be adjusted by a scholarship.',
                ]);
            }

            $paid = (float) $locked->activeAllocations()->sum('amount');
            $gross = max(0, (float) $locked->amount - (float) $locked->discount_amount);
            $onDate = $locked->due_date?->toDateString() ?? now()->toDateString();

            // Skip if this assignment was already adjusted by the same scholarship.
            $already = $locked->scholarshipAdjustments()
                ->where('student_scholarship_id', $scholarship->id)
                ->exists();

            if ($already && $scholarship->discount_frequency === 'once') {
                return;
            }

            $discount = $scholarship->discountFor($locked, $onDate);

            // Combine with any existing per-row scholarship already on the assignment.
            $existing = (float) $locked->scholarship_amount;

            $combined = match ($policy) {
                'no_combine' => max($existing, $discount),
                'approved_stacking' => $existing + $discount,
                default => max($existing, $discount),
            };

            $ceiling = max(0, round($gross - (float) $locked->waiver_amount, 2));
            $conceded = round(min($combined, $ceiling), 2);

            // Never let net fall below what is already paid.
            $payable = max($paid, round($gross - $conceded - (float) $locked->waiver_amount, 2));
            $conceded = round(max(0, $gross - $payable - (float) $locked->waiver_amount), 2);

            if ($conceded <= 0 && $existing <= 0) {
                return;
            }

            $old = $locked->only(['scholarship_type', 'scholarship_value', 'scholarship_amount', 'net_amount', 'status']);

            // Preserve the scholarship_type/value that produced the concession.
            $locked->forceFill([
                'scholarship_type' => $scholarship->discount_method,
                'scholarship_value' => $scholarship->discount_value,
                'scholarship_amount' => $conceded,
            ])->save();

            $this->recalcAssignment($locked, $payable);

            $locked->scholarshipAdjustments()->create([
                'student_scholarship_id' => $scholarship->id,
                'student_id' => $locked->student_id,
                'original_amount' => $gross,
                'discount_amount' => $conceded,
                'payable_amount' => $payable,
                'adjustment_reason' => $reason,
                'created_by' => $actor?->id,
            ]);

            $this->audit->record(
                'scholarship_invoice_adjustment',
                'Fees',
                "Scholarship {$scholarship->scholarship_name} applied to invoice {$locked->id} for {$locked->student?->full_name}: discount ".money($conceded),
                $locked,
                $old,
                $locked->only(['scholarship_type', 'scholarship_value', 'scholarship_amount', 'net_amount', 'status'])
            );
        });
    }

    /**
     * Create a student scholarship with its per-category items.
     */
    public function create(array $data, ?User $actor = null): StudentScholarship
    {
        $this->validateStudentEnrolled((int) $data['student_id'], (int) $data['academic_session_id']);

        return DB::transaction(function () use ($data, $actor) {
            $scholarship = StudentScholarship::create([
                'student_id' => $data['student_id'],
                'academic_session_id' => $data['academic_session_id'],
                'scholarship_name' => $data['scholarship_name'],
                'scholarship_type' => $data['scholarship_type'],
                'discount_method' => $data['discount_method'],
                'discount_value' => $data['discount_value'],
                'applies_to' => $data['applies_to'] ?? 'all',
                'discount_frequency' => $data['discount_frequency'] ?? 'monthly',
                'maximum_discount_amount' => $data['maximum_discount_amount'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => $data['status'] ?? 'pending',
                'description' => $data['description'] ?? null,
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor?->id,
            ]);

            if (($data['applies_to'] ?? 'all') === 'selected' && ! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $categoryId = (int) ($item['fee_category_id'] ?? 0);
                    if (! $categoryId || ! FeeCategory::query()->whereKey($categoryId)->exists()) {
                        continue;
                    }

                    $scholarship->items()->create([
                        'fee_category_id' => $categoryId,
                        'discount_type' => $item['discount_type'] ?? $scholarship->discount_method,
                        'discount_value' => $item['discount_value'] ?? $scholarship->discount_value,
                        'discount_frequency' => $item['discount_frequency'] ?? $scholarship->discount_frequency,
                        'maximum_discount_amount' => $item['maximum_discount_amount'] ?? null,
                    ]);
                }
            }

            if (($scholarship->status ?? 'pending') === 'active') {
                $this->activate($scholarship, $actor, false);
            }

            $this->audit->record(
                'scholarship_created',
                'Fees',
                "Scholarship {$scholarship->scholarship_name} created for {$scholarship->student?->full_name}",
                $scholarship,
                null,
                $scholarship->only(['status', 'discount_method', 'discount_value', 'start_date', 'end_date'])
            );

            return $scholarship->load('items');
        });
    }

    public function activate(StudentScholarship $scholarship, ?User $actor = null, bool $applyToExisting = false): void
    {
        $scholarship->forceFill([
            'status' => 'active',
            'approved_by' => $actor?->id ?? $scholarship->approved_by,
            'approved_at' => $scholarship->approved_at ?? now(),
        ])->save();

        $this->audit->record(
            'scholarship_activated',
            'Fees',
            "Scholarship {$scholarship->scholarship_name} activated for {$scholarship->student?->full_name}",
            $scholarship,
            null,
            ['status' => 'active']
        );

        if ($applyToExisting) {
            $this->applyToExistingInvoices($scholarship, $actor, 'highest_only');
        }
    }

    public function suspend(StudentScholarship $scholarship, ?User $actor = null): void
    {
        $old = $scholarship->only(['status']);
        $scholarship->forceFill(['status' => 'suspended'])->save();

        $this->audit->record(
            'scholarship_suspended',
            'Fees',
            "Scholarship {$scholarship->scholarship_name} suspended for {$scholarship->student?->full_name}",
            $scholarship,
            $old,
            ['status' => 'suspended']
        );
    }

    public function reactivate(StudentScholarship $scholarship, ?User $actor = null): void
    {
        $old = $scholarship->only(['status']);
        $scholarship->forceFill(['status' => 'active'])->save();

        $this->audit->record(
            'scholarship_reactivated',
            'Fees',
            "Scholarship {$scholarship->scholarship_name} reactivated for {$scholarship->student?->full_name}",
            $scholarship,
            $old,
            ['status' => 'active']
        );
    }

    public function renew(StudentScholarship $original, array $data, ?User $actor = null): StudentScholarship
    {
        $data = array_merge($original->toArray(), $data, [
            'status' => 'pending',
            'created_by' => $actor?->id,
        ]);

        return $this->create($data, $actor);
    }

    /**
     * Apply an active scholarship to all eligible unpaid fee rows for the student.
     * Fully paid rows are never altered.
     */
    public function applyToExistingInvoices(StudentScholarship $scholarship, ?User $actor = null, string $policy = 'highest_only'): int
    {
        $count = 0;

        StudentFeeAssignment::query()
            ->where('student_id', $scholarship->student_id)
            ->where('academic_session_id', $scholarship->academic_session_id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->each(function (StudentFeeAssignment $assignment) use ($scholarship, $actor, $policy, &$count) {
                $this->applyToExistingAssignment($scholarship, $assignment, $policy, $actor);
                $count++;
            });

        return $count;
    }

    public function expireOverdue(): int
    {
        return StudentScholarship::query()
            ->whereIn('status', ['active', 'approved', 'pending'])
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }

    private function validateStudentEnrolled(int $studentId, int $sessionId): void
    {
        $enrolled = \App\Models\Enrollment::query()
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->where('status', '!=', 'inactive')
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_id' => 'This student is not enrolled in the selected academic year.',
            ]);
        }
    }

    private function recalcAssignment(StudentFeeAssignment $assignment, ?float $payableFloor = null): void
    {
        $gross = (float) $assignment->amount;
        $net = max(0, round(
            $gross
            - (float) $assignment->discount_amount
            - (float) $assignment->scholarship_amount
            - (float) $assignment->waiver_amount,
            2
        ));

        if ($payableFloor !== null) {
            $net = max($net, min($payableFloor, max(0, $gross - (float) $assignment->discount_amount - (float) $assignment->waiver_amount)));
        }

        $assignment->forceFill(['net_amount' => $net])->save();

        $paid = (float) $assignment->activeAllocations()->sum('amount');
        $status = match (true) {
            $net <= 0 => 'waived',
            $paid <= 0 => 'pending',
            $paid + 0.001 >= $net => 'paid',
            default => 'partial',
        };

        $assignment->forceFill(['status' => $status])->save();
    }
}
