@extends('layouts.app')

@section('title', 'Subscription & Modules')

@php
    $moduleIcons = [
        'students' => 'users',
        'academics' => 'book-open',
        'attendance' => 'calendar-check',
        'exams' => 'graduation-cap',
        'fees' => 'circle-dollar',
        'finance' => 'credit-card',
        'staff' => 'briefcase',
        'payroll' => 'banknote',
        'communication' => 'bell',
        'reports' => 'pie-chart',
        'administration' => 'settings',
        'core' => 'shield',
        'website' => 'layout',
    ];
    $editable = $modules->where('core', false);
    $editableKeys = $editable->pluck('key')->values()->all();
    $enabledKeys = $editable->where('enabled', true)->pluck('key')->values()->all();
    $coreCount = $modules->where('core', true)->count();
    $totalCount = $modules->count();
    $menuKeysOn = $menusByModule->flatten(1)->where('core', false)->where('enabled', true)->pluck('key')->values()->all();
@endphp

@section('content')
    <x-page-header eyebrow="Administration" title="Subscription & Modules"
        subtitle="Enable a module, then switch individual menus on or off. A menu switched off stays visible to the school but opens as locked.">
        <x-slot:actions>
            @can('access.manage')
                <x-button :href="route('access.index')" variant="secondary" icon="users">User access</x-button>
            @endcan
            @can('users.manage')
                <x-button :href="route('roles.index')" variant="secondary" icon="key">Roles &amp; permissions</x-button>
            @endcan
            <x-button :href="route('dashboard')" variant="secondary" icon="layout-dashboard">Dashboard</x-button>
        </x-slot:actions>
    </x-page-header>

    <style>
        .module-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
        .acc-list{display:flex;flex-direction:column;gap:10px}
        .acc{border:1px solid var(--border);border-left:4px solid #dc2626;border-radius:12px;overflow:hidden;background:var(--surface,transparent)}
        .acc.is-on,.acc.is-core{border-left-color:#16a34a}
        .acc-head{display:flex;align-items:center;gap:14px;padding:14px 16px;cursor:pointer;user-select:none}
        .acc-head:hover{background:rgba(127,127,140,.06)}
        .acc-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;flex:none;background:rgba(220,38,38,.10);color:#b91c1c}
        .acc.is-on .acc-icon,.acc.is-core .acc-icon{background:rgba(22,163,74,.14);color:#15803d}
        .acc-title{flex:1;min-width:0}
        .acc-title strong{display:block;font-size:15px}
        .acc-title small{display:block;margin-top:2px;color:var(--muted,#6b7280);font-size:12.5px}
        .acc-status{font-size:12px;font-weight:700;white-space:nowrap;color:#b91c1c;display:inline-flex;align-items:center;gap:6px}
        .acc.is-on .acc-status,.acc.is-core .acc-status{color:#15803d}
        .acc-chevron{transition:transform .2s;color:var(--muted,#6b7280);flex:none}
        .acc-chevron.is-open{transform:rotate(180deg)}
        .acc-body{border-top:1px solid var(--border);padding:12px 16px 16px}
        .acc-body table{width:100%}
        .acc-body code{font-size:12px;word-break:break-all}
        .menu-table{border:1px solid var(--border);border-radius:11px;overflow:hidden;background:var(--surface,#fff)}
        .menu-table table{width:100%;border-collapse:collapse}
        .menu-table thead th{padding:9px 14px;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted,#6b7280);background:rgba(127,127,140,.06);border-bottom:1px solid var(--border);text-align:left;font-weight:700}
        .menu-table tbody tr+tr td{border-top:1px solid var(--border)}
        .menu-table tbody tr:hover td{background:rgba(124,92,255,.045)}
        .menu-table td{padding:9px 14px;vertical-align:middle;font-size:13.5px}
        .menu-cell{display:inline-flex;align-items:center;gap:9px;min-width:0}
        .menu-ico{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;background:rgba(124,92,255,.1);color:#6d4aff;flex:none}
        .menu-name{font-weight:600}
        .menu-col-switch{width:122px;text-align:center}
        .menu-col-link{width:150px;text-align:right}
        .menu-switch-wrap{display:inline-flex;align-items:center;gap:7px;transition:opacity .15s}
        .menu-switch-wrap.is-disabled{opacity:.45}
        .menu-switch-wrap.is-disabled .menu-switch{cursor:not-allowed}
        .acc-locked-note{margin:0 0 10px;padding:8px 11px;border-radius:8px;background:rgba(217,173,85,.12);border:1px solid rgba(217,173,85,.35);font-size:12px;color:#8a6116}
        .menu-switch{position:relative;display:inline-block;width:36px;height:20px;margin:0;cursor:pointer;flex:none}
        .menu-switch input{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
        .menu-track{position:absolute;inset:0;border-radius:999px;background:#dc2626;transition:background .18s}
        .menu-track::after{content:"";position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);transition:transform .18s}
        .menu-switch input:checked ~ .menu-track{background:#16a34a}
        .menu-switch input:checked ~ .menu-track::after{transform:translateX(16px)}
        .menu-switch input:focus-visible ~ .menu-track{outline:2px solid #7c5cff;outline-offset:2px}
        .menu-state{font-size:11px;font-weight:700;letter-spacing:.02em}
        .menu-state.is-on{color:#15803d}
        .menu-state.is-off{color:#b91c1c}
        .menu-always{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;color:#15803d;background:rgba(22,163,74,.12);border-radius:999px;padding:3px 8px}
        .menu-link-btn{border:0;background:none;padding:0;font:inherit;font-size:12px;font-weight:600;color:#6d4aff;cursor:pointer}
        .menu-link-btn:hover{text-decoration:underline}
        .menu-link-box{margin-top:6px;display:flex;flex-direction:column;align-items:flex-end;gap:3px}
        .menu-link-box code{font-size:10.5px;background:rgba(127,127,140,.1);padding:3px 6px;border-radius:6px;max-width:260px;overflow-wrap:anywhere;text-align:left}
        .menu-link-box a{font-size:11px;font-weight:600;color:#6d4aff}
        .acc-count{font-size:11px;font-weight:700;white-space:nowrap;color:var(--muted,#6b7280);background:rgba(127,127,140,.1);padding:3px 8px;border-radius:999px}
        .acc-switch{position:relative;display:inline-block;width:42px;height:24px;flex:none;margin:0;cursor:pointer}
        .acc-switch input{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
        .acc-track{position:absolute;inset:0;border-radius:999px;background:#dc2626;transition:background .2s}
        .acc-track::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .2s}
        .acc-switch input:checked ~ .acc-track{background:#16a34a}
        .acc-switch input:checked ~ .acc-track::after{transform:translateX(18px)}
        .acc-switch input:focus-visible ~ .acc-track{outline:2px solid #7c5cff;outline-offset:2px}
        .acc-core-badge{width:42px;height:24px;border-radius:999px;background:#16a34a;position:relative;flex:none}
        .acc-core-badge::after{content:"";position:absolute;top:3px;right:3px;width:18px;height:18px;border-radius:50%;background:#fff}
        .module-savebar{position:sticky;bottom:12px;z-index:5;margin-top:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 18px;border-radius:14px;border:1px solid var(--border);background:var(--surface,#fff);box-shadow:0 10px 30px rgba(15,23,42,.14)}
        .module-count{font-size:13px;color:var(--muted,#6b7280)}
        .module-count strong{color:inherit;font-size:15px}
        .module-changes{font-size:13px;font-weight:600;color:#b7791f}
        @media (max-width:640px){.acc-head{flex-wrap:wrap}.acc-status{width:100%}.menu-col-switch{width:96px}.menu-col-link{width:92px}}
    </style>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card label="Modules enabled" :value="number_format($modules->where('enabled', true)->count())" icon="check-circle" tone="collection" meta="Menus are open" />
        <x-stat-card label="Modules locked" :value="number_format($lockedCount)" icon="lock" tone="pending" meta="Menus stay visible" />
        <x-stat-card label="Website content" value="Always on" icon="layout" tone="student" meta="Core module" />
    </div>

    @if (! $canManage)
        <div class="info-box" style="margin-top:16px;border-left:4px solid #d9ad55">
            <strong>Subscription status is read-only for your account.</strong>
            <p style="margin:6px 0 0">
                Only the super administrator can enable a module. Contact them with your payment reference to unlock the modules your school needs.
            </p>
        </div>
    @endif

    @if ($canManage)
        <form method="POST" action="{{ route('subscription.update') }}" style="margin-top:16px"
            x-data="{
                editable: @js($editableKeys),
                initial: @js($enabledKeys),
                enabled: @js($enabledKeys),
                menus: @js($menuKeysOn),
                menusInitial: @js($menuKeysOn),
                get dirty() {
                    return [...this.enabled].sort().join('|') !== [...this.initial].sort().join('|')
                        || [...this.menus].sort().join('|') !== [...this.menusInitial].sort().join('|');
                },
                get changedCount() {
                    const modules = this.editable.filter(k => this.enabled.includes(k) !== this.initial.includes(k)).length;
                    const menuKeys = [...new Set([...this.menus, ...this.menusInitial])];
                    const menus = menuKeys.filter(k => this.menus.includes(k) !== this.menusInitial.includes(k)).length;
                    return modules + menus;
                },
                get activeCount() { return this.enabled.length + {{ $coreCount }} },
                reset() { this.enabled = [...this.initial]; this.menus = [...this.menusInitial] },
                setAll(on) { this.enabled = on ? [...this.editable] : [] },
            }">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Module access</h3>
                        <p class="card-sub">Every menu of a module is listed inside its accordion. Switch the module on, then turn its menus on or off one by one.</p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="module-toolbar">
                        <div class="module-count">
                            <strong x-text="activeCount"></strong> of {{ $totalCount }} modules enabled
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="button" class="btn btn-secondary btn-sm" @click="setAll(true)">Enable all</button>
                            <button type="button" class="btn btn-secondary btn-sm" @click="setAll(false)">Lock all</button>
                        </div>
                    </div>

                    <div class="acc-list">
                        @foreach ($modules as $module)
                            @php($key = $module['key'])
                            @php($menus = $menusByModule->get($key, collect()))

                            @if ($module['core'])
                                <div class="acc is-core" x-data="{ open: true }">
                                    <div class="acc-head" @click="open = !open">
                                        <span class="acc-icon"><x-icon :name="$moduleIcons[$key] ?? 'layout'" :size="18" /></span>
                                        <span class="acc-title">
                                            <strong>{{ $module['name'] }}</strong>
                                            <small>{{ $module['description'] }}</small>
                                        </span>
                                        <span class="acc-count">{{ $menus->count() }} {{ $menus->count() === 1 ? 'menu' : 'menus' }}</span>
                                        <span class="acc-status"><x-icon name="check-circle" :size="13" /> Always on</span>
                                        <span class="acc-core-badge" aria-hidden="true"></span>
                                        <x-icon name="chevron-down" :size="16" class="acc-chevron" x-bind:class="open && 'is-open'" />
                                    </div>
                                    <div class="acc-body" x-show="open">
                                        @include('admin.subscription._menu-table', [
                                            'menus' => $menus,
                                            'editable' => true,
                                            'moduleKey' => $key,
                                            'moduleCore' => true,
                                        ])
                                    </div>
                                </div>
                            @else
                                <div class="acc" x-data="{ open: true }"
                                     :class="enabled.includes('{{ $key }}') ? 'is-on' : 'is-locked'">
                                    <div class="acc-head" @click="open = !open">
                                        <span class="acc-icon"><x-icon :name="$moduleIcons[$key] ?? 'circle'" :size="18" /></span>
                                        <span class="acc-title">
                                            <strong>{{ $module['name'] }}</strong>
                                            <small>{{ $module['description'] }}</small>
                                        </span>

                                        <span class="acc-count">{{ $menus->count() }} {{ $menus->count() === 1 ? 'menu' : 'menus' }}</span>

                                        <span class="acc-status">
                                            <template x-if="enabled.includes('{{ $key }}')">
                                                <span style="display:inline-flex;align-items:center;gap:6px"><x-icon name="check-circle" :size="13" /> Enabled</span>
                                            </template>
                                            <template x-if="!enabled.includes('{{ $key }}')">
                                                <span style="display:inline-flex;align-items:center;gap:6px"><x-icon name="lock" :size="13" /> Locked</span>
                                            </template>
                                        </span>

                                        <label class="acc-switch" @click.stop title="Enable or lock this module">
                                            <input type="checkbox" name="modules[]" value="{{ $key }}" x-model="enabled">
                                            <span class="acc-track" aria-hidden="true"></span>
                                        </label>

                                        <x-icon name="chevron-down" :size="16" class="acc-chevron"
                                                x-bind:class="open && 'is-open'" />
                                    </div>

                                    {{-- Every menu is listed even while the module is locked, so the
                                         super administrator can see what each module includes. --}}
                                    <div class="acc-body" x-show="open">
                                        <p class="acc-locked-note" x-show="!enabled.includes('{{ $key }}')">
                                            This module is locked. Switch it on to turn its menus on or off.
                                        </p>
                                        @include('admin.subscription._menu-table', [
                                            'menus' => $menus,
                                            'editable' => true,
                                            'moduleKey' => $key,
                                            'moduleCore' => false,
                                        ])
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="card-body" style="border-top:1px solid var(--border)">
                    <label for="subscription_note" style="font-weight:600;display:block;margin-bottom:6px">Subscription note</label>
                    <textarea id="subscription_note" name="subscription_note" class="input" rows="2" maxlength="500" placeholder="Example: Annual plan paid, invoice #INV-2026-014">{{ $note }}</textarea>
                </div>
            </div>

            <div class="module-savebar" x-cloak>
                <div>
                    <template x-if="dirty">
                        <span class="module-changes"><span x-text="changedCount"></span> unsaved change(s)</span>
                    </template>
                    <template x-if="!dirty">
                        <span class="module-count">No pending changes.
                            @if ($updatedAt)
                                Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('d M Y, h:i A') }}.
                            @endif
                        </span>
                    </template>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="reset()">Discard</button>
                    <x-button type="submit" variant="primary" icon="save">Save module access</x-button>
                </div>
            </div>
        </form>
    @else
        <div class="card" style="margin-top:16px">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Module access</h3>
                    <p class="card-sub">Your school's current subscription.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="acc-list">
                    @foreach ($modules as $module)
                        @php($isOpen = $module['core'] || $module['enabled'])
                        <div class="acc {{ $isOpen ? 'is-on' : '' }} {{ $module['core'] ? 'is-core' : '' }}" x-data="{ open: {{ $isOpen ? 'true' : 'false' }} }">
                            <div class="acc-head" @click="open = !open">
                                <span class="acc-icon"><x-icon :name="$moduleIcons[$module['key']] ?? 'circle'" :size="18" /></span>
                                <span class="acc-title">
                                    <strong>{{ $module['name'] }}</strong>
                                    <small>{{ $module['description'] }}</small>
                                </span>
                                @php($moduleMenuCount = $menusByModule->get($module['key'], collect())->count())
                                <span class="acc-count">{{ $moduleMenuCount }} {{ $moduleMenuCount === 1 ? 'menu' : 'menus' }}</span>
                                <span class="acc-status">
                                    @if ($isOpen)
                                        <x-icon name="check-circle" :size="13" /> {{ $module['core'] ? 'Always on' : 'Enabled' }}
                                    @else
                                        <x-icon name="lock" :size="13" /> Locked
                                    @endif
                                </span>
                                <x-icon name="chevron-down" :size="16" class="acc-chevron" x-bind:class="open && 'is-open'" />
                            </div>
                            <div class="acc-body" x-show="open">
                                @if (! $isOpen)
                                    <p class="acc-locked-note">This module is locked. Contact the super administrator to enable it.</p>
                                @endif
                                @include('admin.subscription._menu-table', ['menus' => $menusByModule->get($module['key'], collect())])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @php($myUnlocks = app(\App\Services\ModuleAccess::class)->unlockedFeaturesForRole(auth()->user()?->role_id))
            @if (! empty($myUnlocks))
                <div class="card-body" style="border-top:1px solid var(--border)">
                    <strong>Opened for your role by the super administrator:</strong>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                        @foreach ($myUnlocks as $featureKey)
                            <x-badge color="success">{{ app(\App\Services\ModuleAccess::class)->featureLabel($featureKey) }}</x-badge>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($note)
                <div class="card-body" style="border-top:1px solid var(--border)">
                    <strong>Note:</strong> {{ $note }}
                </div>
            @endif
        </div>
    @endif

    <div class="info-box" style="margin-top:16px">
        <strong>How access works:</strong>
        a module must be enabled (green) for its menus to open. A menu switched off here stays in the sidebar with a lock. Permissions still come from the role: when a school administrator takes a permission away from a user, that menu is <strong>hidden</strong> from them entirely.
    </div>
@endsection
