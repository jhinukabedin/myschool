<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoutine extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'class_id', 'section_id', 'effective_from',
        'effective_to', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RoutineEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForContext(Builder $query, int $sessionId, int $classId, int $sectionId): Builder
    {
        return $query
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId);
    }

    /**
     * Effective date shown on screens and printouts. Older routines always have
     * a stored date; the session start is only a defensive fallback.
     */
    public function effectiveDate(): \Illuminate\Support\Carbon
    {
        return $this->effective_from
            ?? $this->academicSession?->start_date
            ?? now();
    }
}
