<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\Staff;
use App\Services\AccountProvisioningService;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogService $audit,
        private readonly AccountProvisioningService $accounts
    ) {}

    public function index(Request $request): View
    {
        $showArchived = $request->boolean('archived');

        $staff = Staff::query()
            ->with(['department', 'designation'])
            ->when($showArchived, fn ($query) => $query->onlyTrashed(), fn ($query) => $query->whereNull('deleted_at'))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('employee_no', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'showArchived' => $showArchived,
            'archivedCount' => Staff::onlyTrashed()->count(),
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'summary' => [
                'active' => Staff::where('status', 'active')->count(),
                'academic' => Staff::where('status', 'active')->where('category', 'academic')->count(),
                'non_academic' => Staff::where('status', 'active')->where('category', 'non_academic')->count(),
                'departments' => Department::count(),
                'payroll_month' => Payroll::where('month', now()->format('Y-m'))->sum('net_salary'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('staff.form', [
            'staffMember' => new Staff([
                'gender' => 'male',
                'employment_type' => 'full_time',
                'status' => 'active',
                'category' => 'academic',
                'joining_date' => now()->toDateString(),
                'basic_salary' => 0,
            ]),
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'roles' => $this->assignableRoles(),
            'defaultRoleId' => Role::query()->where('slug', 'teacher')->value('id'),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salary = $this->salaryFields($data);
        $qualifications = $this->qualifications($data);
        $role = Role::query()->findOrFail($data['role_id']);

        $photo = $request->hasFile('photo') ? $request->file('photo')->store('staff', 'public') : null;

        [$staff, $account] = DB::transaction(function () use ($data, $salary, $qualifications, $photo, $role, $request) {
            $staff = Staff::create([
                'employee_no' => $this->numbers->employeeNumber((int) now()->format('Y')),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'photo_path' => $photo,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'category' => $data['category'],
                'joining_date' => $data['joining_date'],
                'employment_type' => $data['employment_type'],
                'status' => $data['status'],
                'basic_salary' => $data['basic_salary'],
            ]);

            $staff->salaryStructures()->create($salary + ['effective_from' => now()->toDateString()]);
            $this->syncQualifications($staff, $qualifications);

            $account = $this->accounts->provisionStaff(
                $staff,
                $role,
                $data['username'] ?? null,
                $request->user()
            );

            $this->audit->record(
                'staff_created',
                'Staff',
                "Staff profile {$staff->full_name} ({$staff->employee_no}) created with login {$account['user']->username}",
                $staff,
                null,
                $staff->getAttributes()
            );

            return [$staff->fresh(), $account];
        });

        $message = "Staff member added successfully. Login: {$account['user']->username}. Initial password: {$account['default_password']}. Please ask the staff member to change it.";

        return redirect()->route('staff.show', $staff)->with('success', $message);
    }

    public function show(Staff $staff): View
    {
        $staff->load(['department', 'designation', 'user.role', 'salaryStructures', 'qualifications', 'payrolls' => fn ($query) => $query->latest('month')]);

        return view('staff.show', [
            'staffMember' => $staff,
            'currentStructure' => $staff->salaryStructures->sortByDesc('effective_from')->first(),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $staff->load(['salaryStructures', 'user', 'qualifications']);

        return view('staff.form', [
            'staffMember' => $staff,
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'roles' => $this->assignableRoles(),
            'defaultRoleId' => $staff->user?->role_id ?? Role::query()->where('slug', 'teacher')->value('id'),
        ]);
    }

    public function update(StoreStaffRequest $request, Staff $staff): RedirectResponse
    {
        $data = $request->validated();
        $before = $staff->getAttributes();
        $photo = $staff->photo_path;
        $qualifications = $this->qualifications($data);
        $role = Role::query()->findOrFail($data['role_id']);

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $photo = $request->file('photo')->store('staff', 'public');
        }

        DB::transaction(function () use ($staff, $data, $photo, $role, $request, $before, $qualifications) {
            $staff->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'photo_path' => $photo,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'category' => $data['category'],
                'joining_date' => $data['joining_date'],
                'employment_type' => $data['employment_type'],
                'status' => $data['status'],
                'basic_salary' => $data['basic_salary'],
            ]);

            $this->syncQualifications($staff, $qualifications);

            $account = $this->accounts->provisionStaff(
                $staff->fresh(),
                $role,
                $data['username'] ?? null,
                $request->user()
            );

            $this->audit->record(
                'staff_updated',
                'Staff',
                "Staff profile {$staff->full_name} updated (login {$account['user']->username})",
                $staff,
                $before,
                $staff->fresh()->getAttributes()
            );
        });

        return redirect()->route('staff.show', $staff)->with('success', 'Staff profile and login account updated.');
    }

    public function storeSalaryStructure(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'house_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'tax_deduction' => ['nullable', 'numeric', 'min:0'],
            'provident_fund' => ['nullable', 'numeric', 'min:0'],
            'other_deduction' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);

        $structure = $staff->salaryStructures()->updateOrCreate(
            ['effective_from' => $data['effective_from']],
            $data
        );

        $this->audit->record(
            'salary_structure_updated',
            'Payroll',
            "Salary structure for {$staff->full_name} set effective {$structure->effective_from->toDateString()}",
            $staff,
            null,
            $data
        );

        return back()->with('success', 'Salary structure saved.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:departments,name']]);
        $department = Department::create($data);
        $this->audit->record('department_created', 'Staff', "Department {$department->name} created", $department, null, $data);

        return back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:departments,name,'.$department->id],
        ]);

        $before = $department->only(['name']);
        $department->update($data);

        $this->audit->record('department_updated', 'Staff', "Department {$department->name} renamed from {$before['name']}", $department, $before, $data);

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(Department $department): RedirectResponse
    {
        $inUse = $department->staff()->withTrashed()->count();

        if ($inUse > 0) {
            return back()->with('error', "“{$department->name}” is assigned to {$inUse} staff member(s). Move them to another department before deleting it.");
        }

        $before = $department->only(['name']);
        $department->delete();

        $this->audit->record('department_deleted', 'Staff', "Department {$before['name']} deleted", null, $before, null);

        return back()->with('success', 'Department deleted.');
    }

    public function storeDesignation(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:designations,name']]);
        $designation = Designation::create($data);
        $this->audit->record('designation_created', 'Staff', "Designation {$designation->name} created", $designation, null, $data);

        return back()->with('success', 'Designation added.');
    }

    public function updateDesignation(Request $request, Designation $designation): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:designations,name,'.$designation->id],
        ]);

        $before = $designation->only(['name']);
        $designation->update($data);

        $this->audit->record('designation_updated', 'Staff', "Designation {$designation->name} renamed from {$before['name']}", $designation, $before, $data);

        return back()->with('success', 'Designation updated.');
    }

    public function destroyDesignation(Designation $designation): RedirectResponse
    {
        $inUse = $designation->staff()->withTrashed()->count();

        if ($inUse > 0) {
            return back()->with('error', "“{$designation->name}” is assigned to {$inUse} staff member(s). Move them to another designation before deleting it.");
        }

        $before = $designation->only(['name']);
        $designation->delete();

        $this->audit->record('designation_deleted', 'Staff', "Designation {$before['name']} deleted", null, $before, null);

        return back()->with('success', 'Designation deleted.');
    }

    /**
     * Organisation setup: rename or remove departments and designations.
     */
    public function organisation(): View
    {
        return view('staff.organisation', [
            'departments' => Department::withCount('staff')->orderBy('name')->get(),
            'designations' => Designation::withCount('staff')->orderBy('name')->get(),
        ]);
    }

    /**
     * Archive a staff member. Soft delete keeps payroll and salary history
     * intact; the linked login is deactivated so it can no longer sign in.
     */
    public function destroy(Staff $staff): RedirectResponse
    {
        $before = $staff->getAttributes();

        DB::transaction(function () use ($staff) {
            if ($staff->user) {
                $staff->user->forceFill(['is_active' => false])->save();
            }

            $staff->delete();
        });

        $this->audit->record(
            'staff_archived',
            'Staff',
            "Staff profile {$staff->full_name} ({$staff->employee_no}) archived and login deactivated",
            $staff,
            $before,
            ['deleted_at' => now()->toDateTimeString()]
        );

        return redirect()->route('staff.index')->with('success', 'Staff member archived. Payroll and salary history are preserved.');
    }

    /**
     * Restore an archived staff profile and re-activate its login.
     */
    public function restore(Staff $staff): RedirectResponse
    {
        if (! $staff->trashed()) {
            return back()->with('error', 'This staff member is not archived.');
        }

        DB::transaction(function () use ($staff) {
            $staff->restore();

            if ($staff->user) {
                $staff->user->forceFill(['is_active' => true])->save();
            }
        });

        $this->audit->record(
            'staff_restored',
            'Staff',
            "Staff profile {$staff->full_name} ({$staff->employee_no}) restored and login reactivated",
            $staff,
            ['deleted_at' => $staff->deleted_at?->toDateTimeString()],
            ['deleted_at' => null]
        );

        return redirect()->route('staff.index')->with('success', 'Staff member restored.');
    }

    private function assignableRoles()
    {
        return Role::query()
            ->whereNotIn('slug', ['student', 'parent'])
            ->when(! auth()->user()?->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', 'super-admin'))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    private function salaryFields(array $data): array
    {
        return [
            'basic_salary' => $data['basic_salary'],
            'house_allowance' => $data['house_allowance'] ?? 0,
            'transport_allowance' => $data['transport_allowance'] ?? 0,
            'medical_allowance' => $data['medical_allowance'] ?? 0,
            'other_allowance' => $data['other_allowance'] ?? 0,
            'tax_deduction' => $data['tax_deduction'] ?? 0,
            'provident_fund' => $data['provident_fund'] ?? 0,
            'other_deduction' => $data['other_deduction'] ?? 0,
        ];
    }

    /**
     * Keep only meaningful qualification rows (exam name required).
     *
     * @return array<int, array{exam_name: string, institution: ?string, passing_year: ?int, result: ?string, sort_order: int}>
     */
    private function qualifications(array $data): array
    {
        $rows = [];

        foreach (array_values($data['qualifications'] ?? []) as $index => $row) {
            $exam = trim((string) ($row['exam_name'] ?? ''));

            if ($exam === '') {
                continue;
            }

            $rows[] = [
                'exam_name' => $exam,
                'institution' => filled($row['institution'] ?? null) ? trim((string) $row['institution']) : null,
                'passing_year' => filled($row['passing_year'] ?? null) ? (int) $row['passing_year'] : null,
                'result' => filled($row['result'] ?? null) ? trim((string) $row['result']) : null,
                'sort_order' => $index,
            ];
        }

        return $rows;
    }

    private function syncQualifications(Staff $staff, array $rows): void
    {
        $staff->qualifications()->delete();

        if ($rows !== []) {
            $staff->qualifications()->createMany($rows);
        }
    }
}
