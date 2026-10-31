<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultSnapshot extends Model
{
    protected $fillable = [
        'examination_id', 'student_id', 'grading_scheme_id', 'grading_rules',
        'result_data', 'total_marks', 'gpa', 'grade', 'result_status',
        'published_at', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'grading_rules' => 'array',
            'result_data' => 'array',
            'total_marks' => 'decimal:2',
            'gpa' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }
}
