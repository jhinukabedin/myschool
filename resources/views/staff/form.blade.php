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

        @php
            $existingQualifications = $staffMember->exists
                ? $staffMember->qualifications->map(fn ($row) => [
                    'exam_name' => $row->exam_name,
                    'institution' => $row->institution,
                    'passing_year' => $row->passing_year,
                    'result' => $row->result,
                ])->values()->all()
                : [];

            if ($existingQualifications === [] && old('qualifications')) {
                $existingQualifications = collect(old('qualifications'))->map(fn ($row) => [
                    'exam_name' => (string) ($row['exam_name'] ?? ''),
                    'institution' => (string) ($row['institution'] ?? ''),
                    'passing_year' => $row['passing_year'] ?? '',
                    'result' => (string) ($row['result'] ?? ''),
                ])->values()->all();
            }

            if ($existingQualifications === []) {
                $existingQualifications = [['exam_name' => '', 'institution' => '', 'passing_year' => '', 'result' => '']];
            }
        @endphp

        <div class="card" x-data="staffQualifications(@js($existingQualifications))">
            <div class="card-header">
                <div><h3 class="card-title">{{ __('Educational Qualifications') }}</h3><p class="card-sub">{{ __('Add one row per certificate — for example SSC, 2026, Rangpur School.') }}</p></div>
                <button type="button" class="btn btn-secondary btn-sm" @click="add()">
                    <x-icon name="plus" :size="14" /> {{ __('Add qualification') }}
                </button>
            </div>

            @if ($errors->has('qualifications') || $errors->has('qualifications.*'))
                <div class="form-section" style="padding-bottom:0">
                    <div class="alert alert-warning" role="alert">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>
                            <strong>{{ __('Please check the qualification rows') }}</strong>
                            <ul style="margin:6px 0 0;padding-left:18px">
                                @foreach ($errors->get('qualifications.*') as $messages)
                                    @foreach ($messages as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th style="min-width:150px">{{ __('Exam / degree') }}</th>
                            <th style="min-width:200px">{{ __('Institution') }}</th>
                            <th class="num" style="width:120px">{{ __('Passing year') }}</th>
                            <th style="width:140px">{{ __('Result / grade') }}</th>
                            <th style="width:56px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in rows" :key="index">
                            <tr>
                                <td class="muted" x-text="index + 1"></td>
                                <td><input class="input" type="text" :name="'qualifications[' + index + '][exam_name]'" x-model="row.exam_name" maxlength="120" placeholder="SSC"></td>
                                <td><input class="input" type="text" :name="'qualifications[' + index + '][institution]'" x-model="row.institution" maxlength="160" placeholder="Rangpur School"></td>
                                <td><input class="input" type="number" min="1950" :max="{{ now()->year + 1 }}" :name="'qualifications[' + index + '][passing_year]'" x-model="row.passing_year" placeholder="2026"></td>
                                <td><input class="input" type="text" :name="'qualifications[' + index + '][result]'" x-model="row.result" maxlength="60" placeholder="GPA 5.00"></td>
                                <td class="actions">
                                    <button type="button" class="btn-icon" title="{{ __('Remove') }}" @click="remove(index)" :disabled="rows.length <= 1">
                                        <x-icon name="trash" :size="15" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Employment</h3><p class="card-sub">Department, designation and status</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="department_id" label="Department" :options="$departments->pluck('name', 'id')" :selected="$staffMember->department_id" placeholder="Unassigned" />
                    <x-select name="designation_id" label="Designation" :options="$designations->pluck('name', 'id')" :selected="$staffMember->designation_id" placeholder="Unassigned" />
                    <x-select name="category" label="Category" :options="['academic' => 'Academic', 'non_academic' => 'Non-academic']" :selected="$staffMember->category ?? 'academic'" required help="Academic staff teach classes; non-academic staff handle administration, accounts, library and support." />
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

@push('scripts')
<script>
    function staffQualifications(initial) {
        const clean = (row) => ({
            exam_name: row?.exam_name ?? '',
            institution: row?.institution ?? '',
            passing_year: row?.passing_year ?? '',
            result: row?.result ?? '',
        });

        return {
            rows: (Array.isArray(initial) && initial.length ? initial : [{}]).map(clean),

            add() {
                this.rows.push({ exam_name: '', institution: '', passing_year: '', result: '' });
            },

            remove(index) {
                if (this.rows.length <= 1) {
                    this.rows[0] = { exam_name: '', institution: '', passing_year: '', result: '' };

                    return;
                }

                this.rows.splice(index, 1);
            },
        };
    }
</script>
@endpush
