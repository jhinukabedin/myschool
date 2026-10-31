<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Examination extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'name', 'exam_type', 'grading_scheme_id',
        'start_date', 'end_date', 'status', 'result_published_at',
        'result_locked_at', 'published_by', 'locked_by', 'result_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'result_published_at' => 'datetime',
            'result_locked_at' => 'datetime',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExaminationSubject::class);
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function resultSnapshots(): HasMany
    {
        return $this->hasMany(ResultSnapshot::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->result_published_at !== null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'published' => 'success',
            'completed' => 'info',
            default => 'muted',
        };
    }
}
