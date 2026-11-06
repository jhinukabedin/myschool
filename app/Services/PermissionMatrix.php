<?php

namespace App\Services;

use App\Models\Permission;
use App\Support\Navigation;
use Illuminate\Support\Str;

/**
 * Builds the module/feature permission matrix for the role & access screen.
 *
 * Permissions are stored module-wide (for example `students.view`), but the
 * screen shows them in readable feature groups with a View / Add / Edit /
 * Delete / Print / Export column for each one.
 */
class PermissionMatrix
{
    public const ACTIONS = [
        'view' => 'View',
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'print' => 'Print',
        'export' => 'Export',
    ];

    /**
     * The sidebar page that best represents a permission area. The matrix shows
     * the page name next to a permission so every checkbox is traceable to the
     * menu page it controls.
     */
    private const PAGE_HINTS = [
        'students' => ['view' => 'students', 'edit' => 'students', 'delete' => 'passed-out', 'promote' => 'promotion'],
        'enquiries' => ['*' => 'enquiries'],
        'academics' => ['view' => 'subjects', '*' => 'classes'],
        'assignments' => ['*' => 'teacher-assignments'],
        'routines' => ['*' => 'class-routine'],
        'attendance' => ['*' => 'attendance'],
        'exams' => ['*' => 'exams'],
        'marks' => ['*' => 'marks-entry'],
        'results' => ['*' => 'class-result'],
        'fees' => ['view' => 'fee-structure', 'edit' => 'fee-structure', 'add' => 'fee-collection', 'delete' => 'fee-collection', '*' => 'fee-structure'],
        'scholarships' => ['*' => 'scholarships'],
        'finance' => ['*' => 'expenses'],
        'staff' => ['*' => 'staff-management'],
        'payroll' => ['*' => 'payroll'],
        'notices' => ['*' => 'notices'],
        'communication' => ['*' => 'notices'],
        'reports' => ['*' => 'reports'],
        'website' => ['*' => 'website-dashboard', 'menus' => 'website-menus', 'media' => 'website-media'],
        'portals' => ['*' => 'portal-accounts'],
        'settings' => ['*' => 'school-settings'],
        'users' => ['*' => 'user-accounts'],
        'subscription' => ['*' => 'subscription'],
        'access' => ['*' => 'user-access'],
        'audit' => ['*' => 'audit-log'],
    ];

    /** Permissions whose page cannot be inferred from the area/action alone. */
    private const PERMISSION_PAGE_OVERRIDES = [
        'students.promote' => 'promotion',
        'attendance.report' => 'attendance-report',
        'fees.waive' => 'fee-collection',
        'website.publish' => 'website-news',
        'website.code' => 'website-dashboard',
    ];

    /**
     * Display groups in screen order. Each group matches permission prefixes.
     */
    private const GROUPS = [
        'students' => ['label' => 'Students', 'icon' => 'users', 'prefixes' => ['students.', 'enquiries.']],
        'academics' => ['label' => 'Academic', 'icon' => 'book-open', 'prefixes' => ['academics.', 'assignments.', 'routines.']],
        'attendance' => ['label' => 'Attendance', 'icon' => 'calendar-check', 'prefixes' => ['attendance.']],
        'exams' => ['label' => 'Examination', 'icon' => 'graduation-cap', 'prefixes' => ['exams.', 'marks.', 'results.']],
        'fees' => ['label' => 'Fees & Payments', 'icon' => 'circle-dollar', 'prefixes' => ['fees.', 'scholarships.']],
        'finance' => ['label' => 'Accounts', 'icon' => 'credit-card', 'prefixes' => ['finance.']],
        'staff' => ['label' => 'HR & Staff', 'icon' => 'briefcase', 'prefixes' => ['staff.']],
        'payroll' => ['label' => 'Payroll', 'icon' => 'banknote', 'prefixes' => ['payroll.']],
        'communication' => ['label' => 'Communication', 'icon' => 'bell', 'prefixes' => ['notices.', 'communication.']],
        'reports' => ['label' => 'Reports', 'icon' => 'pie-chart', 'prefixes' => ['reports.']],
        'website' => ['label' => 'Website', 'icon' => 'layout', 'prefixes' => ['website.']],
        'portals' => ['label' => 'Portal Accounts', 'icon' => 'user-check', 'prefixes' => ['portals.']],
        'administration' => ['label' => 'System Setting', 'icon' => 'settings', 'prefixes' => ['settings.', 'users.', 'roles.', 'audit.', 'subscription.', 'access.', 'dashboard.']],
    ];

    /**
     * @return array<int, array{key: string, label: string, icon: string, permissions: array<int, array<string, mixed>>, cells: array<string, array<int, int>>, ids: array<int, int>}>
     */
    public function groups(): array
    {
        $buckets = [];

        foreach (Permission::query()->orderBy('name')->get() as $permission) {
            $buckets[$this->groupFor($permission->slug)][] = [
                'id' => $permission->id,
                'slug' => $permission->slug,
                'name' => $permission->name,
                'action' => $this->actionFor($permission->slug),
                'page' => $this->pageLabelFor($permission->slug),
            ];
        }

        $groups = [];

        foreach (self::GROUPS as $key => $meta) {
            $items = collect($buckets[$key] ?? []);

            if ($items->isEmpty()) {
                continue;
            }

            $cells = [];
            foreach (array_keys(self::ACTIONS) as $action) {
                $cells[$action] = $items->where('action', $action)->pluck('id')->values()->all();
            }

            $groups[] = [
                'key' => $key,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'permissions' => $items->values()->all(),
                'cells' => $cells,
                'ids' => $items->pluck('id')->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * Every permission id shown on the screen.
     *
     * @return array<int, int>
     */
    public function allIds(): array
    {
        return collect($this->groups())->pluck('ids')->flatten()->all();
    }

    private function groupFor(string $slug): string
    {
        foreach (self::GROUPS as $key => $meta) {
            foreach ($meta['prefixes'] as $prefix) {
                if (Str::startsWith($slug, $prefix)) {
                    return $key;
                }
            }
        }

        return 'administration';
    }

    private function actionFor(string $slug): string
    {
        $verb = Str::afterLast($slug, '.');

        return match (true) {
            in_array($verb, ['view', 'read', 'report'], true) => 'view',
            in_array($verb, ['create', 'add', 'store', 'collect', 'enter', 'submit', 'copy', 'whatsapp'], true) => 'add',
            in_array($verb, ['delete', 'destroy', 'archive', 'reverse', 'suspend'], true) => 'delete',
            $verb === 'print' => 'print',
            $verb === 'export' => 'export',
            default => 'edit',
        };
    }

    /**
     * Human page name for a permission, used as a badge in the matrix.
     */
    private function pageLabelFor(string $slug): string
    {
        $items = collect(Navigation::items());
        $area = Str::before($slug, '.');

        if ($area === 'dashboard') {
            return 'Dashboard';
        }

        // 1. A curated override for a specific permission.
        $override = self::PERMISSION_PAGE_OVERRIDES[$slug] ?? null;
        $overridden = $override ? $items->firstWhere('key', $override) : null;
        if ($overridden) {
            return $overridden['title'];
        }

        // 2. A curated hint for the area/action.
        $hintKey = self::PAGE_HINTS[$area] ?? [];
        $hint = $hintKey[$this->actionFor($slug)] ?? $hintKey['*'] ?? null;
        $hinted = $hint ? $items->firstWhere('key', $hint) : null;
        if ($hinted) {
            return $hinted['title'];
        }

        // 3. A page whose required permission is exactly this permission.
        $exact = $items->firstWhere('can', $slug);
        if ($exact) {
            return $exact['title'];
        }

        // 4. A page in the same area with the same action.
        $sameArea = $items->filter(fn (array $item) => Str::before((string) $item['can'], '.') === $area);
        $sameAction = $sameArea->first(fn (array $item) => $this->actionFor((string) $item['can']) === $this->actionFor($slug));
        if ($sameAction) {
            return $sameAction['title'];
        }

        // 5. Fall back to the first page in the same area.
        return $sameArea->first()['title'] ?? 'General';
    }
}
