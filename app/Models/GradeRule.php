<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeRule extends Model
{
    use HasFactory;

    protected $fillable = ['grading_scheme_id', 'name', 'min_percent', 'max_percent', 'grade', 'grade_point', 'is_fail'];

    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'is_fail' => 'boolean',
        ];
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('min_percent');
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }
}
