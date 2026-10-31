<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id', 'name', 'email', 'username', 'phone', 'phone_normalized',
        'avatar_path', 'password', 'is_active', 'last_login_at', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function studentPortalLink(): HasOne
    {
        return $this->hasOne(StudentPortalLink::class);
    }

    public function parentStudentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class);
    }

    public function linkedStudent(): ?Student
    {
        return $this->studentPortalLink()
            ->where('is_active', true)
            ->with('student')
            ->first()
            ?->student;
    }

    public function linkedChildren()
    {
        $ids = $this->parentStudentLinks()
            ->where('is_active', true)
            ->pluck('student_id');

        return Student::query()
            ->whereIn('id', $ids)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * The landing page for this account. Portal accounts never land in the
     * administration dashboard even if they open /login by mistake.
     */
    public function homeRouteName(): string
    {
        return match ($this->role?->slug) {
            'student' => 'student.dashboard',
            'parent' => 'parent.dashboard',
            'teacher' => 'teacher.dashboard',
            default => 'dashboard',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === 'super-admin';
    }

    /**
     * Check a granular permission through the user's role.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->role_id) {
            return false;
        }

        $permissions = Cache::remember(
            "role_permissions_{$this->role_id}",
            now()->addMinutes(30),
            fn () => $this->role?->permissions()->pluck('slug')->all() ?? []
        );

        return in_array($permission, $permissions, true);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }
}
