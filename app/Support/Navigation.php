<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reads the admin navigation catalogue.
 *
 * The sidebar and the subscription screen both use this class, so a menu
 * added to config/navigation.php is rendered in both places automatically.
 */
class Navigation
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $items = null;

    /** @var array<string, array<string, mixed>> */
    private static array $map = [];

    /** @var array<string, array<string, mixed>|null> */
    private static array $routeCache = [];

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return config('navigation.groups', []);
    }

    /**
     * Every menu item, flattened, with its parent group label attached.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function items(): array
    {
        if (self::$items !== null) {
            return self::$items;
        }

        self::$items = [];
        self::$map = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (empty($item['key'])) {
                    continue;
                }

                $item['group'] = $group['label'] ?? '';
                self::$items[] = $item;
                self::$map[$item['key']] = $item;
            }
        }

        return self::$items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function item(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        self::items();

        return self::$map[$key] ?? null;
    }

    /**
     * Items that belong to one subscription module.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forModule(?string $module): array
    {
        if (! $module) {
            return [];
        }

        return array_values(array_filter(
            self::items(),
            fn (array $item) => ($item['module'] ?? null) === $module
        ));
    }

    /**
     * The menu that owns a route name, matching the most specific pattern.
     *
     * @return array<string, mixed>|null
     */
    public static function find(?string $routeName): ?array
    {
        if (! $routeName) {
            return null;
        }

        if (array_key_exists($routeName, self::$routeCache)) {
            return self::$routeCache[$routeName];
        }

        $best = null;
        $bestLength = -1;

        foreach (self::items() as $item) {
            foreach (explode('|', (string) ($item['pattern'] ?? '')) as $pattern) {
                $pattern = trim($pattern);

                if ($pattern === '' || ! Str::is($pattern, $routeName)) {
                    continue;
                }

                // Prefer the longest matching pattern (an exact route beats a wildcard).
                if (strlen($pattern) > $bestLength) {
                    $best = $item;
                    $bestLength = strlen($pattern);
                }
            }
        }

        return self::$routeCache[$routeName] = $best;
    }
}
