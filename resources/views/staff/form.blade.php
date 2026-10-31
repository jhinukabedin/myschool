@extends('layouts.app')

@section('title', $staffMember->exists ? 'Edit Staff' : 'Add Staff')

@php
    $exists = $staffMember->exists;
    $structure = $exists ? $staffMember->salaryStructures->sortByDesc('effective_from')->first() : null;
@endphp

@section('content')
    <x-page-header eyebrow="Staff" :title="$exists ? 'Edit Staff Profile' : 'Add Staff Member'" subtitle="Employee numbers are generated automatically. Salary structures are versioned by effective date.">
        <x-slot:actions>
            <x-button :href="route('staff.index')" variant="secondary" icon="arrow-left">Staff directory</x-button>
            @if ($exists)
                <x-button :href="route('staff.show', $staffMember)" variant="ghost" icon="user">Open profile</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $exists ? route('staff.update', $staffMember) : route('staff.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Personal Details</h3><p class="card-sub">Basic identity and contact information</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-input name="first_name" label="First name" :value="$staffMember->first_name" required />
                    <x-input name="last_name" label="Last name" :value="$staffMember->last_name" required />
                    <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :selected="$staffMember->gender" required />
                    <x-input name="date_of_birth" label="Date of birth" type="date" :value="$staffMember->date_of_birth?->toDateString()" />
                    <x-input name="phone" label="Phone" :value="$staffMember->phone" required />
                    <x-input name="email" label="Email" type="email" :value="$staffMember->email" />
                    <div class="span-2"><x-input name="address" label="Address" :value="$staffMember->address" /></div>
                    <div class="span-2">
                        <label class="field" for="photo">
                            <span class="field-label">Photo</span>
                            <input id="photo" class="input" type="file" name="photo" accept="image/*">
                            @if ($staffMember->photo_path)<span class="field-hint">A photo is already stored. Uploading a new one replaces it.</span>@endif
                            @error('photo')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Login Account</h3><p class="card-sub">Every staff profile receives a sign-in account with the default school password</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="role_id" label="Login role" :options="$roles" :selected="$staffMember->user?->role_id ?? $defaultRoleId" required help="Choose Teacher for teaching staff so their portal is enabled." />
                    <x-input name="username" label="Username (optional)" :value="$staffMember->user?->username" help="Leave blank to generate one from the email or employee number." />
                </div>
                @if ($staffMember->user)
                    <div class="info-box" style="margin-top:14px">
                        Login username: <strong class="mono">{{ $staffMember->user->username }}</strong>.
                        The existing password is preserved when you save this profile.
                    </div>
                @else
                    <div class="info-box" style="margin-top:14px">The account password will be the school default. Share it once and ask the staff member to change it from their profile.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Employment</h3><p class="card-sub">Department, designation and status</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="department_id" label="Department" :options="$departments->pluck('name', 'id')" :selected="$staffMember->department_id" placeholder="Unassigned" />
                    <x-select name="designation_id" label="Designation" :options="$designations->pluck('name', 'id')" :selected="$staffMember->designation_id" placeholder="Unassigned" />
                    <x-input name="joining_date" label="Joining date" type="date" :value="$staffMember->joining_date?->toDateString()" required />
                    <x-select name="employment_type" label="Employment type" :options="['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract']" :selected="$staffMember->employment_type" required />
                    <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive', 'resigned' => 'Resigned']" :selected="$staffMember->status" required />
                    <x-input name="basic_salary" label="Basic salary" type="number" step="0.01" min="0" :value="$staffMember->basic_salary ?? 0" required />
                </div>
            </div>
        </div>

        @unless ($exists)
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Initial Salary Structure</h3><p class="card-sub">Stored effective from the joining date</p></div></div>
                <div class="form-section">
                    <div class="form-grid three">
                        <x-input name="house_allowance" label="House allowance" type="number" step="0.01" min="0" value="0" />
                        <x-input name="transport_allowance" label="Transport allowance" type="number" step="0.01" min="0" value="0" />
                        <x-input name="medical_allowance" label="Medical allowance" type="number" step="0.01" min="0" value="0" />
                        <x-input name="other_allowance" label="Other allowance" type="number" step="0.01" min="0" value="0" />
                        <x-input name="tax_deduction" label="Tax deduction" type="number" step="0.01" min="0" value="0" />
                        <x-input name="provident_fund" label="Provident fund" type="number" step="0.01" min="0" value="0" />
                        <x-input name="other_deduction" label="Other deduction" type="number" step="0.01" min="0" value="0" />
                    </div>
                    <div class="info-box" style="margin-top:14px">Additional structures can be added from the staff profile with a new effective date. Payroll uses the latest structure effective at month end.</div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="info-box">To change allowances or deductions, add a new salary structure from the staff profile. This preserves the salary history used by payroll.
                        @if ($structure)
                            <div style="margin-top:8px">Current structure effective {{ $structure->effective_from?->format('d M Y') }} · Net {{ money($structure->net_salary) }}.</div>
                        @endif
                    </div>
                </div>
            </div>
        @endunless

        <div class="card">
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('staff.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $exists ? 'Save profile' : 'Add staff member' }}</x-button>
            </div>
        </div>
    </form>
@endsection
