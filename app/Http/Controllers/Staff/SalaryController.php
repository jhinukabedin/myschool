<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\SalaryPayment;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryController extends Controller
{
    public function __construct(private readonly PayrollService $payrolls) {}

    public function index(Request $request): View
    {
        $session = current_session();
        $month = $request->input('month', now()->format('Y-m'));

        $payrolls = Payroll::query()
            ->with(['staff.department', 'staff.designation', 'completedPayments', 'payments', 'processedBy'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->when($month, fn ($query) => $query->where('month', $month))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('staff_id')
            ->paginate(20)
            ->withQueryString();

        $monthRows = Payroll::query()
            ->with('completedPayments')
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->where('month', $month)
            ->where('status', '!=', 'cancelled')
            ->get();

        $paid = round($monthRows->sum(fn (Payroll $payroll) => $payroll->paidAmount()), 2);

        return view('payroll.index', [
            'payrolls' => $payrolls,
            'session' => $session,
            'month' => $month,
            'summary' => [
                'net' => round((float) $monthRows->sum('net_salary'), 2),
                'paid' => $paid,
                'outstanding' => round(max(0, (float) $monthRows->sum('net_salary') - $paid), 2),
                'pending' => $monthRows->where('status', 'pending')->count(),
                'processed' => $monthRows->where('status', 'processed')->count(),
                'partial' => $monthRows->filter(fn (Payroll $payroll) => $payroll->isPartiallyPaid())->count(),
            ],
            'months' => Payroll::query()->distinct()->orderByDesc('month')->pluck('month'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $session = current_session();
        abort_unless($session, 404);

        $created = $this->payrolls->generate($session, $data['month'], $request->user());

        return redirect()->route('payroll.index', ['month' => $data['month']])
            ->with('success', $created > 0
                ? "{$created} payroll row(s) generated. Existing staff payrolls were left untouched."
                : 'No new payroll rows were generated. Payroll may already exist for this month, or staff have no salary structure.');
    }

    public function process(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->payrolls->process($payroll, $request->user());

        return back()->with('success', 'Payroll processed and ready for payment.');
    }

    public function cancel(Request $request, Payroll $payroll): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $this->payrolls->cancel($payroll, $data['reason'], $request->user());

        return back()->with('success', 'Payroll cancelled. The record is retained for audit and excluded from payable totals.');
    }

    public function pay(Request $request, Payroll $payroll): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank,mobile_banking,cheque,card'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->payrolls->pay($payroll, $data, $request->user());

        return back()->with('success', 'Salary installment recorded.');
    }

    public function reverse(Request $request, Payroll $payroll): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $this->payrolls->reverse($payroll, $data['reason'], $request->user());

        return back()->with('success', 'Salary payment reversed. The original payment is retained in the audit trail and the payroll is ready to be paid again.');
    }

    public function reversePayment(Request $request, SalaryPayment $salaryPayment): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $this->payrolls->reversePayment($salaryPayment, $data['reason'], $request->user());

        return back()->with('success', 'Salary installment reversed. The original payment is retained in the audit trail.');
    }

    public function slip(Payroll $payroll): View
    {
        $payroll->load([
            'staff.department', 'staff.designation', 'items', 'payment.paidBy',
            'payments.reversals.reversedBy', 'payments.paidBy', 'processedBy', 'academicSession',
        ]);

        return view('payroll.slip', ['payroll' => $payroll]);
    }

    public function report(Request $request): View
    {
        return view('payroll.report', $this->reportData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->reportData($request);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['EduSphere School - Salary Report', $data['year']]);
            fputcsv($out, []);
            fputcsv($out, ['Staff', 'Employee No', 'Month', 'Basic', 'Allowances', 'Deductions', 'Net', 'Paid', 'Outstanding', 'Status']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['staff'], $row['employee_no'], $row['month'], $row['basic'], $row['allowances'],
                    $row['deductions'], $row['net'], $row['paid'], $row['outstanding'], $row['status'],
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Total net', number_format($data['totalNet'], 2, '.', '')]);
            fputcsv($out, ['Total paid', number_format($data['totalPaid'], 2, '.', '')]);
            fputcsv($out, ['Total outstanding', number_format($data['totalOutstanding'], 2, '.', '')]);
            fclose($out);
        }, 'salary-report-'.$data['year'].'.csv', ['Content-Type' => 'text/csv']);
    }

    private function reportData(Request $request): array
    {
        $session = current_session();
        $year = $request->input('year', now()->format('Y'));

        $payrolls = Payroll::query()
            ->with(['staff', 'completedPayments'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->where('month', 'like', $year.'-%')
            ->where('status', '!=', 'cancelled')
            ->orderBy('month')
            ->orderBy('staff_id')
            ->get();

        $rows = $payrolls->map(fn (Payroll $payroll) => [
            'staff' => $payroll->staff?->full_name,
            'employee_no' => $payroll->staff?->employee_no,
            'month' => $payroll->month,
            'basic' => number_format((float) $payroll->basic_salary, 2, '.', ''),
            'allowances' => number_format((float) $payroll->total_allowances, 2, '.', ''),
            'deductions' => number_format((float) $payroll->total_deductions, 2, '.', ''),
            'net' => number_format((float) $payroll->net_salary, 2, '.', ''),
            'paid' => number_format($payroll->paidAmount(), 2, '.', ''),
            'outstanding' => number_format($payroll->outstandingAmount(), 2, '.', ''),
            'status' => ucfirst(str_replace('_', ' ', $payroll->status)),
        ])->all();

        $byMonth = $payrolls->groupBy('month')->map(function ($group) {
            $paid = round($group->sum(fn (Payroll $payroll) => $payroll->paidAmount()), 2);
            $net = (float) $group->sum('net_salary');

            return [
                'month' => $group->first()->month,
                'staff' => $group->count(),
                'net' => $net,
                'paid' => $paid,
                'outstanding' => round(max(0, $net - $paid), 2),
            ];
        })->values();

        $totalNet = (float) $payrolls->sum('net_salary');
        $totalPaid = round($payrolls->sum(fn (Payroll $payroll) => $payroll->paidAmount()), 2);

        return [
            'session' => $session,
            'year' => $year,
            'rows' => $rows,
            'byMonth' => $byMonth,
            'totalNet' => $totalNet,
            'totalPaid' => $totalPaid,
            'totalOutstanding' => round(max(0, $totalNet - $totalPaid), 2),
            'staffCount' => $payrolls->pluck('staff_id')->unique()->count(),
        ];
    }
}
