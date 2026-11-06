<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicSession;
use App\Models\AdmissionEnquiry;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\AccountProvisioningService;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Services\FeeAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogService $audit,
        private readonly FeeAssignmentService $fees,
        private readonly AccountProvisioningService $accounts
    ) {}

    public function create(Request $request): View
    {
        $session = current_session();
        $enquiry = $request->filled('enquiry')
            ? AdmissionEnquiry::find($request->integer('enquiry'))
            : null;

        return view('students.admission', [
            'session' => $session,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::with('sections')->ordered()->get(),
            'sections' => Section::ordered()->get(),
            'structures' => $session
                ? FeeStructure::with('items')->where('academic_session_id', $session->id)->where('status', 'active')->get()
                : collect(),
            'enquiry' => $enquiry,
            'preselectedSibling' => $request->filled('sibling')
                ? Student::query()->with('guardians')->whereKey($request->integer('sibling'))->first()
                : null,
        ]);
    }

    /**
     * JSON search used by the admission form's sibling picker.
     * Returns up to 10 active students matching name, admission no or student code,
     * together with their primary guardian so the form can auto-fill guardian fields.
     */
    public function searchSiblings(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $session = current_session();

        $students = Student::query()
            ->with(['guardians' => fn ($query) => $query->where('is_primary', true)])
            ->where('status', 'active')
            ->where(function ($query) use ($term) {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('name_bn', 'like', "%{$term}%")
                    ->orWhere('admission_no', 'like', "%{$term}%")
                    ->orWhere('student_code', 'like', "%{$term}%");
            })
            ->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(function (Student $student) use ($session) {
                $enrollment = $session ? $student->enrollmentFor($session->id) : $student->enrollments()->first();
                $guardian = $student->guardians->first();

                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'admission_no' => $student->admission_no,
                    'student_code' => $student->student_code,
                    'class' => $enrollment?->class?->name,
                    'section' => $enrollment?->section?->name,
                    'roll' => $enrollment?->roll_no,
                    'guardian' => $guardian ? [
                        'name' => $guardian->name,
                        'relation' => $guardian->relation,
                        'phone' => $guardian->phone,
                        'email' => $guardian->email,
                        'occupation' => $guardian->occupation,
                        'address' => $guardian->address,
                    ] : null,
                ];
            });

        return response()->json($students);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $session = AcademicSession::findOrFail($data['academic_session_id']);
        $class = SchoolClass::findOrFail($data['class_id']);
        $section = Section::findOrFail($data['section_id']);

        if (! $class->sections()->whereKey($section->id)->exists()) {
            throw ValidationException::withMessages(['section_id' => 'The selected section is not available for this class.']);
        }

        $structure = null;

        if (! empty($data['fee_structure_id'])) {
            $structure = FeeStructure::with('items')->findOrFail($data['fee_structure_id']);

            if ($structure->academic_session_id !== $session->id || $structure->class_id !== $class->id) {
                throw ValidationException::withMessages([
                    'fee_structure_id' => 'The selected fee structure does not match the session and class.',
                ]);
            }
        }

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('students', 'public')
            : null;

        [$student, $accounts] = DB::transaction(function () use ($data, $session, $class, $section, $structure, $request, $photoPath) {
            $admissionYear = (int) date('Y', strtotime($data['admission_date']));

            $student = Student::create([
                'admission_no' => $this->numbers->admissionNumber($admissionYear),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? '',
                'name_bn' => $data['name_bn'] ?? null,
                'has_sibling' => $data['has_sibling'] ?? false,
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'blood_group' => $data['blood_group'] ?? null,
                'religion' => $data['religion'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'photo_path' => $photoPath,
                'admission_date' => $data['admission_date'],
                'previous_school' => $data['previous_school'] ?? null,
                'medical_notes' => $data['medical_notes'] ?? null,
                'status' => 'active',
            ]);

            // Link the sibling relationship both ways so family records stay connected.
            if (! empty($data['has_sibling']) && ! empty($data['sibling_student_id'])) {
                $sibling = Student::find($data['sibling_student_id']);

                if ($sibling && $sibling->id !== $student->id) {
                    $student->update(['sibling_student_id' => $sibling->id]);
                }
            }

            $guardian = $student->guardians()->create([
                'name' => $data['guardian_name'],
                'relation' => $data['guardian_relation'],
                'phone' => $data['guardian_phone'],
                'email' => $data['guardian_email'] ?? null,
                'occupation' => $data['guardian_occupation'] ?? null,
                'address' => $data['guardian_address'] ?? null,
                'is_primary' => true,
            ]);

            $roll = $data['roll_no'] ?? null;

            if (! $roll) {
                $roll = (int) Enrollment::query()
                    ->where('academic_session_id', $session->id)
                    ->where('class_id', $class->id)
                    ->where('section_id', $section->id)
                    ->max('roll_no') + 1;
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'section_id' => $section->id,
                'roll_no' => $roll,
                'status' => 'active',
            ]);

            $accounts = $this->accounts->provisionForAdmission($student, $enrollment, $guardian, $request->user());

            if ($structure) {
                $this->fees->assignStructure(
                    $student,
                    $structure,
                    $session,
                    array_map('intval', (array) $request->input('optional_items', [])),
                    $request->user()
                );
            }

            if ($request->filled('enquiry_id')) {
                AdmissionEnquiry::query()
                    ->whereKey($request->integer('enquiry_id'))
                    ->update(['status' => 'admitted', 'student_id' => $student->id]);
            }

            $this->audit->record(
                'student_admitted',
                'Students',
                "Admitted {$student->full_name} ({$student->admission_no}) to {$class->name} - {$section->name}",
                $student,
                null,
                ['admission_no' => $student->admission_no, 'class' => $class->name, 'section' => $section->name]
            );

            return [$student, $accounts];
        });

        $message = "Student admitted successfully. Student ID: {$accounts['student_code']}. "
            .($accounts['student_created']
                ? 'Student portal account created ('.$accounts['student_username'].')'
                : 'Existing student portal account linked ('.$accounts['student_username'].')').'.';

        if ($accounts['parent_username']) {
            $message .= $accounts['parent_created']
                ? " Guardian portal account created ({$accounts['parent_username']})."
                : " Existing guardian portal account linked ({$accounts['parent_username']}); its password was not changed.";
        }

        if ($accounts['default_password']) {
            $message .= " Initial password for new account(s): {$accounts['default_password']}. Please change it after the first sign-in.";
        }

        return redirect()
            ->route('students.show', $student)
            ->with('success', $message)
            ->with('account_details', $accounts);
    }
}