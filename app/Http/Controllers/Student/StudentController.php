<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $students = Student::query()
            ->with(['enrollments' => fn ($query) => $query
                ->when($session, fn ($q) => $q->where('academic_session_id', $session?->id))
                ->with(['class', 'section', 'academicSession']), 'guardians'])
            ->search($request->input('q'))
            ->when($session, fn ($query) => $query->whereHas('enrollments', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->where('status', '!=', 'inactive')))
            ->when($request->filled('class_id'), fn ($query) => $query->whereHas('enrollments', fn ($q) => $q
                ->where('academic_session_id', $session?->id)
                ->where('class_id', $request->integer('class_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'summary' => [
                'active' => Student::where('status', 'active')->count(),
                'new' => $session ? Student::whereBetween('admission_date', [$session->start_date, $session->end_date])->count() : 0,
                'archived' => Student::whereIn('status', ['passed_out', 'transferred', 'archived'])->count(),
            ],
        ]);
    }

    public function archive(Request $request): View
    {
        $students = Student::query()
            ->with(['enrollments' => fn ($query) => $query->with(['class', 'section', 'academicSession']), 'guardians'])
            ->whereIn('status', ['passed_out', 'transferred', 'archived'])
            ->search($request->input('q'))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('students.archive', ['students' => $students]);
    }

    public function show(Student $student): View
    {
        $session = current_session();
        $student->load([
            'guardians', 'enrollments.academicSession', 'enrollments.class', 'enrollments.section',
            'portalLink.user.role', 'parentLinks.user.role', 'parentLinks.guardian',
        ]);

        $enrollment = $session
            ? $student->enrollments->firstWhere('academic_session_id', $session->id)
            : $student->enrollments->sortByDesc('id')->first();

        $assignments = $session
            ? StudentFeeAssignment::query()
                ->with('category')
                ->where('student_id', $student->id)
                ->where('academic_session_id', $session->id)
                ->orderBy('due_date')
                ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                    ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
                ->get()
            : collect();

        $payments = Payment::query()
            ->with('receivedBy')
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->latest('paid_at')
            ->limit(10)
            ->get();

        $attendance = $session
            ? AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $session->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        return view('students.show', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $enrollment,
            'assignments' => $assignments,
            'payments' => $payments,
            'attendance' => $attendance,
            'totalDue' => $assignments->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)),
        ]);
    }

    public function edit(Student $student): View
    {
        return view('students.edit', ['student' => $student->load('guardians')]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $before = $student->getAttributes();

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('students', 'public');
        }

        unset($data['photo']);
        $student->update($data);

        $guardianData = $request->validate([
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_relation' => ['nullable', 'string', 'max:40'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_occupation' => ['nullable', 'string', 'max:80'],
            'guardian_address' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($guardianData['guardian_name'] ?? null)) {
            $student->guardians()->updateOrCreate(
                ['is_primary' => true],
                [
                    'name' => $guardianData['guardian_name'],
                    'relation' => $guardianData['guardian_relation'] ?? 'Guardian',
                    'phone' => $guardianData['guardian_phone'] ?? $student->phone,
                    'email' => $guardianData['guardian_email'] ?? null,
                    'occupation' => $guardianData['guardian_occupation'] ?? null,
                    'address' => $guardianData['guardian_address'] ?? null,
                ]
            );
        }

        $this->audit->record(
            'student_updated',
            'Students',
            "Updated student record for {$student->full_name}",
            $student,
            $before,
            $student->getAttributes()
        );

        return redirect()->route('students.show', $student)->with('success', 'Student record updated.');
    }

    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,passed_out,transferred,archived'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($student, $data) {
            $before = $student->only(['status']);

            $student->update(['status' => $data['status']]);

            $enrollmentStatus = match ($data['status']) {
                'passed_out' => 'completed',
                'transferred' => 'transferred',
                'inactive' => 'inactive',
                default => null,
            };

            if ($enrollmentStatus) {
                Enrollment::query()
                    ->where('student_id', $student->id)
                    ->where('status', 'active')
                    ->update(['status' => $enrollmentStatus, 'remarks' => $data['remarks'] ?? null]);
            }

            $this->audit->record(
                'student_status_changed',
                'Students',
                "Status for {$student->full_name} changed from {$before['status']} to {$data['status']}",
                $student,
                $before,
                ['status' => $data['status'], 'remarks' => $data['remarks'] ?? null]
            );
        });

        return back()->with('success', 'Student status updated.');
    }
}
