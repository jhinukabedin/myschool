@extends('layouts.app')

@section('title', 'Sections')

@section('content')
    <x-page-header eyebrow="Admin" title="Sections" subtitle="Sections are shared across classes. A section with enrolments cannot be deleted.">
        <x-slot:actions>
            <x-button :href="route('classes.index')" variant="secondary" icon="building">Classes</x-button>
            <x-button :href="route('admin.index')" variant="ghost" icon="arrow-left">Administration</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="grid-side-main">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Add Section</h3><p class="card-sub">Names must be unique</p></div></div>
            <form method="POST" action="{{ route('sections.store') }}">
                @csrf
                <div class="form-section">
                    <div class="stack">
                        <x-input name="name" label="Section name" required placeholder="e.g. C" maxlength="40" />
                        <x-button type="submit" variant="primary" icon="plus" class="btn-block">Add section</x-button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Sections</h3><p class="card-sub">{{ $sections->count() }} section(s)</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Section</th><th class="num">Classes</th><th class="num">Enrolments</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($sections as $section)
                            <tr>
                                <td class="cell-title">{{ $section->name }}</td>
                                <td class="num">{{ $section->classes_count }}</td>
                                <td class="num">{{ $section->enrollments_count }}</td>
                                <td class="actions">
                                    <button type="button" class="btn-icon" title="Edit" @click="$dispatch('open-modal', 'section-{{ $section->id }}')"><x-icon name="edit" :size="15" /></button>
                                    @if ($section->enrollments_count === 0)
                                        <form method="POST" action="{{ route('sections.destroy', $section) }}" onsubmit="return confirm('Delete this section?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Delete"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-empty">No sections have been created.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($sections as $section)
        <x-modal name="section-{{ $section->id }}" :title="'Edit section · '.$section->name">
            <form method="POST" action="{{ route('sections.update', $section) }}">
                @csrf
                @method('PUT')
                <div class="stack">
                    <x-input name="name" label="Section name" :value="$section->name" required maxlength="40" />
                    <x-button type="submit" variant="primary" icon="save" class="btn-block">Save section</x-button>
                </div>
            </form>
        </x-modal>
    @endforeach
@endsection
