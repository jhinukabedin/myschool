@extends('layouts.app')

@section('title', 'Edit Student')

@section('content')
    <x-page-header eyebrow="Students" :title="'Edit '.$student->full_name" :subtitle="'Admission '.$student->admission_no">
        <x-slot:actions>
            <x-button :href="route('students.show', $student)" variant="secondary" icon="arrow-left">Back to profile</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid-main-side">
            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Student Details</h3><p class="card-sub">Update the official record</p></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="first_name" label="First name" :value="$student->first_name" required />
                            <x-input name="last_name" label="Last name" :value="$student->last_name" required />
                            <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :selected="$student->gender" required />
                            <x-input name="date_of_birth" label="Date of birth" type="date" :value="$student->date_of_birth?->toDateString()" required />
                            <x-input name="blood_group" label="Blood group" :value="$student->blood_group" />
                            <x-input name="religion" label="Religion" :value="$student->religion" />
                            <x-input name="nationality" label="Nationality" :value="$student->nationality" />
                            <x-input name="admission_date" label="Admission date" type="date" :value="$student->admission_date?->toDateString()" required />
                            <x-input name="phone" label="Phone" :value="$student->phone" />
                            <x-input name="email" label="Email" type="email" :value="$student->email" />
                            <div class="span-2"><x-input name="address" label="Address" :value="$student->address" /></div>
                            <x-input name="city" label="City" :value="$student->city" />
                            <x-input name="state" label="State / Division" :value="$student->state" />
                            <x-input name="postal_code" label="Postal code" :value="$student->postal_code" />
                            <x-input name="previous_school" label="Previous school" :value="$student->previous_school" />
                            <x-input name="photo" label="Replace photo" type="file" help="Leave blank to keep the current photo." />
                            <div class="span-2"><x-textarea name="medical_notes" label="Medical notes" :value="$student->medical_notes" rows="2" /></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Primary Guardian</h3><p class="card-sub">Used for fee reminders and emergencies</p></div></div>
                    @php($guardian = $student->primaryGuardian())
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="guardian_name" label="Guardian name" :value="$guardian?->name" />
                            <x-input name="guardian_relation" label="Relationship" :value="$guardian?->relation" />
                            <x-input name="guardian_phone" label="Phone" :value="$guardian?->phone" />
                            <x-input name="guardian_email" label="Email" type="email" :value="$guardian?->email" />
                            <x-input name="guardian_occupation" label="Occupation" :value="$guardian?->occupation" />
                            <x-input name="guardian_address" label="Address" :value="$guardian?->address" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Record Status</h3></div></div>
                    <div class="form-section">
                        <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive', 'passed_out' => 'Passed Out', 'transferred' => 'Transferred', 'archived' => 'Archived']" :selected="$student->status" required />
                        <div class="info-box" style="margin-top:12px">Changing status here updates the student record only. Use the profile status action to also update the current enrolment.</div>
                    </div>
                    <div class="form-section" style="display:flex;flex-direction:column;gap:8px">
                        <x-button type="submit" variant="primary" icon="save" class="btn-block">Save student</x-button>
                        <x-button :href="route('students.show', $student)" variant="ghost" class="btn-block">Cancel</x-button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
