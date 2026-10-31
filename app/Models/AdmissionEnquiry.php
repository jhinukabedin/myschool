<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_no', 'student_name', 'guardian_name', 'phone', 'email', 'class_id',
        'follow_up_date', 'source', 'status', 'notes', 'created_by', 'student_id',
    ];

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'new' => 'info',
            'contacted' => 'warning',
            'follow_up' => 'warning',
            'admitted' => 'success',
            default => 'muted',
        };
    }
}
