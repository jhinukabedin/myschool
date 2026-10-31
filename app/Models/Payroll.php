<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'staff_id', 'month', 'basic_salary', 'total_allowances',
        'total_deductions', 'net_salary', 'status', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Convenience accessor for the latest completed installment.
     *
     * This is NOT the source of truth for how much has been paid now that a
     * payroll may receive several installments. Use paidAmount() /
     * outstandingAmount() / completedPayments() for balances. Reversed rows
     * stay in the database for audit purposes and are excluded here.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(SalaryPayment::class)->ofMany(['id' => 'max'], function (Builder $query) {
            $query->where('status', 'completed');
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    /**
     * Valid (non-reversed) installments. Reversed rows are kept for audit but
     * must never count toward the amount paid.
     */
    public function completedPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class)->where('status', 'completed');
    }

    /**
     * Total valid paid amount across every completed installment. This is the
     * authoritative figure; never infer "fully paid" from a single payment row.
     */
    public function paidAmount(): float
    {
        $sum = $this->relationLoaded('completedPayments')
            ? $this->completedPayments->sum('amount')
            : $this->completedPayments()->sum('amount');

        return round((float) $sum, 2);
    }

    public function outstandingAmount(): float
    {
        return round(max(0, (float) $this->net_salary - $this->paidAmount()), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->paidAmount() > 0 && $this->outstandingAmount() <= 0.009;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paidAmount() > 0 && $this->outstandingAmount() > 0.009;
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
