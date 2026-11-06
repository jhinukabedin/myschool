<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gives individual staff users their permissions.
 *
 * An administrator can only hand out permissions they hold themselves, and can
 * never change the super administrator or another administrator's account.
 */
class UserAccessController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $editor = $request->user();
        $users = $this->manageableUsers($editor);
        $selected = $users->firstWhere('id', $request->integer('user')) ?? $users->first();

        return view('admin.access.index', [
            'users' => $users,
            'selected' => $selected,
            'groups' => $selected ? $this->permissionGroups($editor, $selected) : collect(),
            'isSuper' => $editor->isSuperAdmin(),
            'roleTemplate' => $selected?->role?->permissions()->pluck('slug')->all() ?? [],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $editor = $request->user();
        $this->ensureManageable($editor, $user);

        $allSlugs = Permission::query()->pluck('slug')->all();
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allSlugs)],
        ]);

        $submitted = array_values(array_unique($data['permissions'] ?? []));
        $ceiling = $this->grantableSlugs($editor);

        $notHeld = array_values(array_diff($submitted, $ceiling));
        if ($notHeld !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'You can only give permissions you hold yourself: '.implode(', ', $notHeld).'.',
            ]);
        }

        $before = $user->explicitPermissions()->pluck('slug')->all();
        $after = $this->mergeWithinCeiling($before, $submitted, $ceiling);

        $this->applyExplicit($user, $before, $after, $editor);

        $this->audit->record(
            'user_permissions_updated',
            'Access',
            "Permissions for {$user->name} updated",
            $user,
            ['permissions' => $before],
            ['permissions' => $after]
        );

        return redirect()->route('access.index', ['user' => $user->id])
            ->with('success', "Permissions saved for {$user->name}.");
    }

    /**
     * Copy the permissions of the user's role template, within what the editor holds.
     */
    public function template(Request $request, User $user): RedirectResponse
    {
        $editor = $request->user();
        $this->ensureManageable($editor, $user);

        $ceiling = $this->grantableSlugs($editor);
        $template = $user->role?->permissions()->pluck('slug')->all() ?? [];
        $before = $user->explicitPermissions()->pluck('slug')->all();
        $after = $this->mergeWithinCeiling($before, array_values(array_intersect($template, $ceiling)), $ceiling);

        $this->applyExplicit($user, $before, $after, $editor);

        $this->audit->record(
            'user_permissions_template_applied',
            'Access',
            "Role template applied to {$user->name}",
            $user,
            ['permissions' => $before],
            ['permissions' => $after]
        );

        return redirect()->route('access.index', ['user' => $user->id])
            ->with('success', "Role template applied to {$user->name}. Review the list and save if needed.");
    }

    /**
     * Staff who can receive permissions from this editor. Super admin and
     * school administrator accounts are managed in Roles & permissions instead.
     */
    private function manageableUsers(User $editor): Collection
    {
        $excluded = array_merge(['super-admin', 'admin'], config('school.portal_only_roles', []));

        return User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->whereNotIn('slug', $excluded))
            ->where('id', '!=', $editor->id)
            ->orderBy('name')
            ->get();
    }

    private function ensureManageable(User $editor, User $user): void
    {
        abort_unless($this->manageableUsers($editor)->contains('id', $user->id), 403, 'You cannot change this account.');
    }

    /**
     * @return array<int, string>
     */
    private function grantableSlugs(User $editor): array
    {
        if ($editor->isSuperAdmin()) {
            return Permission::query()->pluck('slug')->all();
        }

        return $editor->permissionSlugs();
    }

    /**
     * Keep the permissions outside the editor's reach, and replace only the ones inside it.
     *
     * @param  array<int, string>  $current
     * @param  array<int, string>  $submitted
     * @param  array<int, string>  $ceiling
     * @return array<int, string>
     */
    private function mergeWithinCeiling(array $current, array $submitted, array $ceiling): array
    {
        $outsideReach = array_diff($current, $ceiling);

        return array_values(array_unique(array_merge($outsideReach, array_intersect($submitted, $ceiling))));
    }

    /**
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    private function applyExplicit(User $user, array $before, array $after, User $editor): void
    {
        $toRemove = Permission::query()->whereIn('slug', array_diff($before, $after))->pluck('id');
        $toAdd = Permission::query()->whereIn('slug', array_diff($after, $before))->pluck('id');

        if ($toRemove->isNotEmpty()) {
            $user->explicitPermissions()->detach($toRemove->all());
        }

        if ($toAdd->isNotEmpty()) {
            $user->explicitPermissions()->attach(
                $toAdd->mapWithKeys(fn ($id) => [$id => ['granted_by' => $editor->id]])->all()
            );
        }
    }

    /**
     * Permission groups for the selected user, with what the editor may change.
     *
     * @return Collection<int, array{module: string, items: array<int, array<string, mixed>>}>
     */
    private function permissionGroups(User $editor, User $user): Collection
    {
        $ceiling = $this->grantableSlugs($editor);
        $current = $user->explicitPermissions()->pluck('slug')->all();

        return collect(config('school.permissions', []))
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'name' => $meta['name'],
                'module' => $meta['module'],
                'checked' => in_array($slug, $current, true),
                'allowed' => in_array($slug, $ceiling, true),
            ])
            ->values()
            ->groupBy('module')
            ->map(fn (Collection $items, string $module) => ['module' => $module, 'items' => $items->values()->all()])
            ->values();
    }
}
