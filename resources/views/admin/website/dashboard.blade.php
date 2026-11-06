@extends('layouts.app')

@section('title', 'Website Dashboard')

@php
    $sectionIcons = [
        'slider' => 'eye',
        'introduction' => 'book-open',
        'administration-message' => 'message-circle',
        'principal-message' => 'user',
        'president-message' => 'shield',
        'academic-corner' => 'graduation-cap',
        'news' => 'file-text',
        'events' => 'calendar',
        'gallery' => 'eye',
        'pages' => 'file-text',
        'downloads' => 'download',
        'faculty' => 'users',
        'admission-info' => 'user-plus',
        'class-routine' => 'list',
        'academic-calendar' => 'calendar',
        'syllabus' => 'file-text',
        'video-gallery' => 'video',
    ];
@endphp

@section('content')
    <x-page-header eyebrow="Website" title="Website Content Management"
        subtitle="Every public section has its own function. Publish content without touching code, and keep drafts private until they are ready.">
        <x-slot:actions>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
            @can('website.manage')
                <x-button :href="route('website-admin.design')" variant="primary" icon="settings">Design &amp; logo</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>


    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Published" :value="number_format($published)" icon="check-circle" tone="collection" meta="Visible on the public site" />
        <x-stat-card label="Drafts" :value="number_format($draft)" icon="file-text" tone="pending" meta="Private until published" />
        <x-stat-card label="New messages" :value="number_format($newMessages)" icon="mail" tone="student" meta="Contact enquiries" />
        <x-stat-card label="Menus" :value="($hasHeaderMenu ? 'Header' : '—').' / '.($hasFooterMenu ? 'Footer' : '—')" icon="menu" tone="expense" meta="Public navigation" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Homepage and section functions</h3><p class="card-sub">Open a section to create, edit, archive or publish its content</p></div>
            @can('website.manage')
                <x-button :href="route('website-content.create')" variant="secondary" icon="plus">All content</x-button>
            @endcan
        </div>
        <div class="card-body">
            <div class="grid-3" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                @foreach ($sections as $key => $section)
                    @php $count = (int) ($counts[$section['type']] ?? 0); @endphp
                    <a href="{{ route('website-admin.'.$key) }}" class="card" style="text-decoration:none;border:1px solid var(--border);box-shadow:none">
                        <div class="card-body" style="padding:16px">
                            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                                <span class="table-avatar" style="flex:none"><x-icon :name="$sectionIcons[$key] ?? 'file-text'" :size="17" /></span>
                                <x-badge color="{{ $count ? 'violet' : 'muted' }}">{{ $count }} item{{ $count === 1 ? '' : 's' }}</x-badge>
                            </div>
                            <h3 class="card-title" style="margin-top:12px">{{ $section['title'] }}</h3>
                            <p class="card-sub" style="margin:4px 0 0">{{ $section['subtitle'] }}</p>
                            <span class="section-link" style="display:inline-block;margin-top:10px">Manage section →</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @can('website.manage')
        <div class="card" style="margin-top:16px">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Home page sections</h3>
                    <p class="card-sub">Choose which blocks are visible on the public homepage. Unticked blocks are hidden without deleting their content.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('website-admin.home-sections.update') }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
                        @foreach ($homeSectionOptions as $key => $label)
                            <label class="checkbox" style="display:flex;gap:10px;align-items:center;border:1px solid var(--border);border-radius:10px;padding:12px">
                                <input type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key, $homeSectionsEnabled, true))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer" style="display:flex;justify-content:flex-end">
                    <x-button type="submit" variant="primary" icon="save">Save homepage sections</x-button>
                </div>
            </form>
        </div>
    @endcan

    <div class="grid-2" style="margin-top:16px">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Website tools</h3><p class="card-sub">Appearance, navigation and media</p></div></div>
            <div class="card-body">
                <div class="stack" style="gap:10px">
                    @can('website.manage')
                        <a href="{{ route('website-admin.design') }}" class="sidebar-link" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                            <x-icon name="settings" :size="17" class="nav-icon" /><span><strong>Design, logo &amp; header/footer</strong><br><small class="muted">Choose from four header and footer styles</small></span>
                        </a>
                    @endcan
                    @can('website.menus')
                        <a href="{{ route('website-menus.index') }}" class="sidebar-link" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                            <x-icon name="menu" :size="17" class="nav-icon" /><span><strong>Menu builder</strong><br><small class="muted">Header, footer, sidebar and secondary navigation</small></span>
                        </a>
                    @endcan
                    @can('website.manage')
                        <a href="{{ route('website-admin.sidebar') }}" class="sidebar-link" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                            <x-icon name="layout" :size="17" class="nav-icon" /><span><strong>Public sidebar</strong><br><small class="muted">Links shown in the page sidebar and mobile drawer</small></span>
                        </a>
                        <a href="{{ route('website-admin.result-search') }}" class="sidebar-link" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                            <x-icon name="search" :size="17" class="nav-icon" /><span><strong>Result search</strong><br><small class="muted">Publishing status and public result lookup</small></span>
                        </a>
                    @endcan
                    @can('website.media')
                        <a href="{{ route('media.index') }}" class="sidebar-link" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                            <x-icon name="eye" :size="17" class="nav-icon" /><span><strong>Media library</strong><br><small class="muted">Upload and reuse website images</small></span>
                        </a>
                    @endcan
                    <a href="{{ route('messages.index') }}" class="sidebar-link" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                        <x-icon name="mail" :size="17" class="nav-icon" /><span><strong>Contact messages</strong><br><small class="muted">{{ $newMessages }} unread message(s)</small></span>
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Recently updated</h3><p class="card-sub">Latest content across all sections</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Type</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($latest as $item)
                            <tr>
                                <td><a href="{{ route('website-content.edit', $item) }}" class="cell-title">{{ $item->title }}</a><div class="cell-sub">{{ $item->updated_at?->diffForHumans() }}</div></td>
                                <td>{{ \App\Models\WebsiteContent::TYPES[$item->type] ?? $item->type }}</td>
                                <td><x-badge :color="$item->status === 'published' ? 'success' : ($item->status === 'draft' ? 'warning' : 'muted')">{{ ucfirst($item->status) }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="table-empty">No content has been created yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
