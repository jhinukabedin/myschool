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
        $staff = Staff::query()
            ->with(['department', 'designation'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('employee_no', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'summary' => [
                'active' => Staff::where('status', 'active')->count(),
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
        $role = Role::query()->findOrFail($data['role_id']);

        $photo = $request->hasFile('photo') ? $request->file('photo')->store('staff', 'public') : null;

        [$staff, $account] = DB::transaction(function () use ($data, $salary, $photo, $role, $request) {
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
                'joining_date' => $data['joining_date'],
                'employment_type' => $data['employment_type'],
                'status' => $data['status'],
                'basic_salary' => $data['basic_salary'],
            ]);

            $staff->salaryStructures()->create($salary + ['effective_from' => now()->toDateString()]);

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
        $staff->load(['department', 'designation', 'user.role', 'salaryStructures', 'payrolls' => fn ($query) => $query->latest('month')]);

        return view('staff.show', [
            'staffMember' => $staff,
            'currentStructure' => $staff->salaryStructures->sortByDesc('effective_from')->first(),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $staff->load(['salaryStructures', 'user']);

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
        $role = Role::query()->findOrFail($data['role_id']);

        if ($request->hasFile('photo')) {
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $photo = $request->file('photo')->store('staff', 'public');
        }

        DB::transaction(function () use ($staff, $data, $photo, $role, $request, $before) {
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
                'joining_date' => $data['joining_date'],
                'employment_type' => $data['employment_type'],
                'status' => $data['status'],
                'basic_salary' => $data['basic_salary'],
            ]);

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

    public function storeDesignation(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:designations,name']]);
        $designation = Designation::create($data);
        $this->audit->record('designation_created', 'Staff', "Designation {$designation->name} created", $designation, null, $data);

        return back()->with('success', 'Designation added.');
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
}
