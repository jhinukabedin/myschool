<?php

namespace App\Services;

/**
 * Controls which blocks of the public homepage are visible. Selected keys are
 * stored as a JSON setting; when the setting is missing every block stays on so
 * existing sites are unchanged.
 */
class HomeSections
{
    public const SECTIONS = [
        'hero' => 'Hero slider & quick links',
        'introduction' => 'Introduction & notice board',
        'messages' => 'Administration messages',
        'academic' => 'Academic corner',
        'news' => 'News, events & gallery',
        'stats' => 'At a glance (statistics)',
        'contact' => 'Contact & Google map',
    ];

    /**
     * @return array<int, string>
     */
    public static function enabled(?string $json): array
    {
        $all = array_keys(self::SECTIONS);

        if ($json === null || trim((string) $json) === '') {
            return $all;
        }

        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded)) {
            return $all;
        }

        return array_values(array_intersect($all, array_map('strval', $decoded)));
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::SECTIONS);
    }
}
