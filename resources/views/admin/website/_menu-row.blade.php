@php($nested = $nested ?? false)
<tr>
    <td class="mono">{{ $item->sort_order }}</td>
    <td>
        <div class="cell-title" style="{{ $nested ? 'padding-left:18px' : '' }}">
            @if ($nested)<span class="muted">↳ </span>@endif{{ $item->title }}
        </div>
        <details style="margin-top:4px">
            <summary class="cell-sub" style="cursor:pointer">Edit item</summary>
            <form method="POST" action="{{ route('website-menus.items.update', $item) }}" style="display:grid;gap:6px;margin-top:8px;min-width:230px">
                @csrf
                @method('PUT')
                <input class="input" name="title" value="{{ $item->title }}" required maxlength="120" aria-label="Title">
                <input class="input" name="url" value="{{ $item->url }}" placeholder="URL" aria-label="URL">
                <input class="input" name="route_name" value="{{ $item->route_name }}" placeholder="Route name" aria-label="Route name">
                <select class="select" name="parent_id" aria-label="Parent">
                    <option value="">Top-level item</option>
                    @foreach ($menu->rootItems as $root)
                        @if ($root->id !== $item->id)
                            <option value="{{ $root->id }}" @selected($item->parent_id === $root->id)>{{ $root->title }}</option>
                        @endif
                    @endforeach
                </select>
                <div style="display:flex;gap:6px">
                    <input class="input" type="number" min="0" name="sort_order" value="{{ $item->sort_order }}" aria-label="Sort order" style="width:90px">
                    <select class="select" name="target" aria-label="Target" style="width:110px">
                        <option value="_self" @selected($item->target === '_self')>Same tab</option>
                        <option value="_blank" @selected($item->target === '_blank')>New tab</option>
                    </select>
                </div>
                <input type="hidden" name="is_active" value="0">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> <span>Active</span></label>
                <button class="btn btn-secondary btn-sm" type="submit">Save item</button>
            </form>
        </details>
    </td>
    <td>
        @if ($item->route_name)
            <code>{{ $item->route_name }}</code>
        @else
            <code>{{ $item->url }}</code>
        @endif
        @if ($item->target === '_blank')<div class="cell-sub">New tab</div>@endif
    </td>
    <td>{{ $item->parent?->title ?? '—' }}</td>
    <td><x-badge :color="$item->is_active ? 'success' : 'neutral'">{{ $item->is_active ? 'Active' : 'Hidden' }}</x-badge></td>
    <td class="actions">
        <form method="POST" action="{{ route('website-menus.items.destroy', $item) }}" onsubmit="return confirm('Delete this item{{ $item->children->isNotEmpty() ? ' and its child items' : '' }}?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
        </form>
    </td>
</tr>
