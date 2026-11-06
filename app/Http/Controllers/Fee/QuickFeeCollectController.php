<?php

namespace App\Http\Controllers\Fee;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickPaymentRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class QuickFeeCollectController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $enrollments = Enrollment::query()
            ->with(['student', 'class', 'section'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->where('status', 'active')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->whereHas('student', fn ($q) => $q
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('student_code', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%")));
            })
            ->orderBy('class_id')
            ->orderBy('roll_no')
            ->paginate(20)
            ->withQueryString();

        $dues = $this->dueMap($session, $enrollments->pluck('student_id'));

        // Data for the live allocation preview inside each Collect modal: every
        // outstanding row for the listed students, in the same order the server
        // will allocate (oldest due first, then id).
        $allocationRows = collect();

        if ($session) {
            $horizon = \App\Support\FeeWindow::horizonDate();

            $allocationRows = StudentFeeAssignment::query()
                ->with('category')
                ->where('academic_session_id', $session->id)
                ->whereIn('student_id', $enrollments->pluck('student_id'))
                ->whereIn('status', ['pending', 'partial'])
                ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                    ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
                ->orderBy('due_date')
                ->orderBy('id')
                ->get()
                ->groupBy('student_id')
                ->map(fn ($rows) => $rows->map(fn (StudentFeeAssignment $row) => [
                    'fee' => $row->category?->name ?? 'Fee',
                    'period' => $row->period,
                    'due' => $row->due_date?->format('d M Y'),
                    'balance' => round(max(0, (float) $row->net_amount - (float) $row->paid_amount), 2),
                    'future' => $row->due_date !== null && $row->due_date->toDateString() > $horizon,
                ])->values()->all());
        }

        return view('fees.quick', [
            'enrollments' => $enrollments,
            'dues' => $dues,
            'allocationRows' => $allocationRows,
            'session' => $session,
            'recent' => Payment::with('student')
                ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
                ->where('status', 'completed')
                ->latest('paid_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function store(QuickPaymentRequest $request): RedirectResponse
    {
        $session = current_session();
        abort_unless($session, 404, 'No academic session is configured.');

        $student = Student::findOrFail($request->integer('student_id'));
        $lines = $this->payments->autoAllocate(
            $student,
            $session,
            (float) $request->input('amount'),
            $request->boolean('include_future')
        );

        $payment = $this->payments->collect(
            $student,
            $session,
            $lines,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('payments.receipt', $payment)
            ->with('success', 'Quick payment collected and allocated oldest dues first.');
    }

    private function dueMap(?AcademicSession $session, Collection $studentIds): Collection
    {
        if (! $session) {
            return collect();
        }

        return StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['pending', 'partial'])
            ->where(fn ($query) => $query
                ->whereNull('due_date')
                ->orWhere('due_date', '<=', \App\Support\FeeWindow::horizonDate()))
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2));
    }
}
