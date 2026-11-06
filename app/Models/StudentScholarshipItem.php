<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScholarshipItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_scholarship_id', 'fee_category_id', 'discount_type',
        'discount_value', 'discount_frequency', 'maximum_discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'maximum_discount_amount' => 'decimal:2',
        ];
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(StudentScholarship::class, 'student_scholarship_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }
}
