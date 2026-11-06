<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceAuthorizationService
{
    public function isAdministrator(User $user): bool
    {
        return in_array($user->role?->slug, ['super-admin', 'admin'], true);
    }

    /**
     * Return the sections each user can access for the given session and date range.
     * Administrators can access every section; teachers need an active staff link
     * and a matching active class assignment.
     *
     * @return Collection<int, array<int, int>> class ID => section IDs
     */
    public function classSectionMap(User $user, ?AcademicSession $session, string $from, ?string $to = null): Collection
    {
        if (! $session) {
            return collect();
        }

        $to ??= $from;

        if ($this->isAdministrator($user)) {
            return SchoolClass::query()
                ->with('sections')
                ->ordered()
                ->get()
                ->mapWithKeys(fn (SchoolClass $class) => [$class->id => $class->sections->modelKeys()]);
        }

        $staff = $user->role?->slug === 'teacher' ? $user->staff : null;
        if (! $staff || $staff->status !== 'active') {
            return collect();
        }

        $assignments = $staff->assignments()
            ->where('academic_session_id', $session->id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $to))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
            ->get(['class_id', 'section_id']);

        if ($assignments->isEmpty()) {
            return collect();
        }

        $classes = SchoolClass::query()
            ->with('sections')
            ->whereIn('id', $assignments->pluck('class_id')->unique())
            ->get()
            ->keyBy('id');

        $map = collect();
        foreach ($assignments as $assignment) {
            $class = $classes->get($assignment->class_id);
            if (! $class) {
                continue;
            }

            $sectionIds = $assignment->section_id === null
                ? $class->sections->modelKeys()
                : ($class->sections->contains('id', $assignment->section_id) ? [(int) $assignment->section_id] : []);

            if ($sectionIds !== []) {
                $map->put($class->id, collect($map->get($class->id, []))->merge($sectionIds)->unique()->values()->all());
            }
        }

        return $map;
    }

    public function canAccess(User $user, ?AcademicSession $session, SchoolClass $class, int $sectionId, string $date): bool
    {
        return in_array($sectionId, $this->classSectionMap($user, $session, $date)->get($class->id, []), true);
    }

    /** Limit attendance queries to the classes/sections assigned to this user. */
    public function constrainQuery(Builder $query, User $user, ?AcademicSession $session, string $from, string $to): Builder
    {
        if ($this->isAdministrator($user)) {
            return $query;
        }

        $map = $this->classSectionMap($user, $session, $from, $to);

        if ($map->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($map) {
            foreach ($map as $classId => $sectionIds) {
                $scope->orWhere(fn (Builder $pair) => $pair
                    ->where('class_id', $classId)
                    ->whereIn('section_id', $sectionIds));
            }
        });
    }
}
