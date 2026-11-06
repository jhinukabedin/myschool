<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffQualification extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'exam_name', 'institution', 'passing_year', 'result', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'passing_year' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getSummaryAttribute(): string
    {
        return collect([$this->exam_name, $this->passing_year, $this->institution])
            ->filter()
            ->implode(' · ');
    }
}
