@extends('layouts.app')

@section('title', 'Header Area')

@php
    $v = fn (string $key, mixed $default = null) => old($key, $settings[$key] ?? $default);
    $datePositions = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'];
@endphp

@section('content')
    <x-page-header eyebrow="Website content" title="Header Area"
        subtitle="Configure the public website header: date/time, login buttons and the main navigation.">
        <x-slot:actions>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="arrow-left">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    <form method="POST" action="{{ route('website-admin.header.update') }}">
        @csrf
        @method('PUT')

        <div class="grid-main-side">
            <div class="stack">
                {{-- Header top area --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Header Top Area</h3>
                            <p class="card-sub">Thin bar above the main header: date/time on the left, login buttons on the right.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <div class="field">
                                    <input type="hidden" name="website_show_date" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="website_show_date" value="1" @checked($v('website_show_date') === '1')>
                                        <span><strong>Show Date &amp; Time in the header top bar</strong></span>
                                    </label>
                                    <p class="field-hint">When disabled, the whole date/time block is hidden. The date follows School Settings → Date &amp; Calendar Settings.</p>
                                </div>
                            </div>
                            <div class="span-2">
                                <x-select name="website_header_date_position" label="Date position" :options="$datePositions" :selected="$v('website_header_date_position', 'left')" :placeholder="false" />
                            </div>

                            <div class="span-2">
                                <div class="field">
                                    <input type="hidden" name="website_show_login_menu" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="website_show_login_menu" value="1" @checked($v('website_show_login_menu') === '1')>
                                        <span><strong>Show the login menu on the right</strong></span>
                                    </label>
                                    <p class="field-hint">Enabled login buttons appear as bordered buttons on the public site.</p>
                                </div>
                            </div>
                            <x-input name="website_header_login_label" label="Default login button label" :value="$v('website_header_login_label', 'Student Login')" placeholder="Student Login" />
                            <x-input name="website_header_login_border_radius" label="Button border radius (px)" type="number" min="0" max="50" :value="$v('website_header_login_border_radius', '10')" help="Default 10px." />
                        </div>
                    </div>
                </div>

                {{-- Login menu items --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Login Menu</h3>
                            <p class="card-sub">List of login buttons shown on the right of the header top bar.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="stack">
                            @if ($loginMenu && $loginMenu->rootItems->isNotEmpty())
                                <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr><th>Menu name</th><th>URL</th><th style="width:110px">Status</th><th class="actions" style="width:150px">Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($loginMenu->rootItems as $item)
                                            <tr>
                                                <td>{{ $item->title }}</td>
                                                <td><code class="cell-clamp" title="{{ $item->url }}">{{ $item->url }}</code></td>
                                                <td><x-badge :color="$item->is_active ? 'success' : 'muted'">{{ $item->is_active ? 'Enabled' : 'Disabled' }}</x-badge></td>
                                                <td class="actions">
                                                    <form method="POST" action="{{ route('website-menus.items.update', $item) }}">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="title" value="{{ $item->title }}">
                                                        <input type="hidden" name="url" value="{{ $item->url }}">
                                                        @if ($item->route_name)<input type="hidden" name="route_name" value="{{ $item->route_name }}">@endif
                                                        <input type="hidden" name="parent_id" value="{{ $item->parent_id }}">
                                                        <input type="hidden" name="target" value="{{ $item->target }}">
                                                        <input type="hidden" name="sort_order" value="{{ $item->sort_order }}">
                                                        <input type="hidden" name="is_active" value="{{ $item->is_active ? '0' : '1' }}">
                                                        <button type="submit" class="btn-icon" title="{{ $item->is_active ? 'Disable' : 'Enable' }}">
                                                            <x-icon :name="$item->is_active ? 'lock' : 'check-circle'" :size="15" />
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('website-menus.items.destroy', $item) }}" onsubmit="return confirm('Delete this login button?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn-icon" title="Delete login button"><x-icon name="trash" :size="15" /></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                </div>
                            @else
                                <div class="info-box">No login menu items yet. Add the first one below.</div>
                            @endif

                            <hr style="border:0;border-top:1px solid var(--border);margin:4px 0">

                            @can('website.menus')
                                <form method="POST" action="{{ route('website-menus.store') }}">
                                    @csrf
                                    <input type="hidden" name="name" value="Login menu">
                                    <input type="hidden" name="location" value="login">
                                    <input type="hidden" name="is_active" value="1">
                                    @if (! $loginMenu)
                                        <x-button type="submit" variant="outline" icon="plus">Create login menu</x-button>
                                    @endif
                                </form>

                                @if ($loginMenu)
                                    <form method="POST" action="{{ route('website-menus.items.store', $loginMenu) }}">
                                        @csrf
                                        <div class="form-grid">
                                            <x-input name="title" label="Menu name" placeholder="e.g. Student Login" required />
                                            <x-input name="url" label="URL" placeholder="/student/login" required />
                                        </div>
                                        <input type="hidden" name="is_active" value="1">
                                        <x-button type="submit" variant="outline" icon="plus">Add login menu item</x-button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>

                {{-- Main menu --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Main Menu Setting</h3>
                            <p class="card-sub">WordPress-style main navigation with sub menus.</p>
                        </div>
                        <x-button :href="route('website-menus.index')" variant="secondary" size="sm" icon="settings">Manage menus</x-button>
                    </div>
                    <div class="card-body">
                        @if ($headerMenu)
                            <p class="card-sub">Active header menu: <strong>{{ $headerMenu->name }}</strong> ({{ $headerMenu->is_active ? 'enabled' : 'disabled' }})</p>
                            <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Menu name</th><th>URL</th><th style="white-space:normal">Sub menus</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($headerMenu->rootItems as $item)
                                        <tr>
                                            <td>{{ $item->title }}</td>
                                            <td><code class="cell-clamp" title="{{ $item->route_name ? route($item->route_name) : $item->url }}">{{ $item->route_name ? route($item->route_name) : $item->url }}</code></td>
                                            <td style="white-space:normal">
                                                @forelse ($item->children as $child)
                                                    <div style="font-size:12px">{{ $child->title }} — <code>{{ $child->url }}</code></div>
                                                @empty
                                                    <span class="muted">—</span>
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                            <p class="field-hint" style="margin-top:10px">Add, edit, reorder and delete menu items in the menu builder. Changes appear on the public site immediately.</p>
                        @else
                            <div class="info-box">No custom header menu is active. The public site uses its built-in navigation. Create one in <a href="{{ route('website-menus.index') }}">Menus</a>.</div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <x-button type="submit" variant="primary" icon="save" class="btn-block">Save Header Area Settings</x-button>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Preview</h3><p class="card-sub">Header top area on the public site.</p></div></div>
                    <div class="card-body">
                        <div style="border:1px solid var(--border);border-radius:10px;padding:16px;background:#f8fafc">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid var(--border);padding-bottom:12px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="brand-mark" style="width:34px;height:34px">HS</div>
                                    <strong style="font-size:13px">School name</strong>
                                </div>
                                <div style="font-size:12px;color:#667085;flex:1;text-align:{{ $v('website_header_date_position', 'left') }}">
                                    @if ($v('website_show_date') === '1')
                                        {{ now()->format('l, d F Y') }} • {{ now()->format('h:i A') }}
                                    @else
                                        <span class="muted">Date &amp; Time off</span>
                                    @endif
                                </div>
                                <div style="display:flex;gap:6px">
                                    @if ($v('website_show_login_menu') === '1')
                                        <span style="border:1px solid #c7d2e1;border-radius:{{ $v('website_header_login_border_radius', '10') }}px;padding:5px 12px;font-size:12px;background:#fff">{{ $v('website_header_login_label', 'Student Login') }}</span>
                                    @else
                                        <span class="muted" style="font-size:12px">Login menu off</span>
                                    @endif
                                </div>
                            </div>
                            <div style="padding-top:12px;display:flex;gap:14px;font-size:12px;color:#385d79">
                                <span>Home</span><span>About</span><span>Admission</span><span>Contact</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection