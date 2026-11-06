@extends('layouts.app')

@section('title', 'User Roles & Access')

@php
    $roleIcons = [
        'super-admin' => 'shield',
        'admin' => 'shield',
        'accountant' => 'wallet',
        'teacher' => 'graduation-cap',
        'registrar' => 'user-plus',
        'staff' => 'briefcase',
        'media-manager' => 'image',
        'website-admin' => 'layout',
        'website-editor' => 'edit',
        'student' => 'user',
        'parent' => 'users',
    ];
    $portalRoles = config('school.portal_only_roles', []);
    $allIds = collect($groups)->pluck('ids')->flatten()->values()->all();
    $selectedIds = array_map('intval', $selectedPermissions);
@endphp

@section('content')
    <x-page-header eyebrow="Administration" title="User Roles & Access"
        subtitle="Create roles and manage module-wise access permissions for your system users.">
        <x-slot:actions>
            <x-button :href="route('users.index')" variant="secondary" icon="users">User accounts</x-button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('new-role-dialog').showModal()">
                <x-icon name="plus" :size="16" /> Add New Role
            </button>
        </x-slot:actions>
    </x-page-header>

    <style>
        .ra-grid{display:grid;grid-template-columns:310px minmax(0,1fr);gap:18px;align-items:start}
        .ra-roles{overflow:hidden}
        .ra-roles-head{display:flex;align-items:center;justify-content:space-between;padding:16px 16px 10px;font-size:15px;font-weight:700}
        .ra-roles-head span:last-child{font-size:11px;font-weight:700;color:#6d4aff;background:rgba(124,92,255,.1);border-radius:999px;padding:3px 8px}
        .ra-search{position:relative;margin:0 16px 12px}
        .ra-search .icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)}
        .ra-search input{width:100%;padding:9px 10px 9px 34px;border:1px solid var(--border);border-radius:9px;background:var(--surface,#fff);color:inherit;font:inherit;font-size:13px}
        .ra-search input:focus{outline:2px solid rgba(124,92,255,.35);outline-offset:1px;border-color:#7c5cff}
        .ra-role-list{display:flex;flex-direction:column;gap:4px;padding:0 10px 14px;max-height:calc(100vh - 250px);overflow:auto}
        .ra-role{position:relative;display:flex;align-items:center;border-radius:10px;border:1px solid transparent;color:inherit}
        .ra-role:hover{background:rgba(124,92,255,.06)}
        .ra-role.is-active{background:linear-gradient(90deg,rgba(124,92,255,.14),rgba(124,92,255,.04));border-color:rgba(124,92,255,.35)}
        .ra-role-link{display:flex;align-items:center;gap:10px;padding:9px 10px;flex:1;min-width:0;text-decoration:none;color:inherit}
        .ra-role-actions{position:relative;padding-right:6px;flex:none}
        .ra-role-menu{border:0;background:none;color:var(--muted);cursor:pointer;width:24px;height:24px;border-radius:6px;font:inherit;font-size:16px;line-height:1}
        .ra-role-menu:hover{background:rgba(124,92,255,.1);color:#6d4aff}
        .ra-role-dropdown{position:absolute;right:0;top:100%;z-index:20;min-width:160px;background:var(--surface,#fff);border:1px solid var(--border);border-radius:9px;box-shadow:0 10px 24px rgba(15,23,42,.16);padding:5px;display:flex;flex-direction:column;gap:2px}
        .ra-role-dropdown a,.ra-role-dropdown button{display:block;width:100%;text-align:left;border:0;background:none;font:inherit;font-size:12px;padding:7px 9px;border-radius:6px;color:inherit;cursor:pointer;text-decoration:none}
        .ra-role-dropdown a:hover,.ra-role-dropdown button:hover{background:rgba(124,92,255,.08)}
        .ra-role-dropdown .is-danger{color:#b91c1c}
        .ra-role-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none;color:#fff;background:linear-gradient(135deg,#8b5cf6,#6d4aff)}
        .ra-role-icon.tone-1{background:linear-gradient(135deg,#60a5fa,#2563eb)}
        .ra-role-icon.tone-2{background:linear-gradient(135deg,#34d399,#059669)}
        .ra-role-icon.tone-3{background:linear-gradient(135deg,#fbbf24,#d97706)}
        .ra-role-icon.tone-4{background:linear-gradient(135deg,#f472b6,#db2777)}
        .ra-role-main{flex:1;min-width:0}
        .ra-role-main strong{display:block;font-size:13.5px;line-height:1.25}
        .ra-role-main small{display:block;margin-top:2px;font-size:11.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ra-role-users{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;color:var(--muted)}
        .ra-panel{padding:0;overflow:hidden}
        .ra-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap}
        .ra-panel-head h2{margin:0;font-size:17px}
        .ra-panel-head h2 span{color:#6d4aff}
        .ra-panel-body{padding:16px 18px}
        .ra-info{background:rgba(124,92,255,.05);border:1px solid rgba(124,92,255,.18);border-radius:11px;padding:13px 15px}
        .ra-info h3{margin:0 0 10px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6d4aff}
        .ra-info-grid{display:grid;grid-template-columns:1fr 2fr auto;gap:16px}
        .ra-info-grid label{display:block;font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);font-weight:700;margin-bottom:3px}
        .ra-info-grid strong,.ra-info-grid span.ra-value{font-size:13px}
        .ra-status{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;color:#15803d;background:rgba(22,163,74,.12);padding:3px 9px;border-radius:999px}
        .ra-status::before{content:"";width:6px;height:6px;border-radius:50%;background:#16a34a}
        .ra-perms-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:18px 0 10px;flex-wrap:wrap}
        .ra-perms-head h3{margin:0;font-size:14px}
        .ra-hint{margin:0 0 10px;font-size:11.5px;line-height:1.5;color:var(--muted)}
        .ra-tools{display:flex;gap:7px}
        .ra-mini{font:inherit;font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:7px;border:1px solid var(--border);background:var(--surface,#fff);color:#6d4aff;cursor:pointer}
        .ra-mini:hover{background:rgba(124,92,255,.08)}
        .ra-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:hidden}
        .ra-table{width:100%;border-collapse:collapse}
        .ra-table thead th{font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:9px 8px;background:rgba(124,92,255,.07);border-bottom:1px solid var(--border);text-align:center;font-weight:700;white-space:nowrap}
        .ra-table thead th.ra-module-col{text-align:left;padding-left:14px}
        .ra-table td{padding:8px;border-bottom:1px solid var(--border);text-align:center}
        .ra-table tbody:last-child tr:last-child td{border-bottom:0}
        .ra-module-cell{display:flex;align-items:center;gap:8px;text-align:left;font-weight:600;font-size:13px;padding-left:2px}
        .ra-module-icon{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;flex:none;background:rgba(124,92,255,.1);color:#6d4aff}
        .ra-expand{border:0;background:none;padding:0;width:18px;height:18px;display:grid;place-items:center;color:var(--muted);cursor:pointer;transition:transform .15s}
        .ra-expand.is-open{transform:rotate(90deg)}
        .ra-feature td{background:rgba(127,127,140,.035)}
        .ra-feature.is-collapsed{display:none}
        .ra-feature-name{display:flex;align-items:baseline;gap:7px;text-align:left;padding-left:52px;font-size:12.5px;flex-wrap:wrap}
        .ra-feature-name code{font-size:10px;color:var(--muted)}
        .ra-page-badge{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;color:#6d4aff;background:rgba(124,92,255,.09);border:1px solid rgba(124,92,255,.18);border-radius:999px;padding:1px 7px;white-space:nowrap}
        .ra-table input[type="checkbox"]{width:16px;height:16px;accent-color:#7c5cff;cursor:pointer;vertical-align:middle}
        .ra-table input[type="checkbox"]:disabled{opacity:.55;cursor:not-allowed}
        .ra-na{color:var(--muted);opacity:.45;font-size:12px}
        .ra-footer{display:flex;justify-content:flex-end;gap:9px;padding:14px 18px;border-top:1px solid var(--border);margin-top:16px}
        .ra-dialog{border:0;border-radius:14px;padding:0;width:min(440px,92vw);box-shadow:0 24px 60px rgba(15,23,42,.28)}
        .ra-dialog::backdrop{background:rgba(15,23,42,.45)}
        .ra-dialog form{padding:20px}
        .ra-dialog h3{margin:0 0 4px;font-size:17px}
        .ra-dialog p{margin:0 0 16px;color:var(--muted);font-size:12.5px}
        .ra-dialog label{display:block;font-size:12px;font-weight:700;margin:12px 0 5px}
        .ra-dialog-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
        @media (max-width:980px){.ra-grid{grid-template-columns:1fr}.ra-role-list{max-height:280px}.ra-info-grid{grid-template-columns:1fr 1fr}.ra-info-grid>div:last-child{grid-column:1/-1}}
        @media (max-width:640px){.ra-table thead th{font-size:9px;padding:7px 4px}.ra-table td{padding:6px 4px}.ra-feature-name{padding-left:16px}.ra-feature-name code{display:none}.ra-info-grid{grid-template-columns:1fr}}
    </style>

    <div class="ra-grid" x-data="{
        selected: @js($selectedIds),
        initial: @js($selectedIds),
        all: @js($allIds),
        allSelected(ids) { return ids.length > 0 && ids.every(id => this.selected.includes(id)); },
        someSelected(ids) { return ids.some(id => this.selected.includes(id)); },
        toggleMany(ids, on) {
            if (on) {
                ids.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
            } else {
                this.selected = this.selected.filter(id => !ids.includes(id));
            }
        },
        selectAll() { this.selected = [...this.all]; },
        clearAll() { this.selected = []; },
        cancel() { this.selected = [...this.initial]; },
        editRole: { id: null, name: '', description: '', action: '' },
        openRoleEditor(id, name, description, action) {
            this.editRole = { id, name, description: description || '', action };
            this.$nextTick(() => document.getElementById('edit-role-dialog').showModal());
        },
    }">

        {{-- Roles list --}}
        <aside class="card ra-roles" x-data="{ q: '' }">
            <div class="ra-roles-head">
                <span>Roles</span>
                <span>{{ $roles->count() }}</span>
            </div>

            <div class="ra-search">
                <x-icon name="search" :size="15" class="icon" />
                <input type="search" x-model="q" placeholder="Search role..." aria-label="Search roles">
            </div>

            <div class="ra-role-list">
                @foreach ($roles as $role)
                    <div class="ra-role {{ $selected?->id === $role->id ? 'is-active' : '' }}"
                         x-data="{ menu: false }"
                         x-show="@js(strtolower($role->name.' '.$role->slug.' '.($role->description ?? ''))).includes(q.toLowerCase())">
                        <a href="{{ route('roles.index', ['role' => $role->slug]) }}" class="ra-role-link">
                            <span class="ra-role-icon tone-{{ $loop->index % 5 }}">
                                <x-icon :name="$roleIcons[$role->slug] ?? 'user'" :size="17" />
                            </span>
                            <span class="ra-role-main">
                                <strong>{{ $role->name }}</strong>
                                <small>{{ $role->description ?: 'No description' }}</small>
                            </span>
                            <span class="ra-role-users">
                                <x-icon name="users" :size="12" /> {{ $role->users_count }}
                            </span>
                        </a>

                        <div class="ra-role-actions" @click.outside="menu = false">
                            <button type="button" class="ra-role-menu" @click.stop="menu = !menu"
                                    aria-label="Actions for {{ $role->name }}">⋯</button>
                            <div class="ra-role-dropdown" x-show="menu" x-cloak>
                                <a href="{{ route('roles.index', ['role' => $role->slug]) }}">Edit permissions</a>
                                @if ($editableRoles[$role->id] ?? false)
                                    <button type="button"
                                            @click="menu = false; openRoleEditor({{ $role->id }}, @js($role->name), @js($role->description), @js(route('roles.details.update', $role)))">
                                        Rename role
                                    </button>
                                @endif
                                @if ($deletableRoles[$role->id] ?? false)
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                          onsubmit="return confirm('Delete this role?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="is-danger">Delete role</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- Selected role --}}
        <section class="card ra-panel">
            @if ($selected)
                <div class="ra-panel-head">
                    <h2>Role Permissions <span>– {{ $selected->name }}</span></h2>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        @if (in_array($selected->slug, $portalRoles, true))
                            <x-badge color="warning">Portal only</x-badge>
                        @endif
                        <x-button :href="route('users.index', ['role_id' => $selected->id])" variant="secondary" icon="users">
                            {{ $selected->users_count }} user{{ $selected->users_count === 1 ? '' : 's' }}
                        </x-button>
                        @if ($canEdit)
                            <button type="button" class="btn btn-secondary"
                                    @click="openRoleEditor({{ $selected->id }}, @js($selected->name), @js($selected->description), @js(route('roles.details.update', $selected)))">
                                <x-icon name="edit" :size="15" /> Edit Role
                            </button>
                        @endif
                    </div>
                </div>

                <div class="ra-panel-body">
                    <div class="ra-info">
                        <h3>Role Information</h3>
                        <div class="ra-info-grid">
                            <div>
                                <label>Role Name</label>
                                <strong>{{ $selected->name }}</strong>
                            </div>
                            <div>
                                <label>Description</label>
                                <span class="ra-value">{{ $selected->description ?: '—' }}</span>
                            </div>
                            <div>
                                <label>Status</label>
                                <span class="ra-status">Active</span>
                            </div>
                        </div>
                    </div>

                    @if (! $canEdit)
                        <div class="info-box" style="margin-top:12px">
                            {{ $selected->slug === 'super-admin'
                                ? 'The Super Administrator role always has unrestricted access and cannot be limited.'
                                : 'Only the super administrator can change the permissions of this role.' }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('roles.update', $selected) }}">
                        @csrf
                        @method('PUT')

                        <div class="ra-perms-head">
                            <h3>Module Access Permissions</h3>
                            @if ($canEdit)
                                <div class="ra-tools">
                                    <button type="button" class="ra-mini" @click="selectAll()">Select All</button>
                                    <button type="button" class="ra-mini" @click="clearAll()">Reset</button>
                                </div>
                            @endif
                        </div>

                        <p class="ra-hint">
                            Each row is a permission and the badge shows the page it unlocks. Some pages need several
                            permissions, and one permission can apply to more than one page.
                        </p>

                        <div class="ra-table-wrap">
                            <table class="ra-table">
                                <thead>
                                    <tr>
                                        <th class="ra-module-col">Module / Feature</th>
                                        @foreach ($actions as $action => $label)
                                            <th>{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>

                                @foreach ($groups as $group)
                                    <tbody x-data="{ open: false }">
                                        <tr>
                                            <td>
                                                <div class="ra-module-cell">
                                                    <button type="button" class="ra-expand" x-on:click="open = !open"
                                                            x-bind:class="open && 'is-open'" aria-label="Toggle {{ $group['label'] }}">
                                                        <x-icon name="chevron-right" :size="13" />
                                                    </button>
                                                    <span class="ra-module-icon"><x-icon :name="$group['icon']" :size="14" /></span>
                                                    <span>{{ $group['label'] }}</span>
                                                </div>
                                            </td>

                                            @foreach ($actions as $action => $label)
                                                @php($cellIds = $group['cells'][$action] ?? [])
                                                <td>
                                                    @if ($cellIds !== [])
                                                        <input type="checkbox"
                                                               aria-label="{{ $label }} {{ $group['label'] }}"
                                                               :checked="allSelected(@js($cellIds))"
                                                               x-effect="$el.indeterminate = someSelected(@js($cellIds)) && !allSelected(@js($cellIds))"
                                                               x-on:change="toggleMany(@js($cellIds), $event.target.checked)"
                                                               @checked(! array_diff($cellIds, $selectedPermissions))
                                                               @disabled(! $canEdit)>
                                                    @else
                                                        <span class="ra-na">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>

                                        @foreach ($group['permissions'] as $permission)
                                            <tr class="ra-feature" x-bind:class="!open && 'is-collapsed'" x-cloak>
                                                <td>
                                                    <div class="ra-feature-name">
                                                        <span>{{ $permission['name'] }}</span>
                                                        <span class="ra-page-badge"><x-icon name="link" :size="10" /> {{ $permission['page'] }}</span>
                                                        <code>{{ $permission['slug'] }}</code>
                                                    </div>
                                                </td>

                                                @foreach ($actions as $action => $label)
                                                    <td>
                                                        @if ($permission['action'] === $action)
                                                            <input type="checkbox" name="permissions[]"
                                                                   value="{{ $permission['id'] }}"
                                                                   aria-label="{{ $permission['name'] }}"
                                                                   x-model.number="selected"
                                                                   @checked(in_array($permission['id'], $selectedPermissions))
                                                                   @disabled(! $canEdit)>
                                                        @else
                                                            <span class="ra-na">·</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                @endforeach
                            </table>
                        </div>

                        <div class="ra-footer">
                            <button type="button" class="btn btn-secondary" @click="cancel()">Cancel</button>
                            @if ($canEdit)
                                <x-button type="submit" variant="primary" icon="save">Save Changes</x-button>
                            @endif
                        </div>
                    </form>
                </div>
            @else
                <div class="ra-panel-body">
                    <p class="muted">No roles yet. Create the first role to start assigning permissions.</p>
                </div>
            @endif
        </section>

        <dialog id="edit-role-dialog" class="ra-dialog">
            <form method="POST" :action="editRole.action">
                @csrf
                @method('PUT')
                <h3>Edit Role</h3>
                <p>Update the role name and description. Permissions are managed separately below.</p>

                <label for="edit-role-name">Role name</label>
                <input id="edit-role-name" name="name" class="input" required maxlength="80" x-model="editRole.name">

                <label for="edit-role-description">Description</label>
                <textarea id="edit-role-description" name="description" class="input" rows="2" maxlength="255"
                          x-model="editRole.description"></textarea>

                <div class="ra-dialog-actions">
                    <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('edit-role-dialog').close()">Cancel</button>
                    <x-button type="submit" variant="primary" icon="save">Save changes</x-button>
                </div>
            </form>
        </dialog>
    </div>

    <dialog id="new-role-dialog" class="ra-dialog">
        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            <h3>Add New Role</h3>
            <p>Create the role first, then choose its module permissions.</p>

            <label for="new-role-name">Role name</label>
            <input id="new-role-name" name="name" class="input" required maxlength="80" placeholder="Example: Data Entry">

            <label for="new-role-description">Description</label>
            <textarea id="new-role-description" name="description" class="input" rows="2" maxlength="255"
                      placeholder="What is this role responsible for?"></textarea>

            <div class="ra-dialog-actions">
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('new-role-dialog').close()">Cancel</button>
                <x-button type="submit" variant="primary" icon="plus">Create role</x-button>
            </div>
        </form>
    </dialog>
@endsection
