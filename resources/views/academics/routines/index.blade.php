@extends('layouts.app')

@section('title', __('Class Routine'))

@section('content')
    <x-page-header eyebrow="{{ __('Academic management') }}" title="{{ __('Class Routine') }}"
        subtitle="{{ __('Build the official timetable with days as rows and time slots as columns. Every routine belongs to one academic year, class and section.') }}">
        <x-slot:actions>
            @can('routines.edit')
                <x-button :href="route('routines.settings')" variant="secondary" icon="clock">{{ __('Class times') }}</x-button>
            @endcan
            @can('routines.create')
                <x-button :href="route('routines.create')" variant="primary" icon="plus">{{ __('Create routine') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (! $hasTimeSlots)
        <div class="info-box" style="margin-bottom:16px">
            {{ __('No active time slots are configured. Add periods under') }}
            @can('routines.edit')<a href="{{ route('routines.settings') }}">{{ __('Days & time slots') }}</a>@else {{ __('Days & time slots') }} @endcan
            {{ __('before creating a timetable.') }}
        </div>
    @endif

    <div class="info-box" style="margin-bottom:16px">
        <strong>{{ __('Quick setup:') }}</strong> {{ __('choose the academic year, class and section when creating a routine. Then open it, click an empty period, and select the subject and teacher. Change a class\'s hours from') }} <strong>{{ __('Class times') }}</strong>.
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ __('Routine manager') }}</h3>
                <p class="card-sub">{{ __(':count routine(s), one per academic year, class and section', ['count' => $routines->total()]) }}</p>
            </div>
            <form method="GET" action="{{ route('routines.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <label class="field-label" for="session_id">{{ __('Academic year') }}</label>
                    <select class="select" id="session_id" name="session_id">
                        <option value="">{{ __('All years') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}" @selected((string) $selectedSessionId === (string) $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="class_id">{{ __('Class') }}</label>
                    <select class="select" id="class_id" name="class_id">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="section_id">{{ __('Section') }}</label>
                    <select class="select" id="section_id" name="section_id">
                        <option value="">{{ __('All sections') }}</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected(request('section_id') == $section->id)>{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Academic year') }}</th>
                        <th>{{ __('Class') }}</th>
                        <th>{{ __('Section') }}</th>
                        <th>{{ __('Website') }}</th>
                        <th>{{ __('Effective from') }}</th>
                        <th class="num">{{ __('Periods') }}</th>
                        <th>{{ __('Updated') }}</th>
                        <th class="actions">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routines as $routine)
                        <tr>
                            <td>{{ $routine->academicSession?->name }}</td>
                            <td class="cell-title">{{ $routine->class?->name }}</td>
                            <td>{{ $routine->section?->name }}</td>
                            <td>
                                @if ($routine->is_published)
                                    <span class="badge badge-success">{{ __('Visible') }}</span>
                                @else
                                    <span class="badge badge-muted">{{ __('Hidden') }}</span>
                                @endif
                            </td>
                            <td class="cell-sub">{{ $routine->effective_from?->format('d M Y') ?? '-' }}</td>
                            <td class="num">{{ $routine->entries_count }}</td>
                            <td class="cell-sub">{{ $routine->updated_at?->format('d M Y, H:i') }}</td>
                            <td class="actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('routines.show', $routine) }}">{{ __('View') }}</a>
                                @can('routines.edit')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.edit', $routine) }}">{{ __('Edit') }}</a>
                                @endcan
                                @can('routines.copy')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.copy', $routine) }}">{{ __('Copy') }}</a>
                                @endcan
                                @can('routines.print')
                                    <button type="button" class="btn btn-secondary btn-sm" data-routine-preview data-preview-url="{{ route('routines.print', ['routine' => $routine, 'embed' => 1]) }}" data-preview-pdf-url="{{ route('routines.pdf', $routine) }}">{{ __('Print') }}</button>
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.pdf', $routine) }}">{{ __('Download PDF') }}</a>
                                @endcan
                                @can('routines.edit')
                                    <form method="POST" action="{{ route('routines.website-toggle', $routine) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-{{ $routine->is_published ? 'secondary' : 'primary' }} btn-sm">
                                            {{ $routine->is_published ? __('Hide') : __('Show on website') }}
                                        </button>
                                    </form>
                                @endcan
                                @can('routines.delete')
                                    <form method="POST" action="{{ route('routines.destroy', $routine) }}" onsubmit="return confirm(@js(__('Delete this routine and every timetable cell in it?')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="{{ __('Delete routine') }}"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty">{{ __('No class routines match these filters. Create one to start the timetable.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $routines->links() }}
    </div>
    @include('academics.routines._preview-modal')
@endsection
