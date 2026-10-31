<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mark extends Model
{
    use HasFactory;

    protected $fillable = [
        'examination_subject_id', 'student_id', 'marks_obtained', 'component_marks',
        'is_absent', 'grade', 'grade_point', 'entered_by',
        'status', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'updated_by',
        'special_status', 'special_note',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'component_marks' => 'array',
            'grade_point' => 'decimal:2',
            'is_absent' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function examinationSubject(): BelongsTo
    {
        return $this->belongsTo(ExaminationSubject::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ResultCorrection::class)->latest();
    }
}
