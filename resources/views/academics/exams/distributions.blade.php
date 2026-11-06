@extends('layouts.app')

@section('title', 'Marks Distribution')

@section('content')
    <x-page-header eyebrow="Examinations" title="Subject-wise Marks Distribution" subtitle="Set reusable global defaults and class-specific overrides. Existing exams keep the distribution snapshot stored on the exam.">
        <x-slot:actions><x-button :href="route('exams.index')" variant="secondary" icon="arrow-left">Examinations</x-button></x-slot:actions>
    </x-page-header>

    <div class="tabs" role="tablist">
        <a class="tab {{ $scope === 'global' ? 'active' : '' }}" href="{{ route('exams.distributions', ['scope' => 'global']) }}">Global defaults</a>
        <a class="tab {{ $scope === 'class' ? 'active' : '' }}" href="{{ route('exams.distributions', ['scope' => 'class']) }}">Class overrides</a>
    </div>

    <div class="card">
        <div class="card-header"><div><h3 class="card-title">{{ $editing ? 'Edit' : 'Add' }} {{ $scope === 'global' ? 'global default' : 'class override' }}</h3><p class="card-sub">Component full marks must sum to the subject full marks. Optional component pass requirements are enforced separately.</p></div></div>
        <form method="POST" action="{{ route('exams.distributions.store') }}" x-data="{ rows: @js(old('components', $editing?->components ?? [])) }">
            @csrf
            <input type="hidden" name="scope" value="{{ $scope }}">
            <div class="form-section">
                <div class="form-grid">
                    @if($scope === 'class')
                        <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" :selected="old('class_id', $editing?->class_id)" required onchange="filterDistributionSubjects()" />
                    @endif
                    <label class="field"><span class="field-label">Subject</span><select class="select" id="distribution-subject" name="subject_id" required><option value="">Select subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" data-class-ids="{{ implode(',', $classSubjectMap->get($subject->id, collect())->all()) }}" @selected((string)old('subject_id', $editing?->subject_id) === (string)$subject->id)>{{ $subject->name }}</option>@endforeach</select></label>
                    <x-input name="full_marks" label="Full marks" type="number" step="0.01" min="0.01" max="999" :value="old('full_marks', $editing?->full_marks ?? 100)" required />
                    <x-input name="pass_marks" label="Overall pass marks" type="number" step="0.01" min="0" max="999" :value="old('pass_marks', $editing?->pass_marks ?? 33)" required />
                </div>
                @error('distribution')<div class="field-error" style="margin-top:12px">{{ $message }}</div>@enderror
                @error('components')<div class="field-error" style="margin-top:12px">{{ $message }}</div>@enderror
                <div class="distribution-component-list" style="margin-top:20px">
                    <div class="distribution-component-head"><div><div class="field-label">Marks components</div><p class="card-sub">Written, MCQ, Practical, Oral, Assignment, or another component.</p></div><button type="button" class="btn btn-secondary btn-sm" @click="rows.push({name:'',full_marks:'',pass_marks:'',requires_pass:false})">Add Component</button></div>
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="form-grid distribution-component-row">
                            <input class="input" type="text" :name="`components[${index}][name]`" x-model="row.name" maxlength="80" placeholder="Component name" required>
                            <input class="input" type="number" min="0.01" max="999" step="0.01" :name="`components[${index}][full_marks]`" x-model="row.full_marks" placeholder="Full marks" required>
                            <input class="input" type="number" min="0" max="999" step="0.01" :name="`components[${index}][pass_marks]`" x-model="row.pass_marks" placeholder="Component pass marks">
                            <label class="checkbox"><input type="checkbox" value="1" :name="`components[${index}][requires_pass]`" x-model="row.requires_pass"><span>Must pass</span></label>
                            <button type="button" class="btn-icon" title="Remove component" @click="rows.splice(index, 1)"><x-icon name="trash" :size="15" /></button>
                        </div>
                    </template>
                    <div x-show="rows.length === 0" class="empty-hint">No components: the subject is marked with one total score.</div>
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px"><x-button :href="route('exams.distributions', ['scope' => $scope])" variant="ghost">Cancel</x-button><x-button type="submit" variant="primary" icon="save">Save Configuration</x-button></div>
        </form>
    </div>

    <div class="grid-main-side">
        <section class="card"><div class="card-header"><div><h3 class="card-title">Global Defaults</h3><p class="card-sub">Used when no class override exists</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Subject</th><th>Distribution</th><th>Full</th><th>Pass</th><th></th></tr></thead><tbody>
            @forelse($globalDefaults as $config)<tr><td>{{ $config->subject?->name }}</td><td>{{ collect($config->components ?? [])->map(fn($component) => $component['name'].' '.$component['full_marks'])->implode(' + ') ?: 'Single total score' }}</td><td>{{ number_format((float)$config->full_marks, 2) }}</td><td>{{ number_format((float)$config->pass_marks, 2) }}</td><td class="actions"><a class="btn-icon" href="{{ route('exams.distributions', ['scope'=>'global','id'=>$config->id]) }}" title="Edit"><x-icon name="edit" :size="15" /></a><form method="POST" action="{{ route('exams.distributions.destroy') }}" onsubmit="return confirm('Remove this global default? Existing exams keep their snapshots.')">@csrf @method('DELETE')<input type="hidden" name="scope" value="global"><input type="hidden" name="id" value="{{ $config->id }}"><button class="btn-icon" type="submit" title="Remove"><x-icon name="trash" :size="15" /></button></form></td></tr>@empty<tr><td colspan="5" class="table-empty">No global defaults configured yet.</td></tr>@endforelse
        </tbody></table></div></section>
        <section class="card"><div class="card-header"><div><h3 class="card-title">Class Overrides</h3><p class="card-sub">Replace the global default for one class and subject</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Class</th><th>Subject</th><th>Distribution</th><th>Full</th><th>Pass</th><th></th></tr></thead><tbody>
            @forelse($classOverrides as $config)<tr><td>{{ $config->class?->name }}</td><td>{{ $config->subject?->name }}</td><td>{{ collect($config->components ?? [])->map(fn($component) => $component['name'].' '.$component['full_marks'])->implode(' + ') ?: 'Single total score' }}</td><td>{{ number_format((float)$config->full_marks, 2) }}</td><td>{{ number_format((float)$config->pass_marks, 2) }}</td><td class="actions"><a class="btn-icon" href="{{ route('exams.distributions', ['scope'=>'class','id'=>$config->id]) }}" title="Edit"><x-icon name="edit" :size="15" /></a><form method="POST" action="{{ route('exams.distributions.destroy') }}" onsubmit="return confirm('Remove this class override? Existing exams keep their snapshots.')">@csrf @method('DELETE')<input type="hidden" name="scope" value="class"><input type="hidden" name="id" value="{{ $config->id }}"><button class="btn-icon" type="submit" title="Remove"><x-icon name="trash" :size="15" /></button></form></td></tr>@empty<tr><td colspan="6" class="table-empty">No class overrides configured yet.</td></tr>@endforelse
        </tbody></table></div></section>
    </div>
@endsection

@push('scripts')
<script>
function filterDistributionSubjects() {
    const classField = document.getElementById('class_id');
    const subjectField = document.getElementById('distribution-subject');
    if (!classField || !subjectField) return;
    const classId = classField.value;
    for (const option of subjectField.options) {
        if (!option.value) continue;
        option.hidden = Boolean(classId) && !(option.dataset.classIds || '').split(',').includes(classId);
        if (option.hidden && option.selected) subjectField.value = '';
    }
}
document.addEventListener('DOMContentLoaded', filterDistributionSubjects);
</script>
@endpush
