@php
    /** @var \App\Models\WebsiteMenu|null $menu */
    $menu = $menu ?? null;
    $location = $location ?? 'footer';
    $defaultName = $defaultName ?? 'Footer menu';
@endphp

@if (! $menu)
    <div class="info-box" style="margin-bottom:12px">
        No menu exists for this location yet. Create one to start adding items.
    </div>
    <form method="POST" action="{{ route('website-menus.store') }}">
        @csrf
        <input type="hidden" name="name" value="{{ $defaultName }}">
        <input type="hidden" name="location" value="{{ $location }}">
        <input type="hidden" name="is_active" value="1">
        <x-button type="submit" variant="outline" icon="plus">Create menu</x-button>
    </form>
@else
    @if ($menu->rootItems->isNotEmpty())
        <p class="field-hint" style="margin:0 0 10px">
            Links appear publicly in this order. Use the arrows to move a link up or down. For full editing, use the
            <a href="{{ route('website-menus.index') }}">menu builder</a>.
        </p>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th style="width:110px">Order</th><th>Menu name</th><th>URL</th><th style="width:110px">Status</th><th class="actions" style="width:150px">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($menu->rootItems as $item)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:4px">
                                <span class="mono" style="min-width:18px">{{ $loop->iteration }}</span>
                                <form method="POST" action="{{ route('website-menus.items.move', $item) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn-icon" title="Move up" @disabled($loop->first)>
                                        <x-icon name="arrow-up" :size="14" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('website-menus.items.move', $item) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn-icon" title="Move down" @disabled($loop->last)>
                                        <x-icon name="arrow-down" :size="14" />
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td>{{ $item->title }}</td>
                        <td><code class="cell-clamp" title="{{ $item->route_name ? route($item->route_name) : $item->url }}">{{ $item->route_name ? route($item->route_name) : $item->url }}</code></td>
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
                            <form method="POST" action="{{ route('website-menus.items.destroy', $item) }}" onsubmit="return confirm('Delete this menu item?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon" title="Delete menu item"><x-icon name="trash" :size="15" /></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <div class="info-box" style="margin-bottom:12px">This menu has no items yet.</div>
    @endif

    <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">

    <form method="POST" action="{{ route('website-menus.items.store', $menu) }}">
        @csrf
        <div class="form-grid">
            <x-input name="title" label="Menu name" placeholder="e.g. বিদ্যালয় পরিচিতি" required />
            <x-input name="url" label="URL" placeholder="/about" required />
        </div>
        <input type="hidden" name="is_active" value="1">
        <x-button type="submit" variant="outline" icon="plus">Add menu item</x-button>
    </form>

    @if (! $menu->is_active)
        <p class="field-hint" style="margin-top:10px">This menu is currently disabled in the menu builder, so it will not appear on the public site.</p>
    @endif
@endif