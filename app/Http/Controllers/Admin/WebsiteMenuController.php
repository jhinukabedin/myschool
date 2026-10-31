<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteMenu;
use App\Models\WebsiteMenuItem;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebsiteMenuController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        $menus = WebsiteMenu::query()
            ->with(['rootItems.children'])
            ->orderByRaw("FIELD(location, 'header', 'footer', 'secondary')")
            ->orderBy('name')
            ->get();

        return view('admin.website.menus', [
            'menus' => $menus,
            'locations' => [
                'header' => 'Header navigation',
                'footer' => 'Footer links',
                'secondary' => 'Secondary navigation',
                'login' => 'Header login menu',
                'footer_institution' => 'Footer — Institution',
                'footer_links' => 'Footer — Important links',
                'featured' => 'Hero featured menu',
                'sidebar' => 'Website sidebar',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMenu($request);
        $menu = WebsiteMenu::create($data);
        $this->audit->record('website_menu_created', 'Website', "Created menu {$menu->name} ({$menu->location})", $menu, null, $data);

        return back()->with('success', 'Menu created. Add items below or leave it inactive to keep the built-in navigation.');
    }

    public function update(Request $request, WebsiteMenu $menu): RedirectResponse
    {
        $data = $this->validateMenu($request, $menu);
        $before = $menu->only(['name', 'location', 'is_active']);
        $menu->update($data);
        $this->audit->record('website_menu_updated', 'Website', "Updated menu {$menu->name}", $menu, $before, $data);

        return back()->with('success', 'Menu updated.');
    }

    public function destroy(WebsiteMenu $menu): RedirectResponse
    {
        $payload = $menu->only(['name', 'location']);
        $menu->delete();
        $this->audit->record('website_menu_deleted', 'Website', "Deleted menu {$payload['name']} ({$payload['location']})", null, $payload, null);

        return back()->with('success', 'Menu and its items deleted. The public site will use its built-in navigation for this location if no other menu is active.');
    }

    public function storeItem(Request $request, WebsiteMenu $menu): RedirectResponse
    {
        $data = $this->validateItem($request, $menu);
        $item = $menu->items()->create($data);
        $this->audit->record('website_menu_item_created', 'Website', "Added menu item {$item->title}", $item, null, $data);

        return back()->with('success', 'Menu item added.');
    }

    public function updateItem(Request $request, WebsiteMenuItem $item): RedirectResponse
    {
        $data = $this->validateItem($request, $item->menu, $item);
        $before = $item->only(['title', 'subtitle', 'icon', 'url', 'route_name', 'parent_id', 'target', 'sort_order', 'is_active']);
        $item->update($data);
        $this->audit->record('website_menu_item_updated', 'Website', "Updated menu item {$item->title}", $item, $before, $data);

        return back()->with('success', 'Menu item updated.');
    }

    public function destroyItem(WebsiteMenuItem $item): RedirectResponse
    {
        $payload = $item->only(['title', 'url', 'route_name']);
        $item->delete();
        $this->audit->record('website_menu_item_deleted', 'Website', "Deleted menu item {$payload['title']}", null, $payload, null);

        return back()->with('success', 'Menu item deleted.');
    }

    /**
     * Move an item one position up or down among its siblings. Sibling sort
     * orders are renumbered after the swap so the result stays deterministic
     * even when several items shared the same order.
     */
    public function moveItem(Request $request, WebsiteMenuItem $item): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $siblings = WebsiteMenuItem::query()
            ->where('website_menu_id', $item->website_menu_id)
            ->where('parent_id', $item->parent_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (WebsiteMenuItem $sibling) => $sibling->id === $item->id);

        if ($index === false) {
            return back();
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $siblings->count()) {
            return back()->with('success', $direction === 'up'
                ? 'This item is already first.'
                : 'This item is already last.');
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $sibling) {
                $sibling->update(['sort_order' => $position + 1]);
            }
        });

        $this->audit->record(
            'website_menu_item_moved',
            'Website',
            "Moved menu item {$item->title} {$direction}",
            $item,
            null,
            ['direction' => $direction, 'sort_order' => $item->fresh()->sort_order]
        );

        return back()->with('success', 'Menu order updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMenu(Request $request, ?WebsiteMenu $menu = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => [
                'required', Rule::in(['header', 'footer', 'secondary', 'login', 'footer_institution', 'footer_links', 'featured', 'sidebar']),
                Rule::unique('website_menus', 'location')
                    ->where('name', $request->input('name'))
                    ->ignore($menu?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, WebsiteMenu $menu, ?WebsiteMenuItem $item = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'icon' => ['nullable', 'string', 'max:60'],
            'url' => ['nullable', 'string', 'max:500', 'required_without:route_name', 'regex:/^(https?:\/\/|\/|#)/i'],
            'route_name' => [
                'nullable', 'string', 'max:120', 'required_without:url',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && ! Route::has($value)) {
                        $fail('The selected route does not exist.');
                    }
                },
            ],
            'parent_id' => ['nullable', 'integer'],
            'target' => ['nullable', Rule::in(['_self', '_blank'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $parentId = $data['parent_id'] ?? null;

        // The homepage featured area is a fixed 2x3 grid, so cap it at six items.
        if (! $item && $menu->location === 'featured' && $menu->items()->count() >= 6) {
            throw ValidationException::withMessages([
                'title' => 'The featured menu holds a maximum of 6 items. Delete one before adding another.',
            ]);
        }

        if ($parentId) {
            $parent = WebsiteMenuItem::query()
                ->where('website_menu_id', $menu->id)
                ->whereKey($parentId)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a parent item from this menu.']);
            }

            if ($item && ($parent->id === $item->id || $parent->parent_id === $item->id)) {
                throw ValidationException::withMessages(['parent_id' => 'An item cannot be its own parent or a child of its child.']);
            }

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => 'Only top-level items can be parents. This keeps the public navigation predictable.']);
            }
        }

        $data['parent_id'] = $parentId ?: null;
        $data['target'] = $data['target'] ?? '_self';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['url'] = $data['url'] ?? null;
        $data['route_name'] = $data['route_name'] ?? null;
        $data['icon'] = $data['icon'] ?? null;
        $data['subtitle'] = $data['subtitle'] ?? null;

        return $data;
    }
}
