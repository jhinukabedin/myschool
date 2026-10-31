<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\StudentPortalLink;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortalAccountController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.portal-accounts', [
            'studentLinks' => StudentPortalLink::query()
                ->with(['user', 'student'])
                ->latest()
                ->paginate(15, ['*'], 'students_page'),
            'parentLinks' => ParentStudentLink::query()
                ->with(['user', 'student', 'guardian'])
                ->latest()
                ->paginate(20, ['*'], 'parents_page'),
            'students' => Student::query()
                ->where('status', 'active')
                ->whereDoesntHave('portalLink')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'guardians' => StudentGuardian::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'portal_role' => ['required', 'in:student,parent'],
            'student_id' => ['required', 'exists:students,id'],
            'student_guardian_id' => ['nullable', 'exists:student_guardians,id'],
            'relationship' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:10', 'confirmed'],
        ]);

        $role = Role::query()->where('slug', $data['portal_role'])->firstOrFail();
        $student = Student::findOrFail($data['student_id']);
        $guardian = null;

        if ($data['portal_role'] === 'parent' && ! empty($data['student_guardian_id'])) {
            $guardian = StudentGuardian::findOrFail($data['student_guardian_id']);
            if ($guardian->student_id !== $student->id) {
                throw ValidationException::withMessages([
                    'student_guardian_id' => 'The selected guardian does not belong to this student.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $data, $role, $student, $guardian) {
            $user = User::withTrashed()->where('email', $data['email'])->first();

            if ($user) {
                if ($user->trashed() || $user->role_id !== $role->id) {
                    throw ValidationException::withMessages([
                        'email' => 'This email belongs to an account with a different role. Use another email.',
                    ]);
                }

                if ($data['portal_role'] === 'student' && $user->studentPortalLink()->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'This account is already linked to a student profile.',
                    ]);
                }

                if ($user->studentPortalLink()->exists() && $data['portal_role'] === 'parent') {
                    throw ValidationException::withMessages([
                        'email' => 'This email is already used by a student portal account.',
                    ]);
                }

                if ($data['portal_role'] === 'parent' && ParentStudentLink::query()
                    ->where('user_id', $user->id)
                    ->where('student_id', $student->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'This parent account is already linked to the selected child.',
                    ]);
                }
            } else {
                if (empty($data['password'])) {
                    throw ValidationException::withMessages([
                        'password' => 'Set an initial password for the new portal account.',
                    ]);
                }

                $user = User::create([
                    'role_id' => $role->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                ]);
            }

            if ($data['portal_role'] === 'student') {
                if (StudentPortalLink::where('student_id', $student->id)->exists()) {
                    throw ValidationException::withMessages([
                        'student_id' => 'This student already has a portal account.',
                    ]);
                }

                StudentPortalLink::create([
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'verified_at' => now(),
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ]);
            } else {
                ParentStudentLink::create([
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'student_guardian_id' => $guardian?->id,
                    'relationship' => $data['relationship'] ?? $guardian?->relation,
                    'verified_at' => now(),
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ]);
            }

            $this->audit->record(
                'portal_account_linked',
                'Portals',
                "{$data['portal_role']} portal account {$user->email} linked to {$student->full_name}",
                $student,
                null,
                ['user_id' => $user->id, 'role' => $data['portal_role']]
            );
        });

        return back()->with('success', 'Portal account linked. The user can now sign in from the portal login page.');
    }

    public function destroyStudent(StudentPortalLink $link): RedirectResponse
    {
        $student = $link->student;
        $user = $link->user;
        $link->delete();

        $this->audit->record(
            'portal_account_unlinked',
            'Portals',
            "Student portal access removed for {$user?->email}",
            $student,
            ['student_id' => $student?->id, 'user_id' => $user?->id],
            null
        );

        return back()->with('success', 'Student portal link removed. The account can no longer access student records.');
    }

    public function destroyParent(ParentStudentLink $link): RedirectResponse
    {
        $student = $link->student;
        $user = $link->user;
        $link->delete();

        $this->audit->record(
            'portal_account_unlinked',
            'Portals',
            "Parent portal access to {$student?->full_name} removed for {$user?->email}",
            $student,
            ['student_id' => $student?->id, 'user_id' => $user?->id],
            null
        );

        return back()->with('success', 'Parent link removed. The account can no longer access this child.');
    }
}
