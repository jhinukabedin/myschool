<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeAssignmentService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly DocumentNumberService $numbers,
        private readonly ?ScholarshipService $scholarships = null
    ) {}

    /**
     * Assign every applicable fee item of a structure to one student.
     * Monthly items generate one row per month of the structure's effective range.
     *
     * @param  array<int>  $optionalItemIds
     * @return int number of assignment rows created
     */
    public function assignStructure(
        Student $student,
        FeeStructure $structure,
        AcademicSession $session,
        array $optionalItemIds = [],
        ?User $actor = null
    ): int {
        $structure->loadMissing('items');

        if ($structure->academic_session_id !== $session->id) {
            throw ValidationException::withMessages([
                'fee_structure_id' => 'The fee structure belongs to a different academic session.',
            ]);
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('class_id', $structure->class_id)
            ->where('status', '!=', 'inactive')
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'fee_structure_id' => 'This student is not enrolled in the selected class for this session.',
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($student, $structure, $session, $optionalItemIds, &$created) {
            foreach ($structure->items as $item) {
                if ($item->is_optional && ! in_array($item->id, $optionalItemIds, true)) {
                    continue;
                }

                foreach ($this->periodsFor($item, $structure, $session, $student->admission_date) as $period) {
                    $query = StudentFeeAssignment::query()
                        ->where('student_id', $student->id)
                        ->where('fee_structure_item_id', $item->id)
                        ->where('academic_session_id', $session->id);

                    $period === null
                        ? $query->whereNull('period')
                        : $query->where('period', $period['period']);

                    if ($query->exists()) {
                        continue;
                    }

                    $gross = (float) $item->amount;
                    $discount = (float) $item->discount_amount;
                    $base = max(0, round($gross - $discount, 2));

                    // The structure carries the scholarship template (percent or fixed) for this fee.
                    $templateScholarship = FeeStructureItem::concessionAmount(
                        (float) $item->scholarship_value,
                        (string) ($item->scholarship_type ?? 'fixed'),
                        $base
                    );

                    // Active student-level scholarships (merit, need-based, sibling, etc.)
                    // are layered on top for this student's row when the due date is
                    // inside their effective period. Generated from the date, so
                    // monthly rows for different periods resolve independently.
                    $studentScholarship = 0.0;
                    if ($this->scholarships) {
                        $dueDate = $period['due_date'];
                        $active = $this->scholarships->activeFor($student->id, (int) $session->id, $dueDate);

                        if ($active !== []) {
                            $draft = StudentFeeAssignment::make([
                                'student_id' => $student->id,
                                'academic_session_id' => $session->id,
                                'fee_category_id' => $item->fee_category_id,
                                'amount' => $item->amount,
                                'discount_amount' => $item->discount_amount,
                                'due_date' => $dueDate,
                            ]);
                            $draft->setRelation('student', $student);

                            $studentScholarship = $this->scholarships->combinedDiscountFor(
                                $draft,
                                $active,
                                'highest_only',
                                $dueDate
                            );
                        }
                    }

                    $scholarship = max($templateScholarship, $studentScholarship);

                    $net = max(0, round($base - $scholarship, 2));

                    StudentFeeAssignment::create([
                        'student_id' => $student->id,
                        'fee_structure_id' => $structure->id,
                        'fee_structure_item_id' => $item->id,
                        'fee_category_id' => $item->fee_category_id,
                        'academic_session_id' => $session->id,
                        'period' => $period['period'],
                        'amount' => $item->amount,
                        'discount_amount' => $item->discount_amount,
                        'waiver_amount' => 0,
                        'scholarship_type' => $scholarship > 0 ? ($item->scholarship_type ?? 'fixed') : null,
                        'scholarship_value' => $scholarship > 0 ? $item->scholarship_value : 0,
                        'scholarship_amount' => $scholarship,
                        'net_amount' => $net,
                        'due_date' => $period['due_date'],
                        'status' => $net <= 0 ? 'waived' : 'pending',
                        'note' => $structure->name,
                    ]);

                    $created++;
                }
            }

            $this->audit->record(
                'fee_structure_assigned',
                'Fees',
                "Assigned fee structure {$structure->name} to {$student->full_name}",
                $structure,
                null,
                ['student_id' => $student->id, 'assignments_created' => $created]
            );
        });

        return $created;
    }

    public function assignForClass(FeeStructure $structure, AcademicSession $session, ?User $actor = null): int
    {
        $total = 0;

        Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('class_id', $structure->class_id)
            ->where('status', 'active')
            ->with('student')
            ->chunkById(200, function ($enrollments) use ($structure, $session, $actor, &$total) {
                foreach ($enrollments as $enrollment) {
                    $total += $this->assignStructure($enrollment->student, $structure, $session, [], $actor);
                }
            });

        return $total;
    }

    /**
     * @param  \Carbon\CarbonInterface|null  $studentStart  Admission date; monthly
     *                                                      fees never start before it.
     * @return array<int, array{period: ?string, due_date: string}>
     */
    private function periodsFor($item, FeeStructure $structure, AcademicSession $session, $studentStart = null): array
    {
        $effectiveStart = Carbon::parse($structure->effective_from)->startOfDay();
        $effectiveEnd = Carbon::parse($structure->effective_to ?? $session->end_date)->startOfDay();
        $sessionStart = Carbon::parse($session->start_date)->startOfDay();
        $sessionEnd = Carbon::parse($session->end_date)->startOfDay();

        $start = $effectiveStart->greaterThan($sessionStart) ? $effectiveStart : $sessionStart;
        $end = $effectiveEnd->lessThan($sessionEnd) ? $effectiveEnd : $sessionEnd;

        // A student only owes fees from the day they joined, never before.
        if ($studentStart) {
            $admission = Carbon::parse($studentStart)->startOfDay();
            if ($admission->greaterThan($start)) {
                $start = $admission;
            }
        }

        if ($start->greaterThan($end)) {
            return [];
        }

        if ($item->frequency !== 'monthly') {
            $due = $start->copy()->addDays(max(0, (int) $item->due_day - 1));

            if ($studentStart) {
                $admission = Carbon::parse($studentStart)->startOfDay();
                if ($due->lessThan($admission)) {
                    $due = $admission->copy();
                }
            }

            return [[
                'period' => null,
                'due_date' => $due->toDateString(),
            ]];
        }

        $periods = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $dueDay = min(max(1, (int) $item->due_day), $cursor->daysInMonth);
            $periods[] = [
                'period' => $cursor->format('Y-m'),
                'due_date' => $cursor->copy()->day($dueDay)->toDateString(),
            ];
            $cursor->addMonthNoOverflow();
        }

        return $periods;
    }

    /**
     * Approve a waiver against an existing assignment. The recorded net amount
     * is reduced but the original amount remains for audit purposes.
     */
    public function applyWaiver(StudentFeeAssignment $assignment, float $amount, string $reason, User $actor): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['waiver_amount' => 'Waiver amount must be greater than zero.']);
        }

        DB::transaction(function () use ($assignment, $amount, $reason) {
            $locked = StudentFeeAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $paid = (float) $locked->activeAllocations()->sum('amount');
            $available = round((float) $locked->net_amount - $paid, 2);

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'waiver_amount' => 'Waiver cannot exceed the unpaid balance of '.money($available).'.',
                ]);
            }

            $old = $locked->only(['net_amount', 'waiver_amount', 'status']);
            $locked->forceFill([
                'waiver_amount' => (float) $locked->waiver_amount + $amount,
                'note' => trim($locked->note.' | Waiver: '.$reason, ' |'),
            ])->save();

            // net = gross − discount − scholarship − waiver
            $this->recalcNet($locked);
            $locked->refresh();

            $this->audit->record(
                'fee_waiver',
                'Fees',
                'Waiver of '.money($amount)." approved for {$locked->student?->full_name}: {$reason}",
                $locked,
                $old,
                $locked->only(['net_amount', 'waiver_amount', 'status'])
            );
        });
    }

    /**
     * Recalculate the payable net of a fee row:
     * gross − structure discount − scholarship concession − approved waiver.
     * Existing rows keep the same result because their scholarship is zero.
     */
    public function recalcNet(StudentFeeAssignment $assignment): float
    {
        $net = max(0, round(
            (float) $assignment->amount
            - (float) $assignment->discount_amount
            - (float) $assignment->scholarship_amount
            - (float) $assignment->waiver_amount,
            2
        ));

        $assignment->forceFill(['net_amount' => $net])->save();
        $this->recalcStatus($assignment);

        return $net;
    }

    /**
     * Grant a scholarship against one student fee row, either as a percentage of the
     * payable amount or as a fixed concession. A value of zero removes it again.
     */
    public function applyScholarship(StudentFeeAssignment $assignment, string $type, float $value, User $actor): void
    {
        if (! in_array($type, ['percent', 'fixed'], true)) {
            throw ValidationException::withMessages(['scholarship_type' => 'Choose percentage or fixed scholarship.']);
        }

        if ($value < 0) {
            throw ValidationException::withMessages(['scholarship_value' => 'Scholarship cannot be negative.']);
        }

        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages(['scholarship_value' => 'A percentage scholarship cannot exceed 100%.']);
        }

        DB::transaction(function () use ($assignment, $type, $value, $actor) {
            $locked = StudentFeeAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $base = max(0, round((float) $locked->amount - (float) $locked->discount_amount, 2));
            $ceiling = max(0, round($base - (float) $locked->waiver_amount, 2));
            $conceded = $value <= 0 ? 0.0 : min(FeeStructureItem::concessionAmount($value, $type, $base), $ceiling);

            $old = $locked->only(['scholarship_type', 'scholarship_value', 'scholarship_amount', 'net_amount', 'status']);

            $locked->forceFill([
                'scholarship_type' => $conceded > 0 ? $type : null,
                'scholarship_value' => $conceded > 0 ? $value : 0,
                'scholarship_amount' => $conceded,
            ])->save();

            $this->recalcNet($locked);
            $locked->refresh();

            $label = $type === 'percent' ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%' : money($value);

            $this->audit->record(
                'fee_scholarship',
                'Fees',
                ($conceded > 0 ? 'Scholarship '.$label.' ('.money($conceded).') ' : 'Scholarship removed ')
                    ."on {$locked->category?->name} [".($locked->period ?? 'one-time')."] for {$locked->student?->full_name}",
                $locked,
                $old,
                $locked->only(['scholarship_type', 'scholarship_value', 'scholarship_amount', 'net_amount', 'status']),
            );
        });
    }

    public function recalcStatus(StudentFeeAssignment $assignment): string
    {
        $paid = (float) $assignment->activeAllocations()->sum('amount');
        $net = (float) $assignment->net_amount;

        $status = match (true) {
            $net <= 0 => 'waived',
            $paid <= 0 => 'pending',
            $paid + 0.001 >= $net => 'paid',
            default => 'partial',
        };

        $assignment->forceFill(['status' => $status])->save();

        return $status;
    }
}
