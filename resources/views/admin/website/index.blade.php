@extends('layouts.app')

@section('title', $section['title'] ?? 'Website Content')

@php
    $sectionAction = $activeSection ? route('website-admin.'.$activeSection) : route('website-content.index');
    $createLabel = match ($section['type'] ?? null) {
        'slider' => 'New slide',
        'academic' => 'New academic card',
        'gallery' => 'Upload gallery image',
        'page' => 'New page',
        'download' => 'Add download',
        'faculty' => 'Add faculty profile',
        'admission_info' => 'Add admission info',
        'routine' => 'Add routine',
        'academic_calendar' => 'Add calendar entry',
        'syllabus' => 'Add syllabus',
        'assignment' => 'Add assignment',
        'video' => 'Add video',
        'administration_message' => 'Add message',
        default => 'New content',
    };
@endphp

@section('content')
    <x-page-header eyebrow="Website content" :title="$section['title'] ?? 'Content Library'" :subtitle="$section['subtitle'] ?? 'Manage every public section, page, news item and media-backed content block. Drafts are never shown publicly.'">
        <x-slot:actions>
            @can('website.manage')
                <x-button :href="route('website-content.create', $section ? ['type' => $section['type']] : [])" variant="primary" icon="plus">{{ $createLabel }}</x-button>
            @endcan
            <x-button :href="route('website-admin.index')" variant="secondary" icon="layout-dashboard">Website dashboard</x-button>
            <x-button :href="route('messages.index')" variant="secondary" icon="mail">Messages</x-button>
        </x-slot:actions>
    </x-page-header>


    @if ($section)
        <div class="info-box" style="margin-bottom:16px">
            <strong>{{ $section['title'] }}:</strong> {{ $section['subtitle'] }}.
            Use sort order to control the public display order.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ $section['title'] ?? 'Content Library' }}</h3><p class="card-sub">{{ $items->total() }} item(s)</p></div>
            <form method="GET" action="{{ $sectionAction }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search title">
                </div>
                @if ($section)
                    <input type="hidden" name="type" value="{{ $section['type'] }}">
                @else
                    <div class="field">
                        <select class="select" name="type">
                            <option value="">Any type</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="field">
                    <select class="select" name="status">
                        <option value="">Any status</option>
                        @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Published</th><th>Updated</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <div class="cell-title">{{ $item->title }}</div>
                                <div class="cell-sub">/{{ $item->slug }}@if ($item->category) · {{ $item->category }}@endif</div>
                            </td>
                            <td>{{ $types[$item->type] ?? $item->type }}</td>
                            <td>
                                <x-badge :color="$item->status === 'published' ? 'success' : ($item->status === 'draft' ? 'warning' : 'neutral')">{{ ucfirst($item->status) }}</x-badge>
                            </td>
                            <td class="cell-sub">{{ $item->published_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="cell-sub">{{ $item->updated_at?->format('d M Y') }}</td>
                            <td class="actions">
                                @can('website.manage')
                                    @if ($item->public_url && $item->status === 'published')
                                        <a class="btn btn-secondary btn-sm" href="{{ $item->public_url }}" target="_blank" rel="noopener">Preview</a>
                                    @endif
                                    <a class="btn btn-secondary btn-sm" href="{{ route('website-content.edit', $item) }}">Edit</a>
                                    @if ($item->status !== 'archived')
                                        <form method="POST" action="{{ route('website-content.archive', $item) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm">Archive</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('website-content.destroy', $item) }}" onsubmit="return confirm('Permanently delete this content?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No content in this section yet. Use the button above to create the first item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links() }}
    </div>
@endsection
