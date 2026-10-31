@php
    /** @var \App\Models\WebsiteMenu|null $menu */
    $menu = $menu ?? null;
    $limit = 6;
    $iconChoices = \App\Support\WebsiteIcons::CHOICES;
@endphp

@if (! $menu)
    <div class="info-box" style="margin-bottom:12px">
        No featured menu exists yet. Create one to start adding up to {{ $limit }} homepage buttons.
    </div>
    <form method="POST" action="{{ route('website-menus.store') }}">
        @csrf
        <input type="hidden" name="name" value="Hero featured menu">
        <input type="hidden" name="location" value="featured">
        <input type="hidden" name="is_active" value="1">
        <x-button type="submit" variant="outline" icon="plus">Create featured menu</x-button>
    </form>
@else
    @php
        $items = $menu->rootItems;
        $used = $menu->items()->count();
    @endphp

    @if ($items->isNotEmpty())
        <p class="field-hint" style="margin:0 0 10px">
            The buttons appear on the homepage in this order. Use the arrows to move a button up or down, or set a
            <strong>sort order</strong> number when editing (lower numbers come first).
        </p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:110px">Order</th>
                        <th style="width:64px">Icon</th>
                        <th>Title</th>
                        <th>Sub title</th>
                        <th>Link</th>
                        <th style="width:110px">Status</th>
                        <th class="actions" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
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
                            <td>
                                @if ($item->icon)
                                    <span class="table-avatar"><x-icon :name="$item->icon" :size="16" /></span>
                                @else
                                    <span class="muted">▣</span>
                                @endif
                            </td>
                            <td>
                                <div class="cell-clamp" title="{{ $item->title }}">{{ $item->title }}</div>
                                <details style="margin-top:4px">
                                    <summary class="cell-sub" style="cursor:pointer">Edit button</summary>
                                    <form method="POST" action="{{ route('website-menus.items.update', $item) }}" style="display:grid;gap:6px;margin-top:8px;min-width:260px">
                                        @csrf
                                        @method('PUT')
                                        <label class="field">
                                            <span class="field-label">Icon</span>
                                            <select class="select" name="icon" aria-label="Icon">
                                                @foreach ($iconChoices as $value => $label)
                                                    <option value="{{ $value }}" @selected(($item->icon ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="field">
                                            <span class="field-label">Title</span>
                                            <input class="input" name="title" value="{{ $item->title }}" required maxlength="120" aria-label="Title">
                                        </label>
                                        <label class="field">
                                            <span class="field-label">Sub title</span>
                                            <input class="input" name="subtitle" value="{{ $item->subtitle }}" maxlength="160" aria-label="Sub title">
                                        </label>
                                        <label class="field">
                                            <span class="field-label">Link</span>
                                            <input class="input" name="url" value="{{ $item->url }}" placeholder="/class-routine or https://..." maxlength="500" aria-label="Link">
                                        </label>
                                        @if ($item->route_name)
                                            <input type="hidden" name="route_name" value="{{ $item->route_name }}">
                                        @endif
                                        <input type="hidden" name="parent_id" value="{{ $item->parent_id }}">
                                        <div style="display:flex;gap:6px">
                                            <label class="field" style="width:110px">
                                                <span class="field-label">Sort order</span>
                                                <input class="input" type="number" min="0" max="9999" name="sort_order" value="{{ $item->sort_order }}" aria-label="Sort order">
                                            </label>
                                            <label class="field" style="width:130px">
                                                <span class="field-label">Open in</span>
                                                <select class="select" name="target" aria-label="Target">
                                                    <option value="_self" @selected($item->target === '_self')>Same tab</option>
                                                    <option value="_blank" @selected($item->target === '_blank')>New tab</option>
                                                </select>
                                            </label>
                                        </div>
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> <span>Active</span></label>
                                        <button class="btn btn-secondary btn-sm" type="submit">Save button</button>
                                    </form>
                                </details>
                            </td>
                            <td><div class="cell-clamp" title="{{ $item->subtitle }}">{{ $item->subtitle ?: '—' }}</div></td>
                            <td><code class="cell-clamp" title="{{ $item->href }}">{{ $item->href }}</code></td>
                            <td><x-badge :color="$item->is_active ? 'success' : 'muted'">{{ $item->is_active ? 'Enabled' : 'Disabled' }}</x-badge></td>
                            <td class="actions">
                                <form method="POST" action="{{ route('website-menus.items.update', $item) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="title" value="{{ $item->title }}">
                                    <input type="hidden" name="subtitle" value="{{ $item->subtitle }}">
                                    <input type="hidden" name="icon" value="{{ $item->icon }}">
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
                                <form method="POST" action="{{ route('website-menus.items.destroy', $item) }}" onsubmit="return confirm('Delete this featured button?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-icon" title="Delete"><x-icon name="trash" :size="15" /></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="info-box" style="margin-bottom:12px">This menu has no items yet. Add up to {{ $limit }} buttons below.</div>
    @endif

    @if (! $menu->is_active)
        <p class="field-hint" style="margin-top:10px">This menu is currently disabled, so it will not appear on the public site.</p>
    @endif

    <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">

    @if ($used < $limit)
        <p class="card-sub" style="margin-top:0"><strong>{{ $used }} / {{ $limit }}</strong> buttons used. The next button is added at position {{ $used + 1 }} unless you set a different sort order.</p>
        <form method="POST" action="{{ route('website-menus.items.store', $menu) }}">
            @csrf
            <div class="form-grid">
                <x-select name="icon" label="Icon" :options="$iconChoices" :placeholder="false" help="Small icon shown above the title." />
                <x-input name="title" label="Menu title" placeholder="e.g. ক্লাস রুটিন" required />
                <x-input name="subtitle" label="Sub title" placeholder="e.g. শ্রেণিভিত্তিক সময়সূচি" />
                <x-input name="url" label="Link" placeholder="/class-routine or https://..." required />
                <x-input name="sort_order" label="Sort order" type="number" min="0" max="9999" :value="$used + 1" help="Lower numbers appear first." />
            </div>
            <input type="hidden" name="is_active" value="1">
            <x-button type="submit" variant="outline" icon="plus">Add featured button</x-button>
        </form>
    @else
        <div class="info-box">You have reached the maximum of {{ $limit }} featured buttons. Delete one above to add another.</div>
    @endif
@endif
