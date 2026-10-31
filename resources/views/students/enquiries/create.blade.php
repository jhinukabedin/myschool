@extends('layouts.app')

@section('title', $enquiry->exists ? 'Edit Enquiry' : 'New Admission Enquiry')

@section('content')
    <x-page-header
        eyebrow="Students"
        :title="$enquiry->exists ? 'Edit Enquiry' : 'New Admission Enquiry'"
        subtitle="Capture the family's contact details and the next follow-up action."
    >
        <x-slot:actions>
            <x-button :href="route('enquiries.index')" variant="secondary" icon="arrow-left">Back to register</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form method="POST" action="{{ $enquiry->exists ? route('enquiries.update', $enquiry) : route('enquiries.store') }}">
            @csrf
            @if ($enquiry->exists) @method('PUT') @endif

            <div class="form-section">
                <div class="form-section-title">Prospective student</div>
                <div class="form-grid">
                    <x-input name="student_name" label="Student name" :value="$enquiry->student_name" required />
                    <x-select name="class_id" label="Class of interest" :options="$classes->pluck('name', 'id')" :selected="$enquiry->class_id" placeholder="Not sure yet" />
                    <x-input name="guardian_name" label="Guardian name" :value="$enquiry->guardian_name" required />
                    <x-input name="phone" label="Contact phone" :value="$enquiry->phone" required />
                    <x-input name="email" label="Email" type="email" :value="$enquiry->email" />
                    <x-input name="source" label="Enquiry source" :value="$enquiry->source" placeholder="Walk-in, Facebook, referral..." />
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">Follow-up</div>
                <div class="form-grid">
                    <x-select name="status" label="Status" :options="['new' => 'New', 'contacted' => 'Contacted', 'follow_up' => 'Follow-up', 'admitted' => 'Admitted', 'closed' => 'Closed']" :selected="$enquiry->status" required />
                    <x-input name="follow_up_date" label="Next follow-up date" type="date" :value="$enquiry->follow_up_date?->toDateString()" />
                    <div class="span-2">
                        <x-textarea name="notes" label="Notes" :value="$enquiry->notes" rows="4" placeholder="Conversation notes, preferred class, transport needs..." />
                    </div>
                </div>
            </div>

            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('enquiries.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $enquiry->exists ? 'Save changes' : 'Record enquiry' }}</x-button>
            </div>
        </form>
    </div>
@endsection
