<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(?AcademicSession $session): array
    {
        if (! $session) {
            return $this->emptyMetrics();
        }

        $activeStudents = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count('student_id');

        $collected = (float) Payment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'completed')
            ->sum('amount');

        $pending = $this->pendingFees($session);

        $expenses = (float) Expense::query()
            ->where('academic_session_id', $session->id)
            ->sum('amount');

        $collectedThisMonth = (float) Payment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $expensesThisMonth = (float) Expense::query()
            ->where('academic_session_id', $session->id)
            ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $newAdmissions = Student::query()
            ->whereBetween('admission_date', [$session->start_date, $session->end_date])
            ->count();

        $overdueCount = StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->count();

        return [
            'session' => $session,
            'active_students' => $activeStudents,
            'collected' => $collected,
            'pending' => $pending,
            'expenses' => $expenses,
            'collected_this_month' => $collectedThisMonth,
            'expenses_this_month' => $expensesThisMonth,
            'new_admissions' => $newAdmissions,
            'overdue_count' => $overdueCount,
            'collection_chart' => $this->collectionChart($session),
            'students_by_class' => $this->studentsByClass($session),
            'defaulters' => $this->topDefaulters($session),
            'recent_payments' => Payment::query()
                ->with(['student', 'receivedBy'])
                ->where('academic_session_id', $session->id)
                ->where('status', 'completed')
                ->latest('paid_at')
                ->limit(6)
                ->get(),
            'notices' => Notice::query()
                ->published()
                ->latest('publish_at')
                ->limit(4)
                ->get(),
            'attendance_today' => $this->attendanceToday($session),
        ];
    }

    public function pendingFees(AcademicSession $session): float
    {
        $horizon = \App\Support\FeeWindow::horizonDate();

        $net = (float) StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            // Current billing window only; far-future invoices are not due yet.
            ->where(fn ($query) => $query
                ->whereNull('due_date')
                ->orWhere('due_date', '<=', $horizon))
            ->sum('net_amount');

        $paid = (float) PaymentAllocation::query()
            ->whereHas('assignment', fn ($query) => $query
                ->where('academic_session_id', $session->id)
                ->whereIn('status', ['pending', 'partial'])
                ->where(fn ($inner) => $inner
                    ->whereNull('due_date')
                    ->orWhere('due_date', '<=', $horizon)))
            ->whereHas('payment', fn ($query) => $query->where('status', 'completed'))
            ->sum('amount');

        return max(0, round($net - $paid, 2));
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function collectionChart(AcademicSession $session): array
    {
        $start = $session->start_date->copy()->startOfMonth();
        $end = $session->end_date->copy()->startOfMonth();

        $payments = Payment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$start, $session->end_date->copy()->endOfDay()])
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('Y-m'));

        $labels = [];
        $values = [];
        $cursor = $start->copy();
        $months = 0;

        while ($cursor->lessThanOrEqualTo($end) && $months < 12) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $values[] = round((float) $payments->get($key, collect())->sum('amount'), 2);
            $cursor->addMonthNoOverflow();
            $months++;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function studentsByClass(AcademicSession $session): array
    {
        $counts = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->select('class_id', DB::raw('COUNT(DISTINCT student_id) as total'))
            ->groupBy('class_id')
            ->pluck('total', 'class_id');

        $classes = SchoolClass::query()->ordered()->get(['id', 'name']);

        return [
            'labels' => $classes->map(fn ($class) => $class->name)->all(),
            'values' => $classes->map(fn ($class) => (int) ($counts[$class->id] ?? 0))->all(),
        ];
    }

    /**
     * @return Collection<int, array{student: Student, due: float}>
     */
    private function topDefaulters(AcademicSession $session): Collection
    {
        $assignments = StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->where(fn ($query) => $query
                ->whereNull('due_date')
                ->orWhere('due_date', '<=', \App\Support\FeeWindow::horizonDate()))
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get(['student_id', 'net_amount']);

        $dues = $assignments
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)))
            ->filter(fn ($due) => $due > 0)
            ->sortDesc()
            ->take(5);

        $students = Student::query()
            ->whereIn('id', $dues->keys())
            ->with(['enrollments' => fn ($query) => $query
                ->where('academic_session_id', $session->id)
                ->with(['class', 'section'])])
            ->get()
            ->keyBy('id');

        return $dues
            ->map(fn ($due, $studentId) => [
                'student' => $students->get($studentId),
                'due' => round((float) $due, 2),
            ])
            ->filter(fn ($row) => $row['student'])
            ->values();
    }

    private function attendanceToday(AcademicSession $session): array
    {
        $records = AttendanceRecord::query()
            ->where('academic_session_id', $session->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($records['present'] ?? 0) + (int) ($records['late'] ?? 0);
        $total = (int) $records->sum();

        return [
            'present' => $present,
            'absent' => (int) ($records['absent'] ?? 0),
            'marked' => $total,
            'percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            'session' => null,
            'active_students' => 0,
            'collected' => 0,
            'pending' => 0,
            'expenses' => 0,
            'collected_this_month' => 0,
            'expenses_this_month' => 0,
            'new_admissions' => 0,
            'overdue_count' => 0,
            'collection_chart' => ['labels' => [], 'values' => []],
            'students_by_class' => ['labels' => [], 'values' => []],
            'defaulters' => collect(),
            'recent_payments' => collect(),
            'notices' => collect(),
            'attendance_today' => ['present' => 0, 'absent' => 0, 'marked' => 0, 'percentage' => 0],
        ];
    }
}
