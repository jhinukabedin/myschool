<?php

namespace App\Services;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Subscription and feature access.
 *
 * A module is enabled when its `module_{key}` setting is "1". Core modules and
 * features are always open. A locked feature can still be unlocked for a
 * single role by the super administrator, without enabling the whole module.
 * The super administrator bypasses every lock.
 */
class ModuleAccess
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $modules = null;

    /** @var array<int, array<int, string>> */
    private array $grantCache = [];

    /**
     * @return Collection<int, array{key: string, name: string, description: string, core: bool, enabled: bool, sort_order: int}>
     */
    public function modules(): Collection
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = collect(config('school.modules', []))
            ->map(function (array $meta, string $key): array {
                $core = (bool) ($meta['core'] ?? false);

                return [
                    'key' => $key,
                    'name' => $meta['name'] ?? ucfirst($key),
                    'description' => $meta['description'] ?? '',
                    'core' => $core,
                    'enabled' => $core || school_setting("module_{$key}", '0') === '1',
                    'sort_order' => (int) ($meta['sort_order'] ?? 0),
                ];
            })
            ->sortBy('sort_order')
            ->values();

        return $this->modules;
    }

    public function enabled(?string $key): bool
    {
        if (! $key) {
            return true;
        }

        $module = $this->modules()->firstWhere('key', $key);

        // Unknown or core modules are never locked.
        return $module === null ? true : (bool) $module['enabled'];
    }

    public function isCore(?string $key): bool
    {
        if (! $key) {
            return true;
        }

        return (bool) ($this->modules()->firstWhere('key', $key)['core'] ?? false);
    }

    public function label(?string $key): string
    {
        if (! $key) {
            return 'Module';
        }

        return (string) ($this->modules()->firstWhere('key', $key)['name'] ?? ucfirst($key));
    }

    public function lockedModules(): Collection
    {
        return $this->modules()->where('core', false)->where('enabled', false)->values();
    }

    /**
     * @return array<string, array{name: string, module: string, routes: array<int, string>}>
     */
    public function features(): array
    {
        return config('school.features', []);
    }

    public function featureLabel(?string $key): string
    {
        if (! $key) {
            return 'Feature';
        }

        return (string) ($this->features()[$key]['name'] ?? ucfirst($key));
    }

    /**
     * The feature that owns a route, matched on the longest route prefix.
     */
    public function featureForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $best = null;
        $bestLength = -1;

        foreach ($this->features() as $key => $feature) {
            foreach ($feature['routes'] ?? [] as $prefix) {
                if (str_starts_with($routeName, $prefix) && strlen($prefix) > $bestLength) {
                    $best = $key;
                    $bestLength = strlen($prefix);
                }
            }
        }

        return $best;
    }

    /**
     * The subscription module that owns a route (kept for the locked-page copy).
     */
    public function moduleForRoute(?string $routeName): ?string
    {
        $feature = $this->featureForRoute($routeName);

        return $feature ? ($this->features()[$feature]['module'] ?? null) : null;
    }

    /**
     * Feature keys the super administrator has unlocked for a role.
     *
     * @return array<int, string>
     */
    public function unlockedFeaturesForRole(?int $roleId): array
    {
        if (! $roleId) {
            return [];
        }

        return $this->grantCache[$roleId] ??= DB::table('role_feature_grants')
            ->where('role_id', $roleId)
            ->pluck('feature_key')
            ->all();
    }

    /**
     * Whether a user may open a feature: plan, unlock or super admin.
     * Role permissions are checked separately by the route middleware.
     */
    public function canAccessFeature(?User $user, ?string $feature): bool
    {
        if (! $feature || ! $user) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $module = $this->features()[$feature]['module'] ?? null;

        if ($module === null || $this->isCore($module)) {
            return true;
        }

        // A menu switched off by the super administrator is closed for everyone else.
        if (! $this->menuEnabled($feature)) {
            return false;
        }

        if ($this->enabled($module)) {
            return true;
        }

        return in_array($feature, $this->unlockedFeaturesForRole($user->role_id), true);
    }

    /**
     * The sidebar menu that owns a route, if any.
     *
     * @return array<string, mixed>|null
     */
    public function menuForRoute(?string $routeName): ?array
    {
        return Navigation::find($routeName);
    }

    /**
     * Whether a single menu is switched on. Menus of core modules are always on.
     */
    public function menuEnabled(?string $menuKey): bool
    {
        if (! $menuKey) {
            return true;
        }

        $menu = Navigation::item($menuKey);

        if (! $menu || $this->isCore($menu['module'] ?? null)) {
            return true;
        }

        return school_setting("menu_{$menuKey}", '1') === '1';
    }

    /**
     * Menu keys the super administrator can switch on or off individually.
     *
     * @return array<int, string>
     */
    public function editableMenuKeys(): array
    {
        return collect(Navigation::items())
            ->reject(fn (array $menu) => $this->isCore($menu['module'] ?? null))
            ->pluck('key')
            ->all();
    }

    public function canAccessRoute(?User $user, ?string $routeName): bool
    {
        if (! $user) {
            return true;
        }

        $menu = $this->menuForRoute($routeName);

        // Routes that are not a sidebar menu fall back to the feature rules.
        if (! $menu) {
            return $this->canAccessFeature($user, $this->featureForRoute($routeName));
        }

        $module = $menu['module'] ?? null;

        if ($this->isCore($module) || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->menuEnabled($menu['key'])) {
            return false;
        }

        if ($this->enabled($module)) {
            return true;
        }

        $feature = $this->featureForRoute($routeName);

        return $feature !== null
            && in_array($feature, $this->unlockedFeaturesForRole($user->role_id), true);
    }

    /**
     * Every sidebar menu, grouped by its module key, for the subscription screen.
     *
     * @return Collection<string, Collection<int, array{key: string, name: string, module: string, core: bool, enabled: bool, url: string|null}>>
     */
    public function menusByModule(): Collection
    {
        return collect(Navigation::items())
            ->map(function (array $menu): array {
                $core = $this->isCore($menu['module'] ?? null);
                $route = $menu['route'] ?? null;

                if (is_array($route)) {
                    $url = Route::has($route[0]) ? route($route[0], $route[1]) : null;
                } else {
                    $url = $route && Route::has($route) ? route($route) : null;
                }

                return [
                    'key' => $menu['key'],
                    'name' => $menu['title'],
                    'module' => $menu['module'] ?? 'core',
                    'icon' => $menu['icon'] ?? 'circle',
                    'core' => $core,
                    'enabled' => $core ? true : $this->menuEnabled($menu['key']),
                    'url' => $url,
                ];
            })
            ->values()
            ->groupBy('module');
    }

    /**
     * Feature catalogue grouped by module, for the subscription screen.
     *
     * @return Collection<int, array{module: array<string, mixed>, features: Collection<int, array{key: string, name: string}>}>
     */
    public function featureCatalog(): Collection
    {
        return $this->modules()
            ->reject(fn (array $module) => $module['core'])
            ->map(fn (array $module) => [
                'module' => $module,
                'features' => collect($this->features())
                    ->filter(fn (array $feature, string $key) => $feature['module'] === $module['key'])
                    ->map(fn (array $feature, string $key) => [
                        'key' => $key,
                        'name' => $feature['name'],
                        'url' => isset($feature['page']) && Route::has($feature['page']) ? route($feature['page']) : null,
                    ])
                    ->values(),
            ])
            ->filter(fn (array $group) => $group['features']->isNotEmpty())
            ->values();
    }
}
