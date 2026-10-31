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
                    ->orWhere('admission_no', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%")));
            })
            ->orderBy('class_id')
            ->orderBy('roll_no')
            ->paginate(20)
            ->withQueryString();

        $dues = $this->dueMap($session, $enrollments->pluck('student_id'));

        return view('fees.quick', [
            'enrollments' => $enrollments,
            'dues' => $dues,
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
        $lines = $this->payments->autoAllocate($student, $session, (float) $request->input('amount'));

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
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2));
    }
}
