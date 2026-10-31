<?php

namespace App\Support;

/**
 * The icon set offered to the homepage featured menu and the academic corner
 * cards. Labels are shared so both managers show identical choices.
 */
class WebsiteIcons
{
    /** @var array<string, string> */
    public const CHOICES = [
        '' => 'Default (plain square)',
        'calendar' => 'Calendar — class routine',
        'bell' => 'Bell — notice board',
        'graduation-cap' => 'Graduation cap — academic',
        'users' => 'Users — students',
        'user' => 'User — teacher',
        'book-open' => 'Book — library / syllabus',
        'file-text' => 'Document — file',
        'download' => 'Download',
        'clipboard-list' => 'Clipboard — routine',
        'send' => 'Send — online apply',
        'phone' => 'Phone — contact',
        'mail' => 'Mail — email',
        'map-pin' => 'Map pin — location',
        'building' => 'Building — campus',
        'bus' => 'Bus — transport',
        'wallet' => 'Wallet — fees',
        'tag' => 'Tag — admission',
        'briefcase' => 'Briefcase — staff',
        'school' => 'School',
    ];

    /**
     * Whether the stored category value is one of the selectable icons.
     */
    public static function exists(?string $name): bool
    {
        return is_string($name) && $name !== '' && array_key_exists($name, self::CHOICES);
    }

    public static function label(?string $name): ?string
    {
        return is_string($name) ? (self::CHOICES[$name] ?? null) : null;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_values(array_filter(array_keys(self::CHOICES), fn (string $name) => $name !== ''));
    }
}
