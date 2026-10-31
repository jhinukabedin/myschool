<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;

class LoginIdentifierResolver
{
    /**
     * Resolve one login field to a user. Staff may use their email or
     * username; students use their permanent student ID (also stored as the
     * username); parents use their normalised mobile number.
     */
    public function resolve(?string $identifier, ?string $roleSlug = null): ?User
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return null;
        }

        $lower = Str::lower($identifier);
        $scoped = fn () => User::query()->when(
            $roleSlug,
            fn ($query) => $query->whereHas('role', fn ($role) => $role->where('slug', $roleSlug))
        );

        $user = $scoped()->whereRaw('LOWER(email) = ?', [$lower])->first();

        if ($user) {
            return $user;
        }

        $user = $scoped()->whereRaw('LOWER(username) = ?', [$lower])->first();

        if ($user) {
            return $user;
        }

        $phone = normalize_phone($identifier);

        if ($phone) {
            $user = $scoped()->where('phone_normalized', $phone)->first();

            if ($user) {
                return $user;
            }
        }

        // Existing students may have a code backfilled before the account
        // provisioning service stored it as a username.
        $student = Student::query()
            ->where('student_code', $identifier)
            ->with(['portalLink.user.role'])
            ->first();

        if (! $student?->portalLink?->is_active) {
            return null;
        }

        if ($roleSlug && $student->portalLink->user?->role?->slug !== $roleSlug) {
            return null;
        }

        return $student->portalLink->user;
    }
}
