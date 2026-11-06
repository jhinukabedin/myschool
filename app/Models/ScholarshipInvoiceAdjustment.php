<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipInvoiceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_scholarship_id', 'student_id', 'student_fee_assignment_id',
        'original_amount', 'discount_amount', 'payable_amount',
        'adjustment_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'payable_amount' => 'decimal:2',
        ];
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(StudentScholarship::class, 'student_scholarship_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(StudentFeeAssignment::class, 'student_fee_assignment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
