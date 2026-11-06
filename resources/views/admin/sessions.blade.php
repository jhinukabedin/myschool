@extends('layouts.app')

@section('title', __('Academic Sessions'))

@section('content')
    <x-page-header eyebrow="{{ __('Admin') }}" title="{{ __('Academic Sessions') }}" subtitle="{{ __('Sessions scope students, fees, attendance, examinations and payroll. Only one session is current at a time.') }}">
        <x-slot:actions>
            <x-button :href="route('admin.index')" variant="secondary" icon="arrow-left">{{ __('Administration') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-side-main">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">{{ __('Create Session') }}</h3><p class="card-sub">{{ __('Setting it current deactivates the previous session') }}</p></div></div>
            <form method="POST" action="{{ route('sessions.store') }}">
                @csrf
                <div class="form-section">
                    <div class="stack">
                        <x-input name="name" :label="__('Session name')" required :placeholder="__('e.g. 2027')" />
                        <div class="form-grid">
                            <x-input name="start_date" :label="__('Start date')" type="date" required />
                            <x-input name="end_date" :label="__('End date')" type="date" required />
                        </div>
                        <x-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'closed' => __('Closed')]" :selected="'active'" required />
                        <label class="checkbox">
                            <input type="checkbox" name="is_current" value="1">
                            <span>{{ __('Make this the current academic session') }}</span>
                        </label>
                        <x-button type="submit" variant="primary" icon="plus" class="btn-block">{{ __('Create session') }}</x-button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">{{ __('Sessions') }}</h3><p class="card-sub">{{ $sessions->count() }} {{ __('configured session(s)') }}</p></div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>{{ __('Session') }}</th><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Enrolments') }}</th><th class="num">{{ __('Payments') }}</th><th class="num">{{ __('Exams') }}</th><th class="actions">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td>
                                    <div class="cell-title">{{ $session->name }}</div>
                                    @if ($session->is_current)<div class="cell-sub"><span class="badge badge-success">{{ __('Current') }}</span></div>@endif
                                </td>
                                <td class="muted">{{ $session->start_date?->format('d M Y') }} – {{ $session->end_date?->format('d M Y') }}</td>
                                <td><x-badge :color="$session->status === 'active' ? 'success' : 'muted'">{{ ucfirst($session->status) }}</x-badge></td>
                                <td class="num">{{ $session->enrollments_count }}</td>
                                <td class="num">{{ $session->payments_count }}</td>
                                <td class="num">{{ $session->examinations_count }}</td>
                                <td class="actions">
                                    @unless ($session->is_current)
                                        <form method="POST" action="{{ route('sessions.activate', $session) }}">
                                            @csrf
                                            <button type="submit" class="btn-icon" title="{{ __('Set as current') }}"><x-icon name="check-circle" :size="15" /></button>
                                        </form>
                                    @endunless
                                    <button type="button" class="btn-icon" title="{{ __('Edit') }}" @click="$dispatch('open-modal', 'session-{{ $session->id }}')"><x-icon name="edit" :size="15" /></button>
                                    @if (! $session->is_current)
                                        <form method="POST" action="{{ route('sessions.destroy', $session) }}" onsubmit="return confirm(@js(__('Delete this session? (Only empty sessions can be deleted.)')))" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">{{ __('No academic sessions have been created.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($sessions as $session)
        <x-modal name="session-{{ $session->id }}" :title="__('Edit session').' – '.$session->name">
            <form method="POST" action="{{ route('sessions.update', $session) }}">
                @csrf
                @method('PUT')
                <div class="stack">
                    <x-input name="name" :label="__('Session name')" :value="$session->name" required />
                    <div class="form-grid">
                        <x-input name="start_date" :label="__('Start date')" type="date" :value="$session->start_date?->toDateString()" required />
                        <x-input name="end_date" :label="__('End date')" type="date" :value="$session->end_date?->toDateString()" required />
                    </div>
                    <x-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'closed' => __('Closed')]" :selected="$session->status" required />
                    <label class="checkbox">
                        <input type="checkbox" name="is_current" value="1" @checked($session->is_current)>
                        <span>{{ __('Make this the current academic session') }}</span>
                    </label>
                    <x-button type="submit" variant="primary" icon="save" class="btn-block">{{ __('Save session') }}</x-button>
                </div>
            </form>
        </x-modal>
    @endforeach
@endsection
