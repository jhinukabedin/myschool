<?php

namespace App\Services;

use App\Models\ClassRoutine;
use App\Models\RoutineEntry;
use App\Models\RoutineTimeSlot;
use App\Models\SchoolSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for the class routine module: configurable working days,
 * timetable cell lookup, time slot overlap checks and cross-routine conflicts.
 */
class RoutineScheduleService
{
    /**
     * Canonical day keys. Only the configured subset and order reaches a view.
     *
     * @var array<string, string>
     */
    public const DAYS = [
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ];

    public const WORKING_DAYS_KEY = 'routine_working_days';

    /**
     * @return array<string, string> Ordered working day key => label.
     */
    public function workingDays(): array
    {
        $raw = school_setting(self::WORKING_DAYS_KEY);

        $configured = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($configured) || $configured === []) {
            return self::DAYS;
        }

        $days = [];

        foreach ($configured as $day) {
            if (is_string($day) && isset(self::DAYS[$day]) && ! isset($days[$day])) {
                $days[$day] = self::DAYS[$day];
            }
        }

        return $days ?: self::DAYS;
    }

    /**
     * @return array<int, string>
     */
    public function workingDayKeys(): array
    {
        return array_keys($this->workingDays());
    }

    /**
     * Save the selected days in the requested display order. The form sends a
     * numeric order per day; ties fall back to the canonical weekday order.
     *
     * @param  array<int, string>  $days
     * @param  array<string, int|string|null>  $orders
     */
    public function saveWorkingDays(array $days, array $orders = []): void
    {
        $days = collect($days)
            ->filter(fn ($day) => is_string($day) && isset(self::DAYS[$day]))
            ->unique()
            ->values()
            ->all();

        if ($days === []) {
            throw ValidationException::withMessages([
                'days' => 'Choose at least one working day for the timetable.',
            ]);
        }

        $canonical = array_flip(array_keys(self::DAYS));

        usort($days, function (string $a, string $b) use ($orders, $canonical) {
            $aOrder = is_numeric($orders[$a] ?? null) ? (int) $orders[$a] : 999;
            $bOrder = is_numeric($orders[$b] ?? null) ? (int) $orders[$b] : 999;

            return [$aOrder, $canonical[$a] ?? 999] <=> [$bOrder, $canonical[$b] ?? 999];
        });

        SchoolSetting::put(
            self::WORKING_DAYS_KEY,
            json_encode(array_values($days), JSON_UNESCAPED_UNICODE),
            'academic'
        );
    }

    public function dayLabel(string $day): string
    {
        return self::DAYS[$day] ?? ucfirst($day);
    }

    /**
     * @return Collection<int, RoutineTimeSlot>
     */
    public function activeSlots(?int $classId = null): Collection
    {
        return $this->slotQueryForClass($classId)->active()->ordered()->get();
    }

    /**
     * @return Collection<int, RoutineTimeSlot>
     */
    public function allSlots(?int $classId = null): Collection
    {
        return $this->slotQueryForClass($classId)->ordered()->get();
    }

    public function hasCustomSlots(int $classId): bool
    {
        return RoutineTimeSlot::query()->where('class_id', $classId)->exists();
    }

    public function hasAnyActiveSlots(): bool
    {
        return RoutineTimeSlot::query()->active()->exists();
    }

    /**
     * Merge the periods needed by a teacher's classes without duplicating
     * shared defaults. Class-specific periods remain separate columns.
     *
     * @param  array<int, int>  $classIds
     * @return Collection<int, RoutineTimeSlot>
     */
    public function activeSlotsForClasses(array $classIds): Collection
    {
        return collect($classIds)
            ->unique()
            ->flatMap(fn ($classId) => $this->activeSlots((int) $classId))
            ->unique('id')
            ->sortBy(fn (RoutineTimeSlot $slot) => [$slot->start_time, $slot->class_id ?? 0, $slot->sort_order])
            ->values();
    }

    public function resolveSlotForClass(int $classId, RoutineTimeSlot $source): ?RoutineTimeSlot
    {
        return $this->activeSlots($classId)->first(fn (RoutineTimeSlot $slot) =>
            $slot->name === $source->name
            && $slot->start_time === $source->start_time
            && $slot->end_time === $source->end_time
            && $slot->type === $source->type
        );
    }

    private function slotQueryForClass(?int $classId)
    {
        $query = RoutineTimeSlot::query();

        if ($classId !== null && $this->hasCustomSlots($classId)) {
            return $query->where('class_id', $classId);
        }

        return $query->whereNull('class_id');
    }

    /**
     * Entries keyed by day and slot so the Blade table never needs a nested
     * database query for every cell.
     *
     * @return Collection<string, RoutineEntry>
     */
    public function entryMap(ClassRoutine $routine): Collection
    {
        $routine->loadMissing([
            'entries.subject',
            'entries.teacher',
            'entries.timeSlot',
        ]);

        return $routine->entries->keyBy(fn (RoutineEntry $entry) => $this->cellKey($entry->day, $entry->routine_time_slot_id));
    }

    public function cellKey(string $day, int|string $slotId): string
    {
        return $day.'|'.$slotId;
    }

    /**
     * Create or update one timetable cell after checking every scheduling
     * conflict that can be determined server-side.
     *
     * @param  array{day:string,routine_time_slot_id:int|string,subject_id:int|string,staff_id:int|string,classroom?:string|null,notes?:string|null}  $data
     */
    public function saveEntry(ClassRoutine $routine, array $data, ?RoutineEntry $entry = null): RoutineEntry
    {
        $day = (string) $data['day'];
        $slot = RoutineTimeSlot::query()->findOrFail((int) $data['routine_time_slot_id']);

        if (! array_key_exists($day, $this->workingDays())) {
            throw ValidationException::withMessages([
                'day' => 'That day is not a configured working day.',
            ]);
        }

        if (! $slot->is_active) {
            throw ValidationException::withMessages([
                'routine_time_slot_id' => 'This time slot is inactive and cannot receive a subject.',
            ]);
        }

        if (! $slot->isTeaching()) {
            throw ValidationException::withMessages([
                'routine_time_slot_id' => 'Lunch, breaks and assemblies are non-teaching periods. They cannot contain a subject.',
            ]);
        }

        $classHasCustomSlots = $this->hasCustomSlots((int) $routine->class_id);
        if ($classHasCustomSlots && (int) $slot->class_id !== (int) $routine->class_id) {
            throw ValidationException::withMessages([
                'routine_time_slot_id' => 'This period does not belong to the selected class schedule. Refresh the timetable and try again.',
            ]);
        }

        if (! $classHasCustomSlots && $slot->class_id !== null) {
            throw ValidationException::withMessages([
                'routine_time_slot_id' => 'This class is not configured to use that period. Refresh the timetable and try again.',
            ]);
        }

        $existingCell = RoutineEntry::query()
            ->where('class_routine_id', $routine->id)
            ->where('day', $day)
            ->where('routine_time_slot_id', $slot->id)
            ->when($entry, fn ($query) => $query->whereKeyNot($entry->id))
            ->exists();

        if ($existingCell) {
            throw ValidationException::withMessages([
                'routine_time_slot_id' => 'This day and time slot already has a subject. Edit the existing cell instead.',
            ]);
        }

        $this->assertTeacherAvailable($routine, $data, $slot, $entry);
        $this->assertClassroomAvailable($routine, $data, $slot, $entry);

        $overlappingClassEntry = RoutineEntry::query()
            ->where('class_routine_id', $routine->id)
            ->where('day', $day)
            ->when($entry, fn ($query) => $query->whereKeyNot($entry->id))
            ->whereHas('timeSlot', fn ($query) => $query
                ->where('start_time', '<', $slot->end_time)
                ->where('end_time', '>', $slot->start_time))
            ->exists();

        if ($overlappingClassEntry) {
            throw ValidationException::withMessages([
                'routine_time_slot_id' => 'This class and section already has a subject in an overlapping period.',
            ]);
        }

        $payload = [
            'day' => $day,
            'routine_time_slot_id' => $slot->id,
            'subject_id' => (int) $data['subject_id'],
            'staff_id' => (int) $data['staff_id'],
            'classroom' => filled($data['classroom'] ?? null) ? trim((string) $data['classroom']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ];

        if ($entry) {
            $entry->update($payload);

            return $entry->refresh();
        }

        return $routine->entries()->create($payload);
    }

    /**
     * Reject overlapping active periods. Two periods may share a boundary
     * (08:45 ends, 08:45 starts) but may not overlap by a minute.
     *
     * @param  array{start_time:string,end_time:string}  $data
     */
    public function assertSlotDoesNotOverlap(array $data, ?int $ignoreId = null): void
    {
        $overlap = RoutineTimeSlot::query()
            ->where('class_id', $data['class_id'] ?? null)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->orderBy('start_time')
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_time' => 'This time range overlaps "'.$overlap->name.'" ('.$overlap->time_range.'). Adjust the start or end time.',
            ]);
        }
    }

    private function assertTeacherAvailable(
        ClassRoutine $routine,
        array $data,
        RoutineTimeSlot $slot,
        ?RoutineEntry $ignore
    ): void {
        $conflict = RoutineEntry::query()
            ->with(['routine.class', 'routine.section', 'timeSlot', 'subject'])
            ->where('day', $data['day'])
            ->where('staff_id', (int) $data['staff_id'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->whereHas('routine', fn ($query) => $query
                ->where('academic_session_id', $routine->academic_session_id))
            ->whereHas('timeSlot', fn ($query) => $query
                ->where('start_time', '<', $slot->end_time)
                ->where('end_time', '>', $slot->start_time))
            ->first();

        if (! $conflict) {
            return;
        }

        $class = $conflict->routine?->class?->name ?? 'another class';
        $section = $conflict->routine?->section?->name;
        $context = $class.($section ? ' Section '.$section : '');

        throw ValidationException::withMessages([
            'staff_id' => 'This teacher is already scheduled for '.$context.' at '.$conflict->timeSlot?->time_range.' on '.$this->dayLabel((string) $data['day']).'.',
        ]);
    }

    private function assertClassroomAvailable(
        ClassRoutine $routine,
        array $data,
        RoutineTimeSlot $slot,
        ?RoutineEntry $ignore
    ): void {
        $room = trim((string) ($data['classroom'] ?? ''));

        if ($room === '') {
            return;
        }

        $conflict = RoutineEntry::query()
            ->with(['routine.class', 'routine.section', 'timeSlot'])
            ->where('day', $data['day'])
            ->whereRaw('LOWER(TRIM(classroom)) = ?', [mb_strtolower($room)])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->whereHas('routine', fn ($query) => $query
                ->where('academic_session_id', $routine->academic_session_id))
            ->whereHas('timeSlot', fn ($query) => $query
                ->where('start_time', '<', $slot->end_time)
                ->where('end_time', '>', $slot->start_time))
            ->first();

        if (! $conflict) {
            return;
        }

        $class = $conflict->routine?->class?->name ?? 'another class';
        $section = $conflict->routine?->section?->name;

        throw ValidationException::withMessages([
            'classroom' => $room.' is already used by '.$class.($section ? ' Section '.$section : '').' at '.$conflict->timeSlot?->time_range.' on '.$this->dayLabel((string) $data['day']).'.',
        ]);
    }
}
