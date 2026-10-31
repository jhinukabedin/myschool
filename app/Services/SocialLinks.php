<?php

namespace App\Services;

/**
 * Resolves the ordered list of social links shown in the public footer and
 * topbar. The list is stored as a JSON setting so administrators can add any
 * number of common networks from the Footer Area page. Each network carries a
 * short glyph and its official brand colour.
 */
class SocialLinks
{
    public const NETWORKS = [
        'facebook' => ['label' => 'Facebook', 'color' => '#1877F2', 'glyph' => 'f'],
        'x' => ['label' => 'X (Twitter)', 'color' => '#000000', 'glyph' => 'X'],
        'youtube' => ['label' => 'YouTube', 'color' => '#FF0000', 'glyph' => '▶'],
        'instagram' => ['label' => 'Instagram', 'color' => '#E1306C', 'glyph' => '◎'],
        'linkedin' => ['label' => 'LinkedIn', 'color' => '#0A66C2', 'glyph' => 'in'],
        'whatsapp' => ['label' => 'WhatsApp', 'color' => '#25D366', 'glyph' => '✆'],
        'telegram' => ['label' => 'Telegram', 'color' => '#229ED9', 'glyph' => '✈'],
        'tiktok' => ['label' => 'TikTok', 'color' => '#010101', 'glyph' => '♪'],
        'pinterest' => ['label' => 'Pinterest', 'color' => '#E60023', 'glyph' => 'P'],
        'email' => ['label' => 'Email', 'color' => '#EA4335', 'glyph' => '@'],
        'phone' => ['label' => 'Phone', 'color' => '#0EA5E9', 'glyph' => '☎'],
    ];

    /**
     * @return array<string, array{label: string, color: string, glyph: string}>
     */
    public static function networks(): array
    {
        return self::NETWORKS;
    }

    /**
     * @return array<int, array{network: string, url: string, label: string, color: string, glyph: string}>
     */
    public static function parse(?string $json, array $legacy = []): array
    {
        $rows = [];
        $decoded = json_decode((string) $json, true);

        // A decoded array means the repeater has been used, even when it is
        // empty (the administrator removed every link). Only fall back to the
        // legacy settings when the new setting was never saved.
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $network = strtolower(trim((string) ($row['network'] ?? '')));
                $url = trim((string) ($row['url'] ?? ''));

                if ($url === '' || ! isset(self::NETWORKS[$network])) {
                    continue;
                }

                $rows[] = self::make($network, $url);
            }

            return $rows;
        }

        // Fall back to the three legacy settings so existing sites keep their
        // icons until an administrator uses the new repeater.
        foreach (['facebook', 'x', 'youtube'] as $key) {
            $url = trim((string) ($legacy[$key] ?? ''));
            if ($url !== '') {
                $rows[] = self::make($key, $url);
            }
        }

        return $rows;
    }

    /**
     * @return array{network: string, url: string, label: string, color: string, glyph: string}
     */
    private static function make(string $network, string $url): array
    {
        $meta = self::NETWORKS[$network];

        return [
            'network' => $network,
            'url' => $url,
            'label' => $meta['label'],
            'color' => $meta['color'],
            'glyph' => $meta['glyph'],
        ];
    }
}
