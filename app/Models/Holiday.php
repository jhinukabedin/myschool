<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use HasFactory;

    public const TYPES = [
        'public_holiday' => 'Public holiday',
        'vacation' => 'Vacation',
        'event' => 'School event',
        'exam' => 'Exam day',
        'other' => 'Other',
    ];

    public const DAYS = [
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday',
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
    ];

    protected $fillable = [
        'name', 'type', 'start_date', 'end_date', 'applies_to', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function scopeCovering(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day);
    }

    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->whereIn('applies_to', ['all', $audience]);
    }

    /**
     * Weekly off days configured for the school (default: Friday).
     *
     * @return array<int, string>
     */
    public static function weeklyOffDays(): array
    {
        $stored = SchoolSetting::get('holiday_weekdays');

        if (! $stored) {
            return ['Friday'];
        }

        $days = is_array($stored) ? $stored : json_decode((string) $stored, true);

        return is_array($days) ? array_values(array_intersect($days, array_keys(self::DAYS))) : ['Friday'];
    }

    /**
     * The holiday (or weekly off) that applies to an audience on a date.
     *
     * @return array{name: string, type: string, is_weekly_off: bool}|null
     */
    public static function forDate(CarbonInterface|string $date, string $audience = 'students'): ?array
    {
        $day = Carbon::parse($date)->startOfDay();

        $holiday = static::query()
            ->covering($day)
            ->forAudience($audience)
            ->orderBy('start_date')
            ->first();

        if ($holiday) {
            return [
                'name' => $holiday->name,
                'type' => $holiday->type_label,
                'is_weekly_off' => false,
            ];
        }

        if (in_array($day->format('l'), self::weeklyOffDays(), true)) {
            return [
                'name' => $day->format('l').' weekly off',
                'type' => 'Weekly holiday',
                'is_weekly_off' => true,
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{date: string, label: string, type: string, weekly: bool}>
     */
    public static function calendarFor(\DateTimeInterface|string|null $from, \DateTimeInterface|string|null $to, string $audience = 'students'): array
    {
        $start = Carbon::parse($from ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($to ?? now()->endOfMonth())->startOfDay();

        if ($start->greaterThan($end)) {
            return [];
        }

        $days = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $entry = static::forDate($cursor, $audience);

            if ($entry) {
                $days[] = [
                    'date' => $cursor->toDateString(),
                    'label' => $entry['name'],
                    'type' => $entry['type'],
                    'weekly' => $entry['is_weekly_off'],
                ];
            }
        }

        return $days;
    }
}
