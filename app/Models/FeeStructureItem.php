<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructureItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_structure_id', 'fee_category_id', 'amount', 'discount_amount',
        'scholarship_type', 'scholarship_value',
        'frequency', 'due_day', 'is_optional',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'scholarship_value' => 'decimal:2',
            'is_optional' => 'boolean',
        ];
    }

    /**
     * Scholarship conceded on this fee line. A percentage is calculated against the
     * gross amount; a fixed value is capped at the gross amount.
     */
    public function scholarshipAmount(): float
    {
        return $this->concessionAmount(
            (float) ($this->attributes['scholarship_value'] ?? 0),
            (string) ($this->attributes['scholarship_type'] ?? 'fixed'),
            (float) ($this->attributes['amount'] ?? 0)
        );
    }

    public function getScholarshipAmountAttribute(): string
    {
        return number_format($this->scholarshipAmount(), 2, '.', '');
    }

    /**
     * Resolve a concession expressed either as a percentage of the gross amount or
     * as a fixed figure, never exceeding the gross amount itself.
     */
    public static function concessionAmount(float $value, string $type, float $gross): float
    {
        if ($value <= 0 || $gross <= 0) {
            return 0.0;
        }

        $conceded = $type === 'percent'
            ? $gross * (min(100, max(0, $value)) / 100)
            : min($value, $gross);

        return max(0, round($conceded, 2));
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentFeeAssignment::class);
    }

    public function getNetAmountAttribute(): string
    {
        $net = (float) ($this->attributes['amount'] ?? 0)
            - (float) ($this->attributes['discount_amount'] ?? 0)
            - $this->scholarshipAmount();

        return number_format(max(0, $net), 2, '.', '');
    }
}
