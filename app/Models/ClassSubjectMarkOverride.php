<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSubjectMarkOverride extends Model
{
    protected $fillable = ['class_id', 'subject_id', 'full_marks', 'pass_marks', 'components'];

    protected function casts(): array
    {
        return ['full_marks' => 'decimal:2', 'pass_marks' => 'decimal:2', 'components' => 'array'];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
