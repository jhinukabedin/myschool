<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id', 'amount', 'payment_method', 'reference_no', 'paid_at', 'paid_by',
        'status', 'reversed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(SalaryPaymentReversal::class);
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
