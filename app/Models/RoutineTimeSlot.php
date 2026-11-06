<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RoutineTimeSlot extends Model
{
    use HasFactory;

    public const TYPES = [
        'class' => 'Teaching period',
        'break' => 'Break',
        'lunch' => 'Lunch break',
        'assembly' => 'Assembly',
    ];

    protected $fillable = [
        'class_id', 'name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RoutineEntry::class, 'routine_time_slot_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTeaching(Builder $query): Builder
    {
        return $query->where('type', 'class');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('start_time')->orderBy('id');
    }

    public function isTeaching(): bool
    {
        return $this->type === 'class';
    }

    public function getStartLabelAttribute(): string
    {
        return $this->clock((string) $this->start_time);
    }

    public function getEndLabelAttribute(): string
    {
        return $this->clock((string) $this->end_time);
    }

    public function getTimeRangeAttribute(): string
    {
        return $this->start_label.' – '.$this->end_label;
    }

    public function getTypeLabelAttribute(): string
    {
        return __(self::TYPES[$this->type] ?? ucfirst((string) $this->type));
    }

    /**
     * The label rendered inside a non-teaching timetable cell. Lunch is kept
     * explicit so it is visible in every day row under the same time column.
     */
    public function getBreakLabelAttribute(): string
    {
        return match ($this->type) {
            'lunch' => __('LUNCH BREAK'),
            'break' => __(strtoupper($this->name ?: 'BREAK')),
            'assembly' => __(strtoupper($this->name ?: 'ASSEMBLY')),
            default => __(strtoupper($this->name ?: 'BREAK')),
        };
    }

    private function clock(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $value;
        }
    }
}
