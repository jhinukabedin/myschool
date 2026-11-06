@extends('layouts.app')

@section('title', 'Website Menus')

@section('content')
    <x-page-header eyebrow="Website" title="Navigation Menus"
        subtitle="Control the header, footer, sidebar and secondary navigation. If no active menu exists for a location, the public site falls back to its built-in links.">
        <x-slot:actions>
            <x-button :href="route('website-content.index')" variant="secondary" icon="layout-dashboard">Content library</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card" style="margin-bottom:18px">
        <div class="card-header"><div><h3 class="card-title">Create a menu</h3><p class="card-sub">One active menu per location is used publicly.</p></div></div>
        <form method="POST" action="{{ route('website-menus.store') }}">
            @csrf
            <div class="form-section">
                <div class="form-grid">
                    <x-input name="name" label="Menu name" placeholder="Main navigation" required />
                    <x-select name="location" label="Location" :options="$locations" required />
                    <div class="span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> <span>Active</span></label>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex;justify-content:flex-end">
                <x-button type="submit" variant="primary" icon="plus">Create menu</x-button>
            </div>
        </form>
    </div>

    @forelse ($menus as $menu)
        <section class="card" style="margin-bottom:18px">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ $menu->name }}</h3>
                    <p class="card-sub">{{ $locations[$menu->location] ?? $menu->location }} · {{ $menu->rootItems->sum(fn ($item) => 1 + $item->children->count()) }} item(s)</p>
                </div>
                <x-badge :color="$menu->is_active ? 'success' : 'neutral'">{{ $menu->is_active ? 'Active' : 'Inactive' }}</x-badge>
            </div>

            <div class="card-body" style="border-bottom:1px solid var(--border)">
                <form method="POST" action="{{ route('website-menus.update', $menu) }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                    @csrf
                    @method('PUT')
                    <div class="field"><input class="input" name="name" value="{{ $menu->name }}" required maxlength="120" aria-label="Menu name"></div>
                    <div class="field">
                        <select class="select" name="location" aria-label="Menu location">
                            @foreach ($locations as $value => $label)
                                <option value="{{ $value }}" @selected($menu->location === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="is_active" value="0">
                    <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($menu->is_active)> <span>Active</span></label>
                    <button class="btn btn-secondary btn-sm" type="submit">Save menu</button>
                </form>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Order</th><th>Item</th><th>Destination</th><th>Parent</th><th>Status</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($menu->rootItems as $item)
                            @include('admin.website._menu-row', ['item' => $item, 'menu' => $menu])
                            @foreach ($item->children as $child)
                                @include('admin.website._menu-row', ['item' => $child, 'menu' => $menu, 'nested' => true])
                            @endforeach
                        @empty
                            <tr><td colspan="6"><div class="empty-state">No items yet. Add the first link below.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-body" style="border-top:1px solid var(--border)">
                <form method="POST" action="{{ route('website-menus.items.store', $menu) }}">
                    @csrf
                    <div class="form-grid">
                        <x-input name="title" label="Link title" required maxlength="120" />
                        <x-input name="url" label="URL" placeholder="/about or https://example.com" help="Leave blank if using a Laravel route name." />
                        <x-input name="route_name" label="Route name" placeholder="website.about" help="Preferred for internal links." />
                        <div class="field">
                            <label for="parent-{{ $menu->id }}">Parent item</label>
                            <select id="parent-{{ $menu->id }}" class="select" name="parent_id">
                                <option value="">Top-level item</option>
                                @foreach ($menu->rootItems as $root)
                                    <option value="{{ $root->id }}">{{ $root->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="target-{{ $menu->id }}">Open in</label>
                            <select id="target-{{ $menu->id }}" class="select" name="target">
                                <option value="_self">Same tab</option>
                                <option value="_blank">New tab</option>
                            </select>
                        </div>
                        <x-input name="sort_order" label="Sort order" type="number" min="0" value="0" />
                        <div class="span-2">
                            <input type="hidden" name="is_active" value="0">
                            <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> <span>Active</span></label>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:12px">
                        <button class="btn btn-primary btn-sm" type="submit">Add item</button>
                    </div>
                </form>
            </div>
        </section>
    @empty
        <div class="card"><div class="empty-state">No custom menus exist. The public website is using its built-in navigation. Create a menu to override it.</div></div>
    @endforelse
@endsection
