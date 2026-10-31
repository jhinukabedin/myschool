<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\StudentPortalLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountProvisioningService
{
    public function __construct(private readonly StudentCodeService $studentCodes) {}

    /**
     * Create (or repair) the student and parent portal accounts immediately
     * after an admission. This runs inside the caller's database transaction.
     *
     * @return array{student_code: string, student_username: string, student_created: bool, parent_username: ?string, parent_created: bool, default_password: ?string}
     */
    public function provisionForAdmission(
        Student $student,
        Enrollment $enrollment,
        StudentGuardian $guardian,
        ?User $actor = null
    ): array {
        return DB::transaction(function () use ($student, $enrollment, $guardian, $actor) {
            $studentRole = $this->role('student');
            $studentCode = $this->studentCodes->ensure($student, $enrollment);
            [$studentUser, $studentCreated] = $this->studentUser($student, $studentCode, $studentRole);
            [$parentUser, $parentCreated] = $this->parentUser($guardian, $this->role('parent'));

            StudentPortalLink::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'user_id' => $studentUser->id,
                    'verified_at' => now(),
                    'is_active' => true,
                    'created_by' => $actor?->id,
                ]
            );

            if ($parentUser) {
                ParentStudentLink::query()->updateOrCreate(
                    ['user_id' => $parentUser->id, 'student_id' => $student->id],
                    [
                        'student_guardian_id' => $guardian->id,
                        'relationship' => $guardian->relation,
                        'verified_at' => now(),
                        'is_active' => true,
                        'created_by' => $actor?->id,
                    ]
                );
            }

            return [
                'student_code' => $studentCode,
                'student_username' => $studentUser->username ?: $studentCode,
                'student_created' => $studentCreated,
                'parent_username' => $parentUser?->username,
                'parent_created' => $parentCreated,
                'default_password' => ($studentCreated || $parentCreated) ? portal_default_password() : null,
            ];
        });
    }

    /**
     * Create or update a staff login account. Existing passwords are never
     * reset when a profile is edited.
     *
     * @return array{user: User, default_password: ?string}
     */
    public function provisionStaff(
        Staff $staff,
        Role $role,
        ?string $preferredUsername = null,
        ?User $actor = null
    ): array {
        return DB::transaction(function () use ($staff, $role, $preferredUsername) {
            $user = $staff->user;
            $email = $staff->email;
            $email = $this->availableEmail($email, $user?->id);

            if (filled($staff->email) && ! $email) {
                throw ValidationException::withMessages([
                    'email' => 'This email address is already used by another account.',
                ]);
            }

            $username = $user?->username
                ?: $this->uniqueUsername($preferredUsername ?: $staff->email ?: $staff->employee_no ?: $staff->full_name);
            $created = false;

            if (! $user) {
                $user = User::query()->create([
                    'role_id' => $role->id,
                    'name' => $staff->full_name,
                    'email' => $email,
                    'username' => $username,
                    'phone' => $staff->phone,
                    'phone_normalized' => normalize_phone($staff->phone),
                    'avatar_path' => $staff->photo_path,
                    'password' => portal_default_password(),
                    'is_active' => $staff->status === 'active',
                    'email_verified_at' => $email ? now() : null,
                ]);
                $created = true;
            } else {
                $user->fill([
                    'role_id' => $role->id,
                    'name' => $staff->full_name,
                    'email' => $email,
                    'phone' => $staff->phone,
                    'phone_normalized' => normalize_phone($staff->phone),
                    'avatar_path' => $user->avatar_path ?: $staff->photo_path,
                    'is_active' => $staff->status === 'active' ? true : $user->is_active,
                ]);
                $user->save();
            }

            if ((int) $staff->user_id !== (int) $user->id) {
                $staff->forceFill(['user_id' => $user->id])->save();
            }

            return [
                'user' => $user->refresh(),
                'default_password' => $created ? portal_default_password() : null,
            ];
        });
    }

    public function uniqueUsername(?string $preferred): string
    {
        $base = Str::lower(trim((string) $preferred));
        $base = preg_replace('/[^a-z0-9._-]+/u', '.', $base) ?: 'user';
        $base = trim($base, '.-_') ?: 'user';
        $base = Str::limit($base, 60, '');
        $candidate = $base;
        $suffix = 1;

        while ($this->usernameExists($candidate)) {
            $candidate = $base.'-'.(++$suffix);
        }

        return $candidate;
    }

    public function usernameExists(string $username, ?int $ignoreUserId = null): bool
    {
        return User::withTrashed()
            ->whereRaw('LOWER(username) = ?', [Str::lower($username)])
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();
    }

    public function availableEmail(?string $email, ?int $ignoreUserId = null): ?string
    {
        $email = Str::lower(trim((string) $email));

        if ($email === '') {
            return null;
        }

        $taken = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();

        return $taken ? null : $email;
    }

    /**
     * @return array{0: User, 1: bool}
     */
    private function studentUser(Student $student, string $studentCode, Role $role): array
    {
        $user = StudentPortalLink::query()
            ->where('student_id', $student->id)
            ->with('user')
            ->first()
            ?->user;

        $user ??= User::query()->where('username', $studentCode)->first();
        $created = false;

        $email = $this->availableEmail($student->email, $user?->id);

        if (! $user) {
            $user = User::query()->create([
                'role_id' => $role->id,
                'name' => $student->full_name,
                'email' => $email,
                'username' => $studentCode,
                'phone' => $student->phone,
                'phone_normalized' => normalize_phone($student->phone),
                'avatar_path' => $student->photo_path,
                'password' => portal_default_password(),
                'is_active' => true,
                'email_verified_at' => $email ? now() : null,
            ]);
            $created = true;
        } else {
            $user->fill([
                'role_id' => $role->id,
                'name' => $student->full_name,
                'email' => $user->email ?: $email,
                'username' => $studentCode,
                'phone' => $user->phone ?: $student->phone,
                'phone_normalized' => $user->phone_normalized ?: normalize_phone($student->phone),
                'avatar_path' => $student->photo_path ?: $user->avatar_path,
            ]);
            $user->save();
        }

        return [$user, $created];
    }

    /**
     * @return array{0: ?User, 1: bool}
     */
    private function parentUser(StudentGuardian $guardian, Role $role): array
    {
        $normalized = normalize_phone($guardian->phone);

        if (! $normalized) {
            return [null, false];
        }

        $user = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'parent'))
            ->where(function ($query) use ($normalized) {
                $query->where('username', $normalized)
                    ->orWhere('phone_normalized', $normalized);
            })
            ->first();

        $email = $this->availableEmail($guardian->email, $user?->id);
        $created = false;

        if (! $user) {
            $user = User::query()->create([
                'role_id' => $role->id,
                'name' => $guardian->name,
                'email' => $email,
                'username' => $this->uniqueUsername($normalized),
                'phone' => $guardian->phone,
                'phone_normalized' => $normalized,
                'password' => portal_default_password(),
                'is_active' => true,
                'email_verified_at' => $email ? now() : null,
            ]);
            $created = true;
        } else {
            $user->fill([
                'email' => $user->email ?: $email,
                'username' => $user->username ?: $this->uniqueUsername($normalized),
                'phone' => $user->phone ?: $guardian->phone,
                'phone_normalized' => $normalized,
            ]);
            $user->save();
        }

        return [$user, $created];
    }

    private function role(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->firstOrFail();
    }
}
