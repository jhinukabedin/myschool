@extends('layouts.app')

@section('title', $subject->exists ? 'Edit Subject' : 'New Subject')

@section('content')
    <x-page-header eyebrow="Academics" :title="$subject->exists ? 'Edit Subject' : 'New Subject'" subtitle="Subjects can be mapped to multiple classes and used across examinations.">
        <x-slot:actions>
            <x-button :href="route('subjects.index')" variant="secondary" icon="arrow-left">Subjects</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $subject->exists ? route('subjects.update', $subject) : route('subjects.store') }}">
        @csrf
        @if ($subject->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Subject Details</h3><p class="card-sub">Codes must be unique</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-input name="code" label="Subject code" :value="$subject->code" required placeholder="e.g. MAT" />
                    <x-select name="type" label="Subject type" :options="['theory' => 'Theory', 'practical' => 'Practical', 'both' => 'Theory & practical']" :selected="$subject->type" required />
                    <div class="span-2"><x-input name="name" label="Subject name" :value="$subject->name" required placeholder="e.g. Mathematics" /></div>
                    <x-input name="full_marks" label="Default full marks" type="number" step="0.01" min="1" max="999" :value="$subject->full_marks" required />
                    <x-input name="pass_marks" label="Default pass marks" type="number" step="0.01" min="0" max="999" :value="$subject->pass_marks" required help="Must not exceed the full marks." />
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('subjects.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $subject->exists ? 'Save subject' : 'Create subject' }}</x-button>
            </div>
        </div>
    </form>
@endsection
