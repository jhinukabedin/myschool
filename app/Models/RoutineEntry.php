<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_routine_id', 'day', 'routine_time_slot_id', 'subject_id',
        'staff_id', 'classroom', 'notes',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(ClassRoutine::class, 'class_routine_id');
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(RoutineTimeSlot::class, 'routine_time_slot_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
