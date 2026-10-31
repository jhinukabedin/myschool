<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'basic_salary', 'house_allowance', 'transport_allowance',
        'medical_allowance', 'other_allowance', 'tax_deduction',
        'provident_fund', 'other_deduction', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'house_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'medical_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
            'tax_deduction' => 'decimal:2',
            'provident_fund' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getTotalAllowancesAttribute(): float
    {
        return (float) $this->house_allowance + (float) $this->transport_allowance
            + (float) $this->medical_allowance + (float) $this->other_allowance;
    }

    public function getTotalDeductionsAttribute(): float
    {
        return (float) $this->tax_deduction + (float) $this->provident_fund + (float) $this->other_deduction;
    }

    public function getNetSalaryAttribute(): float
    {
        return (float) $this->basic_salary + $this->total_allowances - $this->total_deductions;
    }
}
