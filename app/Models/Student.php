<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'admission_no', 'student_code', 'first_name', 'last_name', 'gender', 'date_of_birth', 'blood_group',
        'religion', 'nationality', 'phone', 'email', 'address', 'city', 'state', 'postal_code',
        'photo_path', 'admission_date', 'previous_school', 'medical_notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function portalLink()
    {
        return $this->hasOne(StudentPortalLink::class);
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class);
    }

    public function primaryGuardian(): ?StudentGuardian
    {
        return $this->guardians->firstWhere('is_primary', true) ?? $this->guardians->first();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function latestEnrollment(): HasMany
    {
        return $this->hasMany(Enrollment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feeAssignments(): HasMany
    {
        return $this->hasMany(StudentFeeAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path && Storage::disk('public')->exists($this->photo_path)
            ? asset('storage/'.$this->photo_path)
            : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            'passed_out' => 'Passed Out',
            'transferred' => 'Transferred',
            'archived' => 'Archived',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'muted',
            'passed_out' => 'info',
            'transferred' => 'warning',
            default => 'muted',
        };
    }

    public function enrollmentFor(int $sessionId): ?Enrollment
    {
        return $this->enrollments->firstWhere('academic_session_id', $sessionId)
            ?? $this->enrollments()->where('academic_session_id', $sessionId)->with(['class', 'section'])->first();
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('admission_no', 'like', "%{$term}%")
                ->orWhere('student_code', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%"));
        });
    }
}
