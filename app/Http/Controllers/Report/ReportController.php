<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Payroll;
use App\Models\SalaryPayment;
use App\Models\SchoolClass;
use App\Models\StudentFeeAssignment;
use App\Services\ExamResultService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const REPORTS = [
        'collection' => ['title' => 'Fee Collection Report', 'description' => 'Receipts and collected fees for the selected period.', 'icon' => 'receipt'],
        'income-expense' => ['title' => 'Income vs Expense', 'description' => 'Monthly fee income against operating expenses and paid salaries.', 'icon' => 'trending-down'],
        'dues' => ['title' => 'Outstanding Dues', 'description' => 'Students with unpaid fee balances, oldest dues first.', 'icon' => 'wallet'],
        'partial' => ['title' => 'Partial Payment Students', 'description' => 'Students whose fee rows are partly paid, with paid and outstanding amounts.', 'icon' => 'circle-dollar'],
        'category-collection' => ['title' => 'Category-wise Collection', 'description' => 'Completed fee collections grouped by fee category for the selected period.', 'icon' => 'list-checks'],
        'collectors' => ['title' => 'Collector & Receipt Report', 'description' => 'Receipts grouped by the staff member who collected them, including reversed totals.', 'icon' => 'receipt'],
        'expenses' => ['title' => 'Expense Report', 'description' => 'Expenses grouped by category with totals.', 'icon' => 'credit-card'],
        'attendance' => ['title' => 'Attendance Summary', 'description' => 'Attendance rates by class for the selected period.', 'icon' => 'calendar-check'],
        'results' => ['title' => 'Exam Performance', 'description' => 'Class-level pass rate, GPA and grade distribution.', 'icon' => 'graduation-cap'],
        'students' => ['title' => 'Student Summary', 'description' => 'Enrolment by class and gender for the session.', 'icon' => 'users'],
        'payroll' => ['title' => 'Payroll Summary', 'description' => 'Monthly salary obligations and payments.', 'icon' => 'banknote'],
    ];

    public function __construct(private readonly ExamResultService $results) {}

    public function index(): View
    {
        return view('reports.index', [
            'reports' => self::REPORTS,
            'session' => current_session(),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);
        $data = $this->build($request, $report);

        return view('reports.show', $data + [
            'reportKey' => $report,
            'definitions' => self::REPORTS,
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);
        $data = $this->build($request, $report);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['EduSphere School - '.$data['title']]);
            fputcsv($out, ['Session', $data['session']?->name, 'Period', $data['period']]);
            fputcsv($out, []);
            fputcsv($out, $data['columns']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, 'report-'.$data['key'].'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function build(Request $request, string $key): array
    {
        $session = current_session();
        $today = now();
        $from = $request->input('from', $session?->start_date?->toDateString() ?? $today->copy()->startOfYear()->toDateString());
        $to = $request->input('to', $session?->end_date?->toDateString() ?? $today->toDateString());
        $meta = self::REPORTS[$key];

        $data = [
            'key' => $key,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'session' => $session,
            'from' => $from,
            'to' => $to,
            'period' => Carbon::parse($from)->format('d M Y').' – '.Carbon::parse($to)->format('d M Y'),
            'summary' => [],
            'columns' => [],
            'rows' => [],
            'chart' => null,
            'exams' => Examination::query()->when($session, fn ($q) => $q->where('academic_session_id', $session->id))->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::ordered()->get(),
        ];

        return match ($key) {
            'collection' => $this->collectionReport($data, $session, $from, $to),
            'income-expense' => $this->incomeExpenseReport($data, $session, $from, $to),
            'dues' => $this->duesReport($data, $session),
            'partial' => $this->partialReport($data, $session),
            'category-collection' => $this->categoryCollectionReport($data, $session, $from, $to),
            'collectors' => $this->collectorReport($data, $session, $from, $to),
            'expenses' => $this->expenseReport($data, $session, $from, $to),
            'attendance' => $this->attendanceReport($data, $session, $from, $to),
            'results' => $this->resultsReport($data, $request, $session),
            'students' => $this->studentsReport($data, $session),
            'payroll' => $this->payrollReport($data, $session, $from, $to),
        };
    }

    private function collectionReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $payments = Payment::query()
            ->with(['student', 'receivedBy'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('paid_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderByDesc('paid_at')
            ->get();

        $data['summary'] = [
            ['label' => 'Completed receipts', 'value' => $payments->where('status', 'completed')->count()],
            ['label' => 'Collected', 'value' => money($payments->where('status', 'completed')->sum('amount'))],
            ['label' => 'Collected today', 'value' => money($payments->where('status', 'completed')->filter(fn ($p) => $p->paid_at?->isToday())->sum('amount'))],
            ['label' => 'Reversed', 'value' => $payments->where('status', 'reversed')->count().' · '.money($payments->where('status', 'reversed')->sum('amount'))],
        ];
        $data['columns'] = ['Receipt', 'Date', 'Student', 'Method', 'Amount', 'Status', 'Received by'];
        $data['rows'] = $payments->map(fn (Payment $payment) => [
            'receipt' => $payment->receipt_no,
            'date' => $payment->paid_at->format('d M Y'),
            'student' => $payment->student?->full_name,
            'method' => $payment->method_label,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => ucfirst($payment->status),
            'received_by' => $payment->receivedBy?->name ?? '—',
        ])->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => ['Collected', 'Reversed'],
            'values' => [
                (float) $payments->where('status', 'completed')->sum('amount'),
                (float) $payments->where('status', 'reversed')->sum('amount'),
            ],
        ];

        return $data;
    }

    private function incomeExpenseReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();
        $labels = [];
        $income = [];
        $expenses = [];
        $salaries = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $income[] = (float) Payment::query()
                ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
                ->where('status', 'completed')
                ->whereBetween('paid_at', [$cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth()])
                ->sum('amount');
            $expenses[] = (float) Expense::query()
                ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
                ->whereBetween('expense_date', [$cursor->copy()->startOfMonth()->toDateString(), $cursor->copy()->endOfMonth()->toDateString()])
                ->sum('amount');
            $salaries[] = (float) SalaryPayment::query()
                ->where('status', 'completed')
                ->whereHas('payroll', fn ($q) => $q
                    ->when($session, fn ($q2) => $q2->where('academic_session_id', $session->id))
                    ->where('month', $key))
                ->sum('amount');
            $cursor->addMonthNoOverflow();
        }

        $totalIncome = array_sum($income);
        $totalExpenses = array_sum($expenses);
        $totalSalaries = array_sum($salaries);

        $data['summary'] = [
            ['label' => 'Fee income', 'value' => money($totalIncome)],
            ['label' => 'Operating expenses', 'value' => money($totalExpenses)],
            ['label' => 'Salaries paid', 'value' => money($totalSalaries)],
            ['label' => 'Net position', 'value' => money($totalIncome - $totalExpenses - $totalSalaries)],
        ];
        $data['columns'] = ['Month', 'Fee income', 'Operating expenses', 'Salaries paid', 'Net'];
        $data['rows'] = collect($labels)->map(fn ($label, $index) => [
            'month' => $label,
            'income' => number_format($income[$index], 2, '.', ''),
            'expenses' => number_format($expenses[$index], 2, '.', ''),
            'salaries' => number_format($salaries[$index], 2, '.', ''),
            'net' => number_format($income[$index] - $expenses[$index] - $salaries[$index], 2, '.', ''),
        ])->all();
        $data['chart'] = ['type' => 'line', 'labels' => $labels, 'datasets' => [
            ['label' => 'Fee income', 'values' => $income, 'color' => '#34d399'],
            ['label' => 'Operating expenses', 'values' => $expenses, 'color' => '#fb7185'],
            ['label' => 'Salaries paid', 'values' => $salaries, 'color' => '#fbbf24'],
        ]];

        return $data;
    }

    private function duesReport(array $data, ?AcademicSession $session): array
    {
        $assignments = StudentFeeAssignment::query()
            ->with(['student.enrollments.class', 'student.enrollments.section', 'category'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereIn('status', ['pending', 'partial'])
            ->withSum(['paymentAllocations as paid_total' => fn ($q) => $q
                ->whereHas('payment', fn ($p) => $p->where('status', 'completed'))], 'amount')
            ->orderBy('due_date')
            ->get();

        $grouped = $assignments->groupBy('student_id')->map(function ($rows) use ($session) {
            $first = $rows->first();
            $enrollment = $first->student?->enrollments?->firstWhere('academic_session_id', $session?->id);

            return [
                'student' => $first->student?->full_name,
                'admission_no' => $first->student?->admission_no,
                'class' => $enrollment?->class?->name,
                'due' => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2),
                'oldest_due' => $rows->min('due_date')?->format('d M Y'),
                'items' => $rows->count(),
            ];
        })->sortByDesc('due')->values();

        $data['summary'] = [
            ['label' => 'Students with dues', 'value' => $grouped->count()],
            ['label' => 'Total outstanding', 'value' => money($grouped->sum('due'))],
            ['label' => 'Overdue fee rows', 'value' => $assignments->where('due_date', '<', now()->toDateString())->count()],
        ];
        $data['columns'] = ['Student', 'Admission No', 'Class', 'Fee rows', 'Oldest due', 'Outstanding'];
        $data['rows'] = $grouped->map(fn ($row) => [
            'student' => $row['student'],
            'admission_no' => $row['admission_no'],
            'class' => $row['class'],
            'items' => $row['items'],
            'oldest_due' => $row['oldest_due'],
            'outstanding' => number_format($row['due'], 2, '.', ''),
        ])->all();

        return $data;
    }

    private function partialReport(array $data, ?AcademicSession $session): array
    {
        $assignments = StudentFeeAssignment::query()
            ->with(['student.enrollments.class', 'category'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->where('status', 'partial')
            ->orderBy('due_date')
            ->get();

        $grouped = $assignments->groupBy('student_id')->map(function ($rows) use ($session) {
            $first = $rows->first();
            $enrollment = $first->student?->enrollments?->firstWhere('academic_session_id', $session?->id);

            return [
                'student' => $first->student?->full_name,
                'admission_no' => $first->student?->admission_no,
                'class' => $enrollment?->class?->name,
                'items' => $rows->count(),
                'paid' => round($rows->sum(fn ($row) => (float) $row->paid_amount), 2),
                'outstanding' => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2),
                'oldest_due' => $rows->min('due_date')?->format('d M Y'),
            ];
        })->sortByDesc('outstanding')->values();

        $data['summary'] = [
            ['label' => 'Students with partial payments', 'value' => $grouped->count()],
            ['label' => 'Partly paid fee rows', 'value' => $assignments->count()],
            ['label' => 'Paid so far', 'value' => money($grouped->sum('paid'))],
            ['label' => 'Outstanding', 'value' => money($grouped->sum('outstanding'))],
        ];
        $data['columns'] = ['Student', 'Admission No', 'Class', 'Fee rows', 'Paid', 'Outstanding', 'Oldest due'];
        $data['rows'] = $grouped->map(fn ($row) => [
            'student' => $row['student'],
            'admission_no' => $row['admission_no'],
            'class' => $row['class'],
            'items' => $row['items'],
            'paid' => number_format($row['paid'], 2, '.', ''),
            'outstanding' => number_format($row['outstanding'], 2, '.', ''),
            'oldest_due' => $row['oldest_due'],
        ])->all();

        return $data;
    }

    private function categoryCollectionReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $allocations = PaymentAllocation::query()
            ->with(['assignment.category'])
            ->whereHas('payment', fn ($q) => $q
                ->where('status', 'completed')
                ->when($session, fn ($q2) => $q2->where('academic_session_id', $session->id))
                ->whereBetween('paid_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()]))
            ->get();

        $grouped = $allocations
            ->groupBy(fn (PaymentAllocation $allocation) => $allocation->assignment?->category?->name ?? 'Uncategorised')
            ->map(fn ($group, $name) => [
                'category' => $name,
                'receipts' => $group->pluck('payment_id')->unique()->count(),
                'allocated' => round($group->sum(fn ($row) => (float) $row->amount), 2),
            ])
            ->sortByDesc('allocated')
            ->values();

        $data['summary'] = [
            ['label' => 'Fee categories', 'value' => $grouped->count()],
            ['label' => 'Receipts', 'value' => $allocations->pluck('payment_id')->unique()->count()],
            ['label' => 'Allocated collection', 'value' => money($grouped->sum('allocated'))],
        ];
        $data['columns'] = ['Fee category', 'Receipts', 'Allocated amount'];
        $data['rows'] = $grouped->map(fn ($row) => [
            'category' => $row['category'],
            'receipts' => $row['receipts'],
            'allocated' => number_format($row['allocated'], 2, '.', ''),
        ])->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => $grouped->pluck('category')->all(),
            'values' => $grouped->pluck('allocated')->all(),
        ];

        return $data;
    }

    private function collectorReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $payments = Payment::query()
            ->with('receivedBy')
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('paid_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->get();

        $grouped = $payments
            ->groupBy(fn (Payment $payment) => $payment->received_by ?? 0)
            ->map(function ($rows) {
                return [
                    'collector' => $rows->first()->receivedBy?->name ?? 'System / online',
                    'receipts' => $rows->where('status', 'completed')->count(),
                    'collected' => round($rows->where('status', 'completed')->sum('amount'), 2),
                    'reversed' => $rows->where('status', 'reversed')->count(),
                    'reversed_amount' => round($rows->where('status', 'reversed')->sum('amount'), 2),
                    'last' => $rows->sortByDesc('paid_at')->first()?->paid_at?->format('d M Y'),
                ];
            })
            ->sortByDesc('collected')
            ->values();

        $data['summary'] = [
            ['label' => 'Collectors', 'value' => $grouped->count()],
            ['label' => 'Collected', 'value' => money($grouped->sum('collected'))],
            ['label' => 'Reversed amount', 'value' => money($grouped->sum('reversed_amount'))],
        ];
        $data['columns'] = ['Collector', 'Completed receipts', 'Collected', 'Reversed receipts', 'Reversed amount', 'Last receipt'];
        $data['rows'] = $grouped->map(fn ($row) => [
            'collector' => $row['collector'],
            'receipts' => $row['receipts'],
            'collected' => number_format($row['collected'], 2, '.', ''),
            'reversed' => $row['reversed'],
            'reversed_amount' => number_format($row['reversed_amount'], 2, '.', ''),
            'last' => $row['last'],
        ])->all();

        return $data;
    }

    private function expenseReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $rows = Expense::query()
            ->with('category')
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('expense_category_id, COUNT(*) as entries, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->get()
            ->sortByDesc('total');

        $data['summary'] = [
            ['label' => 'Categories', 'value' => $rows->count()],
            ['label' => 'Expense entries', 'value' => $rows->sum('entries')],
            ['label' => 'Total expenses', 'value' => money($rows->sum('total'))],
        ];
        $data['columns'] = ['Category', 'Entries', 'Total'];
        $data['rows'] = $rows->map(fn ($row) => [
            'category' => $row->category?->name,
            'entries' => (int) $row->entries,
            'total' => number_format((float) $row->total, 2, '.', ''),
        ])->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => $rows->map(fn ($row) => $row->category?->name)->all(),
            'values' => $rows->map(fn ($row) => (float) $row->total)->all(),
        ];

        return $data;
    }

    private function attendanceReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $rows = DB::table('attendance_records')
            ->join('classes', 'classes.id', '=', 'attendance_records.class_id')
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('attendance_date', [$from, $to])
            ->selectRaw('classes.name as class, attendance_records.status, COUNT(*) as total')
            ->groupBy('classes.name', 'attendance_records.status')
            ->get()
            ->groupBy('class')
            ->map(function ($group) {
                $present = (int) $group->where('status', 'present')->sum('total');
                $late = (int) $group->where('status', 'late')->sum('total');
                $absent = (int) $group->where('status', 'absent')->sum('total');
                $excused = (int) $group->where('status', 'excused')->sum('total');
                $total = $present + $late + $absent + $excused;

                return [
                    'class' => $group->first()->class,
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'excused' => $excused,
                    'total' => $total,
                    'percentage' => $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0,
                ];
            })->values();

        $data['summary'] = [
            ['label' => 'Records', 'value' => $rows->sum('total')],
            ['label' => 'Present + late', 'value' => $rows->sum('present') + $rows->sum('late')],
            ['label' => 'Absent', 'value' => $rows->sum('absent')],
            ['label' => 'Average attendance', 'value' => $rows->count() ? round($rows->avg('percentage'), 1).'%' : '0%'],
        ];
        $data['columns'] = ['Class', 'Present', 'Late', 'Absent', 'Excused', 'Total', 'Attendance %'];
        $data['rows'] = $rows->map(fn ($row) => [
            'class' => $row['class'], 'present' => $row['present'], 'late' => $row['late'],
            'absent' => $row['absent'], 'excused' => $row['excused'], 'total' => $row['total'],
            'percentage' => $row['percentage'].'%',
        ])->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => $rows->pluck('class')->all(),
            'values' => $rows->pluck('percentage')->map(fn ($v) => (float) $v)->all(),
        ];

        return $data;
    }

    private function resultsReport(array $data, Request $request, ?AcademicSession $session): array
    {
        $exams = $data['exams'];
        $exam = $request->filled('exam_id') ? $exams->firstWhere('id', $request->integer('exam_id')) : $exams->first();
        $classId = $request->filled('class_id') ? $request->integer('class_id') : null;
        $rows = collect();

        if ($exam) {
            $exam->load('subjects.subject');
            $classes = $data['classes']->when($classId, fn ($c) => $c->where('id', $classId));

            foreach ($classes as $class) {
                if ($exam->subjects->where('class_id', $class->id)->isEmpty()) {
                    continue;
                }

                $results = $this->results->meritList($exam, $class);
                $rows->push([
                    'class' => $class->name,
                    'students' => $results->count(),
                    'pass' => $results->where('result', 'Pass')->count(),
                    'fail' => $results->where('result', 'Fail')->count(),
                    'pass_rate' => $results->count() ? round(($results->where('result', 'Pass')->count() / $results->count()) * 100, 1) : 0,
                    'average_gpa' => $results->count() ? round($results->avg('gpa'), 2) : 0,
                    'top_gpa' => $results->max('gpa') ?? 0,
                ]);
            }
        }

        $data['exam'] = $exam;
        $data['summary'] = [
            ['label' => 'Examination', 'value' => $exam?->name ?? 'No examination'],
            ['label' => 'Classes assessed', 'value' => $rows->count()],
            ['label' => 'Pass rate', 'value' => $rows->count() ? round($rows->avg('pass_rate'), 1).'%' : '0%'],
            ['label' => 'Average GPA', 'value' => $rows->count() ? round($rows->avg('average_gpa'), 2) : '0.00'],
        ];
        $data['columns'] = ['Class', 'Students', 'Pass', 'Fail', 'Pass rate', 'Average GPA', 'Top GPA'];
        $data['rows'] = $rows->map(fn ($row) => [
            'class' => $row['class'], 'students' => $row['students'], 'pass' => $row['pass'], 'fail' => $row['fail'],
            'pass_rate' => $row['pass_rate'].'%', 'average_gpa' => $row['average_gpa'], 'top_gpa' => $row['top_gpa'],
        ])->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => $rows->pluck('class')->all(),
            'values' => $rows->pluck('pass_rate')->map(fn ($v) => (float) $v)->all(),
        ];

        return $data;
    }

    private function studentsReport(array $data, ?AcademicSession $session): array
    {
        $enrollments = Enrollment::query()
            ->with(['student', 'class'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->where('status', 'active')
            ->get();

        $rows = $enrollments->groupBy('class_id')->map(function ($group) {
            return [
                'class' => $group->first()->class?->name,
                'students' => $group->count(),
                'male' => $group->filter(fn ($row) => $row->student?->gender === 'male')->count(),
                'female' => $group->filter(fn ($row) => $row->student?->gender === 'female')->count(),
                'other' => $group->filter(fn ($row) => ! in_array($row->student?->gender, ['male', 'female'], true))->count(),
            ];
        })->sortBy('class')->values();

        $data['summary'] = [
            ['label' => 'Active students', 'value' => $enrollments->count()],
            ['label' => 'Male', 'value' => $rows->sum('male')],
            ['label' => 'Female', 'value' => $rows->sum('female')],
            ['label' => 'Classes', 'value' => $rows->count()],
        ];
        $data['columns'] = ['Class', 'Students', 'Male', 'Female', 'Other'];
        $data['rows'] = $rows->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => $rows->pluck('class')->all(),
            'values' => $rows->pluck('students')->map(fn ($v) => (int) $v)->all(),
        ];

        return $data;
    }

    private function payrollReport(array $data, ?AcademicSession $session, string $from, string $to): array
    {
        $payrolls = Payroll::query()
            ->with(['staff', 'completedPayments'])
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->whereBetween('month', [Carbon::parse($from)->format('Y-m'), Carbon::parse($to)->format('Y-m')])
            ->get();

        $rows = $payrolls->groupBy('month')->sortKeys()->map(function ($group) {
            return [
                'month' => $group->first()->month,
                'staff' => $group->count(),
                'gross' => number_format((float) $group->sum(fn ($p) => (float) $p->basic_salary + (float) $p->total_allowances), 2, '.', ''),
                'deductions' => number_format((float) $group->sum('total_deductions'), 2, '.', ''),
                'net' => number_format((float) $group->sum('net_salary'), 2, '.', ''),
                'paid' => number_format($group->sum(fn (Payroll $p) => $p->paidAmount()), 2, '.', ''),
            ];
        })->values();

        $totalNet = (float) $payrolls->sum('net_salary');
        $totalPaid = round($payrolls->sum(fn (Payroll $p) => $p->paidAmount()), 2);

        $data['summary'] = [
            ['label' => 'Payroll rows', 'value' => $payrolls->count()],
            ['label' => 'Net payroll', 'value' => money($totalNet)],
            ['label' => 'Paid', 'value' => money($totalPaid)],
            ['label' => 'Outstanding', 'value' => money(max(0, $totalNet - $totalPaid))],
        ];
        $data['columns'] = ['Month', 'Staff', 'Gross', 'Deductions', 'Net', 'Paid'];
        $data['rows'] = $rows->all();
        $data['chart'] = [
            'type' => 'bar',
            'labels' => $rows->pluck('month')->all(),
            'values' => $rows->map(fn ($row) => (float) $row['net'])->all(),
        ];

        return $data;
    }
}
