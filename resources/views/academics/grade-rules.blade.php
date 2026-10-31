@extends('layouts.app')

@section('title', 'Grade Rules')

@section('content')
    <x-page-header eyebrow="Academics" title="Grade Rules" subtitle="Grade boundaries and grade points used when marks are entered and results are calculated.">
        <x-slot:actions>
            <x-button :href="route('grading-schemes.index')" variant="secondary" icon="sliders">Grading schemes</x-button>
            <x-button :href="route('class-subjects.index')" variant="secondary" icon="list-checks">Class-subject map</x-button>
        </x-slot:actions>
    </x-page-header>

    @can('exams.manage')
        <form method="POST" action="{{ route('grade-rules.update') }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">Grade Boundaries</h3><p class="card-sub">Percentages must not overlap or leave gaps</p></div>
                    <x-badge color="warning">Recalculation required</x-badge>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Grade</th><th>Min %</th><th>Max %</th><th>Grade label</th><th>Grade point</th><th>Fail grade</th></tr></thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td><span class="badge badge-violet">{{ $rule->name }}</span></td>
                                    <td><input class="input" type="number" step="0.01" min="0" max="100" name="rules[{{ $rule->id }}][min_percent]" value="{{ old('rules.'.$rule->id.'.min_percent', $rule->min_percent) }}" required></td>
                                    <td><input class="input" type="number" step="0.01" min="0" max="100" name="rules[{{ $rule->id }}][max_percent]" value="{{ old('rules.'.$rule->id.'.max_percent', $rule->max_percent) }}" required></td>
                                    <td><input class="input" type="text" maxlength="10" name="rules[{{ $rule->id }}][grade]" value="{{ old('rules.'.$rule->id.'.grade', $rule->grade) }}" required></td>
                                    <td><input class="input" type="number" step="0.01" min="0" max="10" name="rules[{{ $rule->id }}][grade_point]" value="{{ old('rules.'.$rule->id.'.grade_point', $rule->grade_point) }}" required></td>
                                    <td>
                                        <label class="checkbox">
                                            <input type="checkbox" name="rules[{{ $rule->id }}][is_fail]" value="1" @checked(old('rules.'.$rule->id.'.is_fail', $rule->is_fail))>
                                            <span>Counts as fail</span>
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                    <span class="muted">Existing marks keep the grade stored at entry time. Re-enter marks to apply new rules.</span>
                    <x-button type="submit" variant="primary" icon="save">Save grade rules</x-button>
                </div>
            </div>
        </form>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Grade</th><th class="num">Min %</th><th class="num">Max %</th><th class="num">Grade point</th><th>Fail</th></tr></thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            <tr>
                                <td><span class="badge badge-violet">{{ $rule->name }}</span></td>
                                <td class="num">{{ $rule->min_percent }}</td>
                                <td class="num">{{ $rule->max_percent }}</td>
                                <td class="num">{{ $rule->grade_point }}</td>
                                <td>{{ $rule->is_fail ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
@endsection
