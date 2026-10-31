@extends('layouts.app')

@section('title', 'Grading Schemes')

@section('content')
    <x-page-header eyebrow="Academics" title="Grading Schemes"
        subtitle="Configure SSC, HSC or school-specific grade boundaries. Published results keep the rules captured when they were released.">
        <x-slot:actions>
            <x-button :href="route('grade-rules.index')" variant="secondary" icon="sliders">Default grade rules</x-button>
            <x-button :href="route('exams.index')" variant="secondary" icon="file-text">Examinations</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-2">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Create a scheme</h3><p class="card-sub">Boundaries are copied from the default rules or an existing scheme and can then be edited.</p></div></div>
            <form method="POST" action="{{ route('grading-schemes.store') }}">
                @csrf
                <div class="form-section">
                    <div class="form-grid">
                        <x-input name="name" label="Scheme name" required placeholder="SSC 2026 · Science" />
                        <x-input name="exam_type" label="Examination type" placeholder="ssc, hsc, term" help="Optional; used as a fallback when an exam has no explicit scheme." />
                        <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')->prepend('Any session', '')" />
                        <x-select name="copy_rules_from" label="Copy boundaries from" :options="\App\Models\GradingScheme::query()->orderBy('name')->pluck('name', 'id')->prepend('Default (institution) rules', '')" />
                        <div class="span-2"><x-textarea name="description" label="Description" rows="2" /></div>
                        <div>
                            <input type="hidden" name="is_default" value="0">
                            <label class="checkbox"><input type="checkbox" name="is_default" value="1"> <span>Default scheme for this session</span></label>
                        </div>
                        <div>
                            <input type="hidden" name="is_active" value="0">
                            <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> <span>Active</span></label>
                        </div>
                    </div>
                </div>
                <div class="card-footer" style="display:flex;justify-content:flex-end">
                    <x-button type="submit" variant="primary" icon="plus">Create scheme</x-button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">How grading is chosen</h3><p class="card-sub">Resolution order for every result calculation</p></div></div>
            <div class="card-body">
                <ol style="margin:0;padding-left:20px;line-height:1.95;font-size:13px">
                    <li>The scheme explicitly assigned to the examination.</li>
                    <li>An active default matching the session and exam type.</li>
                    <li>The institution's default grade rules.</li>
                </ol>
                <div class="warning-box" style="margin-top:14px">
                    Editing a scheme does not rewrite published results. Each publication stores a grading snapshot for historical accuracy. Correcting marks after publication is audited separately.
                </div>
                <p class="muted" style="font-size:12px;margin-bottom:0">Marks with no entry are reported as Incomplete, not silently scored as zero. Exempt, withheld, incomplete, disqualified and absent statuses are handled by the result engine.</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-header"><div><h3 class="card-title">Schemes</h3><p class="card-sub">{{ $schemes->count() }} configured scheme(s)</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Scheme</th><th>Exam type</th><th>Session</th><th>Rules</th><th>Exams</th><th>Status</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($schemes as $scheme)
                        <tr>
                            <td>
                                <div class="cell-title">{{ $scheme->name }}</div>
                                @if ($scheme->description)<div class="cell-sub">{{ $scheme->description }}</div>@endif
                            </td>
                            <td>{{ $scheme->exam_type ?: 'Any' }}</td>
                            <td>{{ $scheme->academicSession?->name ?? 'Any session' }}</td>
                            <td>{{ $scheme->rules_count }}</td>
                            <td>{{ $scheme->exams_count }}</td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap">
                                    @if ($scheme->is_default)<x-badge color="info">Default</x-badge>@endif
                                    <x-badge :color="$scheme->is_active ? 'success' : 'neutral'">{{ $scheme->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </div>
                            </td>
                            <td class="actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('grading-schemes.edit', $scheme) }}">Open</a>
                                @if (! $scheme->is_default && $scheme->exams_count === 0)
                                    <form method="POST" action="{{ route('grading-schemes.destroy', $scheme) }}" onsubmit="return confirm('Delete this grading scheme?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state">No schemes yet. The institution's default grade rules are still used.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
