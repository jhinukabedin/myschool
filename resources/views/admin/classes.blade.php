@extends('layouts.app')

@section('title', 'Classes')

@section('content')
    <x-page-header eyebrow="Academic management" title="Classes" subtitle="Create and order classes by numeric level. Sections are shared and mapped per class.">
        <x-slot:actions>
            <x-button :href="route('sections.index')" variant="secondary" icon="list-checks">Manage sections</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="grid-side-main">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Create Class</h3><p class="card-sub">Assign the sections offered by this class</p></div></div>
            <form method="POST" action="{{ route('classes.store') }}">
                @csrf
                <div class="form-section">
                    <div class="stack">
                        <x-input name="name" label="Class name" required placeholder="e.g. Class 11" />
                        <x-input name="numeric_level" label="Numeric level" type="number" min="0" max="99" value="0" required help="Used for ordering and the next-class suggestion during promotion." />
                        <label class="field">
                            <span class="field-label">Sections</span>
                            <select class="select" name="sections[]" multiple style="height:auto;min-height:84px">
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }} ({{ $section->enrollments_count }} students)</option>
                                @endforeach
                            </select>
                            <span class="field-hint">Hold Ctrl (Windows) or Command to select multiple.</span>
                        </label>
                        <x-button type="submit" variant="primary" icon="plus" class="btn-block">Create class</x-button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Configured Classes</h3><p class="card-sub">{{ $classes->count() }} class(es)</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Class</th><th class="num">Level</th><th>Sections</th><th class="num">Enrolments</th><th class="num">Fee structures</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($classes as $class)
                            <tr>
                                <td class="cell-title">{{ $class->name }}</td>
                                <td class="num">{{ $class->numeric_level }}</td>
                                <td>{{ $class->sections->pluck('name')->join(', ') ?: '—' }}</td>
                                <td class="num">{{ $class->enrollments_count }}</td>
                                <td class="num">{{ $class->fee_structures_count }}</td>
                                <td class="actions">
                                    <button type="button" class="btn-icon" title="Edit" @click="$dispatch('open-modal', 'class-{{ $class->id }}')"><x-icon name="edit" :size="15" /></button>
                                    <form method="POST" action="{{ route('classes.destroy', $class) }}" onsubmit="return confirm(@js(__('Delete this class and its dependent records?')))" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">No classes have been configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($classes as $class)
        @php($classSectionIds = $class->sections->pluck('id')->all())
        <x-modal name="class-{{ $class->id }}" :title="'Edit class · '.$class->name">
            <form method="POST" action="{{ route('classes.update', $class) }}">
                @csrf
                @method('PUT')
                <div class="stack">
                    <x-input name="name" label="Class name" :value="$class->name" required />
                    <x-input name="numeric_level" label="Numeric level" type="number" min="0" max="99" :value="$class->numeric_level" required />
                    <label class="field">
                        <span class="field-label">Sections</span>
                        <select class="select" name="sections[]" multiple style="height:auto;min-height:84px">
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" @selected(in_array($section->id, $classSectionIds))>{{ $section->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-button type="submit" variant="primary" icon="save" class="btn-block">Save class</x-button>
                </div>
            </form>
        </x-modal>
    @endforeach
@endsection
