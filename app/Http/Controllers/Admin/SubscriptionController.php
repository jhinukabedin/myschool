<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Services\AuditLogService;
use App\Services\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly ModuleAccess $modules,
        private readonly AuditLogService $audit
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $adminRole = Role::query()->where('slug', 'admin')->first();

        return view('admin.subscription.index', [
            'modules' => $this->modules->modules(),
            'canManage' => $user?->isSuperAdmin() || $user?->can('subscription.manage'),
            'note' => school_setting('subscription_note') ?: '',
            'updatedAt' => school_setting('subscription_updated_at'),
            'lockedCount' => $this->modules->lockedModules()->count(),
            'menusByModule' => $this->modules->menusByModule(),
            'adminRoleName' => $adminRole?->name ?? 'School Administrator',
            'exceptions' => $adminRole
                ? DB::table('role_feature_grants')->where('role_id', $adminRole->id)->pluck('feature_key')->flip()->all()
                : [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $editableKeys = collect(config('school.modules', []))
            ->reject(fn (array $meta) => (bool) ($meta['core'] ?? false))
            ->keys()
            ->all();

        $menuKeys = $this->modules->editableMenuKeys();

        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in($editableKeys)],
            'menu_scope' => ['nullable', 'array'],
            'menu_scope.*' => ['string', Rule::in($menuKeys)],
            'menus' => ['nullable', 'array'],
            'menus.*' => ['string', Rule::in($menuKeys)],
            'subscription_note' => ['nullable', 'string', 'max:500'],
        ]);

        $enabled = collect($data['modules'] ?? [])->filter()->values()->all();
        $before = collect($editableKeys)
            ->mapWithKeys(fn (string $key) => [$key => school_setting("module_{$key}", '0')])
            ->all();

        // Only the menus shown on the page (their module was enabled) are changed.
        $scope = array_values(array_unique($data['menu_scope'] ?? []));
        $menusOn = array_values(array_intersect($data['menus'] ?? [], $scope));
        $menuBefore = collect($scope)->mapWithKeys(fn (string $key) => [$key => $this->modules->menuEnabled($key) ? '1' : '0'])->all();

        foreach ($scope as $key) {
            SchoolSetting::put("menu_{$key}", in_array($key, $menusOn, true) ? '1' : '0', 'subscription');
        }

        foreach ($editableKeys as $key) {
            SchoolSetting::put("module_{$key}", in_array($key, $enabled, true) ? '1' : '0', 'subscription');
        }

        // Website content is core and intentionally always available.
        foreach (collect(config('school.modules', []))->filter(fn (array $meta) => (bool) ($meta['core'] ?? false))->keys() as $coreKey) {
            SchoolSetting::put("module_{$coreKey}", '1', 'subscription');
        }

        SchoolSetting::put('subscription_note', $data['subscription_note'] ?? null, 'subscription');
        SchoolSetting::put('subscription_updated_at', now()->toDateTimeString(), 'subscription');

        $this->audit->record(
            'subscription_modules_updated',
            'Subscription',
            'School module access updated by the super administrator',
            null,
            $before,
            collect($editableKeys)->mapWithKeys(fn (string $key) => [$key => school_setting("module_{$key}", '0')])->all()
        );

        if ($scope !== []) {
            $this->audit->record(
                'menu_access_updated',
                'Subscription',
                'Individual menu switches updated',
                null,
                ['menus' => $menuBefore],
                ['menus' => collect($scope)->mapWithKeys(fn (string $key) => [$key => $this->modules->menuEnabled($key) ? '1' : '0'])->all()]
            );
        }

        return back()->with('success', 'Module and menu access saved.');
    }

    /**
     * Open individual features of a locked module for the School Administrator.
     * Features of enabled modules are already open, so their rows are left alone.
     */
    public function updateGrants(Request $request): RedirectResponse
    {
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $lockedFeatures = $this->modules->featureCatalog()
            ->reject(fn (array $group) => $this->modules->enabled($group['module']['key']))
            ->flatMap(fn (array $group) => $group['features']->pluck('key'))
            ->values()
            ->all();

        $data = $request->validate([
            'exceptions' => ['nullable', 'array'],
            'exceptions.*' => ['string', Rule::in($lockedFeatures)],
        ]);

        $selected = array_values(array_unique($data['exceptions'] ?? []));

        $before = DB::table('role_feature_grants')
            ->where('role_id', $adminRole->id)
            ->whereIn('feature_key', $lockedFeatures)
            ->pluck('feature_key')
            ->all();

        DB::transaction(function () use ($adminRole, $lockedFeatures, $selected) {
            DB::table('role_feature_grants')
                ->where('role_id', $adminRole->id)
                ->whereIn('feature_key', $lockedFeatures)
                ->delete();

            $now = now();
            $rows = collect($selected)->map(fn (string $feature) => [
                'role_id' => $adminRole->id,
                'feature_key' => $feature,
                'granted_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows !== []) {
                DB::table('role_feature_grants')->insert($rows);
            }
        });

        $this->audit->record(
            'feature_exceptions_updated',
            'Subscription',
            'Locked-feature exceptions for the School Administrator updated',
            null,
            ['features' => $before],
            ['features' => $selected]
        );

        return back()->with('success', 'Feature exceptions saved for the School Administrator.');
    }
}
