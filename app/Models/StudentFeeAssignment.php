<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFeeAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'fee_structure_id', 'fee_structure_item_id', 'fee_category_id',
        'academic_session_id', 'period', 'amount', 'discount_amount', 'waiver_amount',
        'scholarship_type', 'scholarship_value', 'scholarship_amount',
        'net_amount', 'due_date', 'status', 'note',
    ];

    public function scholarshipAdjustments(): HasMany
    {
        return $this->hasMany(ScholarshipInvoiceAdjustment::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'scholarship_value' => 'decimal:2',
            'scholarship_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    /**
     * Percentage or fixed scholarship granted against this student's fee row.
     */
    public function getScholarshipLabelAttribute(): string
    {
        if ((float) $this->scholarship_amount <= 0) {
            return '—';
        }

        return $this->scholarship_type === 'percent'
            ? __(':pct% concession', ['pct' => rtrim(rtrim(number_format((float) $this->scholarship_value, 2, '.', ''), '0'), '.')])
            : money($this->scholarship_amount).' '.__('fixed scholarship');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FeeStructureItem::class, 'fee_structure_item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Only allocations belonging to completed (non-reversed) payments count as paid.
     */
    public function activeAllocations(): HasMany
    {
        return $this->paymentAllocations()
            ->whereHas('payment', fn (Builder $query) => $query->where('status', 'completed'));
    }

    public function getPaidAmountAttribute(): float
    {
        if (array_key_exists('paid_total', $this->attributes)) {
            return (float) $this->attributes['paid_total'];
        }

        return (float) $this->activeAllocations()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return round(max(0, (float) $this->net_amount - $this->paid_amount), 2);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }
}
