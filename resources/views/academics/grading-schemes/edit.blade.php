@extends('layouts.app')

@section('title', 'Edit Grading Scheme')

@section('content')
    <x-page-header eyebrow="Academics" :title="$scheme->name" subtitle="Grade boundaries are stored with every published result, so later edits never change historical grade points.">
        <x-slot:actions>
            <x-button :href="route('grading-schemes.index')" variant="secondary" icon="arrow-left">Grading schemes</x-button>
            <x-button :href="route('exams.index')" variant="secondary" icon="file-text">Examinations</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('grading-schemes.update', $scheme) }}">
        @csrf
        @method('PUT')

        <div class="grid-2">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Scheme details</h3><p class="card-sub">Name, scope and default behaviour</p></div></div>
                <div class="form-section">
                    <div class="form-grid">
                        <x-input name="name" label="Scheme name" :value="$scheme->name" required maxlength="120" />
                        <x-input name="exam_type" label="Examination type" :value="$scheme->exam_type" placeholder="ssc, hsc, term" help="Used as a fallback when an exam has no explicit scheme." />
                        <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')->prepend('Any session', '')" :selected="$scheme->academic_session_id" />
                        <div class="span-2"><x-textarea name="description" label="Description" rows="2" :value="$scheme->description" /></div>
                        <div>
                            <input type="hidden" name="is_default" value="0">
                            <label class="checkbox"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $scheme->is_default))> <span>Default for this session</span></label>
                        </div>
                        <div>
                            <input type="hidden" name="is_active" value="0">
                            <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $scheme->is_active))> <span>Active</span></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Usage</h3><p class="card-sub">Where this scheme applies</p></div></div>
                <div class="card-body">
                    <div class="metric-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
                        <div class="stat" style="box-shadow:none;border:1px solid var(--border)"><div class="stat-label">Examinations</div><div class="stat-value" style="font-size:24px">{{ $scheme->exams->count() }}</div></div>
                        <div class="stat" style="box-shadow:none;border:1px solid var(--border)"><div class="stat-label">Grade rules</div><div class="stat-value" style="font-size:24px">{{ $scheme->rules->count() }}</div></div>
                    </div>
                    <p class="muted" style="font-size:12px;margin-top:14px">
                        Boundaries must cover 0% to 100% without gaps or overlaps. Every result publication stores a copy of these rules.
                    </p>
                    @if ($scheme->exams->isNotEmpty())
                        <div class="warning-box" style="margin-bottom:0">This scheme is assigned to {{ $scheme->exams->count() }} examination(s). Changes apply to future publications; existing snapshots are unchanged.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:18px">
            <div class="card-header"><div><h3 class="card-title">Grade boundaries</h3><p class="card-sub">Percentages are rounded to two decimals. Use 100 as the top boundary and 0 as the bottom.</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th style="width:90px">Order</th><th>Label</th><th>Minimum %</th><th>Maximum %</th><th>Grade</th><th>Grade point</th><th>Fail</th></tr></thead>
                    <tbody>
                        @foreach ($scheme->rules as $index => $rule)
                            <tr>
                                <td class="mono">{{ $index + 1 }}</td>
                                <td><input class="input" name="rules[{{ $index }}][name]" value="{{ old('rules.'.$index.'.name', $rule->name) }}" required maxlength="40" aria-label="Grade label"></td>
                                <td><input class="input" type="number" step="0.01" min="0" max="100" name="rules[{{ $index }}][min_percent]" value="{{ old('rules.'.$index.'.min_percent', $rule->min_percent) }}" required aria-label="Minimum percent"></td>
                                <td><input class="input" type="number" step="0.01" min="0" max="100" name="rules[{{ $index }}][max_percent]" value="{{ old('rules.'.$index.'.max_percent', $rule->max_percent) }}" required aria-label="Maximum percent"></td>
                                <td><input class="input" name="rules[{{ $index }}][grade]" value="{{ old('rules.'.$index.'.grade', $rule->grade) }}" required maxlength="10" aria-label="Grade"></td>
                                <td><input class="input" type="number" step="0.01" min="0" max="10" name="rules[{{ $index }}][grade_point]" value="{{ old('rules.'.$index.'.grade_point', $rule->grade_point) }}" required aria-label="Grade point"></td>
                                <td>
                                    <input type="hidden" name="rules[{{ $index }}][is_fail]" value="0">
                                    <label class="checkbox"><input type="checkbox" name="rules[{{ $index }}][is_fail]" value="1" @checked(old('rules.'.$index.'.is_fail', $rule->is_fail))> <span>Fail</span></label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('rules')<div class="form-section"><div class="alert alert-error">{{ $message }}</div></div>@enderror
            <div class="card-footer" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div>
                    @if (! $scheme->is_default && $scheme->exams->isEmpty())
                        <button class="btn btn-danger btn-sm" type="submit" form="delete-scheme-form">Delete scheme</button>
                    @endif
                </div>
                <div style="display:flex;gap:8px">
                    <x-button :href="route('grading-schemes.index')" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary" icon="save">Save scheme</x-button>
                </div>
            </div>
        </div>
    </form>

    @if (! $scheme->is_default && $scheme->exams->isEmpty())
        <form id="delete-scheme-form" method="POST" action="{{ route('grading-schemes.destroy', $scheme) }}" onsubmit="return confirm('Delete this grading scheme? This cannot be undone.')">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
