<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionReportController extends Controller
{
    public function index(Request $request): View
    {
        $session = $request->filled('session_id')
            ? AcademicSession::find($request->integer('session_id'))
            : current_session();

        abort_unless($session, 404, 'No academic session is configured.');

        return view('students.session-report', $this->reportData($session));
    }

    public function export(Request $request): StreamedResponse
    {
        $session = $request->filled('session_id')
            ? AcademicSession::findOrFail($request->integer('session_id'))
            : current_session();

        abort_unless($session, 404);

        $data = $this->reportData($session);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['EduSphere School - Session Report', $data['session']->name]);
            fputcsv($out, []);
            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Total enrolled students', $data['totalEnrolled']]);
            fputcsv($out, ['Active students', $data['activeStudents']]);
            fputcsv($out, ['New admissions', $data['newAdmissions']]);
            fputcsv($out, ['Fees collected', number_format($data['collected'], 2, '.', '')]);
            fputcsv($out, ['Outstanding fees', number_format($data['outstanding'], 2, '.', '')]);
            fputcsv($out, ['Attendance records', $data['attendanceTotal']]);
            fputcsv($out, ['Attendance present %', $data['attendancePercentage']]);
            fputcsv($out, []);
            fputcsv($out, ['Class', 'Sections', 'Students', 'Male', 'Female', 'Collected', 'Outstanding']);
            foreach ($data['classRows'] as $row) {
                fputcsv($out, [
                    $row['class'], $row['sections'], $row['students'], $row['male'], $row['female'],
                    number_format($row['collected'], 2, '.', ''), number_format($row['outstanding'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'session-report-'.$session->name.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function reportData(AcademicSession $session): array
    {
        $enrollments = Enrollment::query()
            ->with(['student', 'class', 'section'])
            ->where('academic_session_id', $session->id)
            ->get();

        $active = $enrollments->where('status', 'active');
        $students = Student::query()->whereIn('id', $enrollments->pluck('student_id'))->get()->keyBy('id');
        $collected = (float) Payment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'completed')
            ->sum('amount');
        $outstanding = (float) StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->sum('net_amount');
        $paidOnOutstanding = (float) DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('student_fee_assignments', 'student_fee_assignments.id', '=', 'payment_allocations.student_fee_assignment_id')
            ->where('payments.status', 'completed')
            ->where('student_fee_assignments.academic_session_id', $session->id)
            ->whereIn('student_fee_assignments.status', ['pending', 'partial'])
            ->sum('payment_allocations.amount');
        $outstanding = max(0, round($outstanding - $paidOnOutstanding, 2));

        $attendance = AttendanceRecord::query()
            ->where('academic_session_id', $session->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $attendanceTotal = (int) $attendance->sum();
        $present = (int) ($attendance['present'] ?? 0) + (int) ($attendance['late'] ?? 0);

        $paymentsByClass = DB::table('payments')
            ->join('enrollments', function ($join) use ($session) {
                $join->on('enrollments.student_id', '=', 'payments.student_id')
                    ->where('enrollments.academic_session_id', '=', $session->id);
            })
            ->where('payments.academic_session_id', $session->id)
            ->where('payments.status', 'completed')
            ->selectRaw('enrollments.class_id, SUM(payments.amount) as total')
            ->groupBy('enrollments.class_id')
            ->pluck('total', 'class_id');

        $studentClass = $enrollments->keyBy('student_id');
        $outstandingRows = StudentFeeAssignment::query()
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get(['student_id', 'net_amount']);

        $outstandingByClass = $outstandingRows
            ->groupBy(fn ($row) => $studentClass->get($row->student_id)?->class_id)
            ->map(fn ($rows) => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2));

        $classRows = SchoolClass::query()
            ->whereIn('id', $enrollments->pluck('class_id')->unique())
            ->ordered()
            ->get()
            ->map(function (SchoolClass $class) use ($enrollments, $students, $paymentsByClass, $outstandingByClass) {
                $rows = $enrollments->where('class_id', $class->id);

                return [
                    'class' => $class->name,
                    'sections' => $rows->pluck('section.name')->unique()->filter()->implode(', '),
                    'students' => $rows->where('status', 'active')->count(),
                    'male' => $rows->filter(fn ($row) => $students->get($row->student_id)?->gender === 'male')->count(),
                    'female' => $rows->filter(fn ($row) => $students->get($row->student_id)?->gender === 'female')->count(),
                    'collected' => (float) ($paymentsByClass[$class->id] ?? 0),
                    'outstanding' => (float) ($outstandingByClass[$class->id] ?? 0),
                ];
            })
            ->all();

        return [
            'session' => $session,
            'enrollments' => $enrollments,
            'totalEnrolled' => $enrollments->count(),
            'activeStudents' => $active->count(),
            'newAdmissions' => Student::query()->whereBetween('admission_date', [$session->start_date, $session->end_date])->count(),
            'collected' => $collected,
            'outstanding' => $outstanding,
            'attendanceTotal' => $attendanceTotal,
            'attendancePercentage' => $attendanceTotal > 0 ? round(($present / $attendanceTotal) * 100, 1) : 0,
            'classRows' => $classRows,
        ];
    }
}
