<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name', 'numeric_level'];

    protected $casts = ['numeric_level' => 'integer'];

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'class_section', 'class_id', 'section_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class, Enrollment::class, 'class_id', 'id', 'id', 'student_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'class_id');
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(ExaminationSubject::class, 'class_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('numeric_level')->orderBy('name');
    }

    public function getLabelAttribute(): string
    {
        return $this->name;
    }
}
