<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtherIncome extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'other_income_category_id', 'title', 'amount',
        'income_date', 'reference', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'income_date' => 'date',
        ];
    }

    /**
     * Line items of this income invoice (multiple receipts per entry).
     */
    public function items(): HasMany
    {
        return $this->hasMany(OtherIncomeItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OtherIncomeCategory::class, 'other_income_category_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForSession(Builder $query, ?AcademicSession $session): Builder
    {
        return $session ? $query->where('academic_session_id', $session->id) : $query;
    }
}
