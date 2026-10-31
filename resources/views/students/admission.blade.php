@extends('layouts.app')

@section('title', 'New Admission')

@section('content')
    <x-page-header eyebrow="Students" title="New Admission" subtitle="Create the student record, guardian profile, class enrolment and optional fee assignment in one step.">
        <x-slot:actions>
            <x-button :href="route('students.index')" variant="secondary" icon="arrow-left">All students</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $session)
        <div class="alert alert-warning"><span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span><div>No academic session is configured. Ask an administrator to create one first.</div></div>
    @endif

    <form method="POST" action="{{ route('admissions.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($enquiry)
            <input type="hidden" name="enquiry_id" value="{{ $enquiry->id }}">
        @endif

        <div class="grid-main-side">
            <div class="stack">
                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">Student Information</h3><p class="card-sub">Official details used on records and result sheets</p></div>
                        <x-badge color="violet">Required fields marked *</x-badge>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title">Identity</div>
                        <div class="form-grid">
                            <x-input name="first_name" label="First name" :value="$enquiry?->student_name ? Str::before($enquiry->student_name, ' ') : null" required />
                            <x-input name="last_name" label="Last name" :value="$enquiry?->student_name ? Str::after($enquiry->student_name, ' ') : null" required />
                            <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" required />
                            <x-input name="date_of_birth" label="Date of birth" type="date" required />
                            <x-input name="blood_group" label="Blood group" placeholder="e.g. B+" />
                            <x-input name="religion" label="Religion" />
                            <x-input name="nationality" label="Nationality" value="Bangladeshi" />
                            <x-input name="photo" label="Student photo" type="file" help="JPG, PNG or WebP, max 2 MB." />
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title">Contact</div>
                        <div class="form-grid">
                            <x-input name="phone" label="Student phone" />
                            <x-input name="email" label="Student email" type="email" />
                            <div class="span-2"><x-input name="address" label="Address" /></div>
                            <x-input name="city" label="City" value="Dhaka" />
                            <x-input name="state" label="State / Division" value="Dhaka" />
                            <x-input name="postal_code" label="Postal code" />
                            <x-input name="previous_school" label="Previous school" />
                            <div class="span-2"><x-textarea name="medical_notes" label="Medical notes" rows="2" placeholder="Allergies, medication or accessibility needs" /></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">Guardian Information</h3><p class="card-sub">Primary contact for fees and communications</p></div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="guardian_name" label="Guardian name" :value="$enquiry?->guardian_name" required />
                            <x-select name="guardian_relation" label="Relationship" :options="['Father' => 'Father', 'Mother' => 'Mother', 'Guardian' => 'Guardian', 'Other' => 'Other']" required />
                            <x-input name="guardian_phone" label="Guardian phone" :value="$enquiry?->phone" required help="This mobile number becomes the parent portal username. A sibling with the same number shares one parent account." />
                            <x-input name="guardian_email" label="Guardian email" type="email" :value="$enquiry?->email" />
                            <x-input name="guardian_occupation" label="Occupation" />
                            <x-input name="guardian_address" label="Guardian address" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">Enrolment</h3><p class="card-sub">Academic placement for this admission</p></div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$session?->id" required />
                            </div>
                            <div class="span-2">
                                <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" :selected="$enquiry?->class_id" required />
                            </div>
                            <div class="span-2">
                                <x-select name="section_id" label="Section" :options="$sections->pluck('name', 'id')" required />
                            </div>
                            <div class="span-2">
                                <x-input name="roll_no" label="Roll number" type="number" help="Leave blank to generate the next roll number." />
                            </div>
                            <div class="span-2">
                                <x-input name="admission_date" label="Admission date" type="date" :value="now()->toDateString()" required />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">Fees</h3><p class="card-sub">Optionally assign the class fee structure now</p></div>
                    </div>
                    <div class="form-section">
                        <x-select name="fee_structure_id" label="Fee structure" :options="$structures->pluck('name', 'id')" placeholder="Assign later" help="Monthly items generate one due row per month of the session. Optional items can be added from the collection page." />
                        <div class="info-box" style="margin-top:14px">
                            Financial records are never hard-deleted. Receipts must be reversed to preserve a complete audit trail.
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="form-section">
                        <div class="form-section-title">Save admission</div>
                        <p class="muted" style="margin:0 0 10px">The student ID, portal accounts, guardian link, enrolment and fee assignment are written in one database transaction.</p>
                        <div class="info-box" style="margin:0 0 14px">
                            A permanent Student ID is generated as <strong>year + class + roll</strong> (for example 202601001). The student and guardian receive automatic portal logins using the configured default password; the credentials are shown once on the next screen.
                        </div>
                        <div class="stack">
                            <x-button type="submit" variant="primary" icon="user-plus" class="btn-block" :disabled="! $session">Admit student</x-button>
                            <x-button :href="route('students.index')" variant="ghost" class="btn-block">Cancel</x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
