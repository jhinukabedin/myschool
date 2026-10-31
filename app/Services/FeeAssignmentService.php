<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeStructure;
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
        private readonly DocumentNumberService $numbers
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

                foreach ($this->periodsFor($item, $structure, $session) as $period) {
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

                    $net = max(0, (float) $item->amount - (float) $item->discount_amount);

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
     * @return array<int, array{period: ?string, due_date: string}>
     */
    private function periodsFor($item, FeeStructure $structure, AcademicSession $session): array
    {
        $effectiveStart = Carbon::parse($structure->effective_from)->startOfDay();
        $effectiveEnd = Carbon::parse($structure->effective_to ?? $session->end_date)->startOfDay();
        $sessionStart = Carbon::parse($session->start_date)->startOfDay();
        $sessionEnd = Carbon::parse($session->end_date)->startOfDay();

        $start = $effectiveStart->greaterThan($sessionStart) ? $effectiveStart : $sessionStart;
        $end = $effectiveEnd->lessThan($sessionEnd) ? $effectiveEnd : $sessionEnd;

        if ($start->greaterThan($end)) {
            return [];
        }

        if ($item->frequency !== 'monthly') {
            return [[
                'period' => null,
                'due_date' => $start->copy()->addDays(max(0, (int) $item->due_day - 1))->toDateString(),
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
            $newNet = round((float) $locked->net_amount - $amount, 2);
            $locked->update([
                'waiver_amount' => (float) $locked->waiver_amount + $amount,
                'net_amount' => $newNet,
                'note' => trim($locked->note.' | Waiver: '.$reason, ' |'),
            ]);
            $this->recalcStatus($locked);
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
