<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\RoutineTimeSlot;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\RoutineScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoutineTimeSlotController extends Controller
{
    public function __construct(
        private readonly RoutineScheduleService $schedule,
        private readonly AuditLogService $audit
    ) {}

    public function index(Request $request): View
    {
        $class = $request->filled('class_id') ? SchoolClass::findOrFail($request->integer('class_id')) : null;
        $slots = $this->schedule->allSlots($class?->id)->loadCount('entries');

        return view('academics.routines.settings', [
            'slots' => $slots,
            'classes' => SchoolClass::ordered()->get(),
            'selectedClass' => $class,
            'hasCustomSlots' => $class ? $this->schedule->hasCustomSlots($class->id) : false,
            'days' => $this->schedule->workingDays(),
            'allDays' => RoutineScheduleService::DAYS,
            'types' => RoutineTimeSlot::TYPES,
            'nextOrder' => ((int) $slots->max('sort_order')) + 1,
        ]);
    }

    public function updateDays(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['string', Rule::in(array_keys(RoutineScheduleService::DAYS))],
            'day_order' => ['nullable', 'array'],
            'day_order.*' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $this->schedule->saveWorkingDays($data['days'], $data['day_order'] ?? []);

        $this->audit->record(
            'routine_working_days_updated',
            'Academics',
            'Updated working days and timetable day order',
            null,
            null,
            ['days' => array_values($this->schedule->workingDayKeys())]
        );

        return redirect()->route('routines.settings')->with('success', 'Working days updated. Timetable rows now follow this order.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['class_id'] && ! $this->schedule->hasCustomSlots($data['class_id'])
            && RoutineTimeSlot::query()->whereNull('class_id')->exists()) {
            throw ValidationException::withMessages([
                'class_id' => 'Copy the shared periods to this class before adding its first custom period.',
            ]);
        }

        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be later than the start time.',
            ]);
        }

        $this->schedule->assertSlotDoesNotOverlap($data);

        if (! isset($data['sort_order']) || $data['sort_order'] === null) {
            $data['sort_order'] = ((int) RoutineTimeSlot::query()->where('class_id', $data['class_id'])->max('sort_order')) + 1;
        }

        $slot = RoutineTimeSlot::create($data);

        $this->audit->record(
            'routine_time_slot_created',
            'Academics',
            "Created timetable period {$slot->name} ({$slot->time_range})",
            $slot,
            null,
            $slot->only(['class_id', 'name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active'])
        );

        return redirect()->route('routines.settings', array_filter(['class_id' => $slot->class_id]))->with('success', 'Time slot added.');
    }

    public function update(Request $request, RoutineTimeSlot $timeSlot): RedirectResponse
    {
        $data = $this->validated($request);
        $data['class_id'] = $timeSlot->class_id;

        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be later than the start time.',
            ]);
        }

        $this->schedule->assertSlotDoesNotOverlap($data, $timeSlot->id);

        if ((! $data['is_active'] || $data['type'] !== 'class') && $timeSlot->entries()->exists()) {
            throw ValidationException::withMessages([
                'type' => 'This period already contains scheduled subjects. Move or clear those timetable cells before disabling it or changing it to a non-teaching period.',
            ]);
        }

        $before = $timeSlot->only(['class_id', 'name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active']);
        $data['sort_order'] = $data['sort_order'] ?? $timeSlot->sort_order;
        $timeSlot->update($data);

        $this->audit->record(
            'routine_time_slot_updated',
            'Academics',
            "Updated timetable period {$timeSlot->name}",
            $timeSlot,
            $before,
            $timeSlot->only(['class_id', 'name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active'])
        );

        return redirect()->route('routines.settings', array_filter(['class_id' => $timeSlot->class_id]))->with('success', 'Time slot updated.');
    }

    public function move(Request $request, RoutineTimeSlot $timeSlot): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $ordered = RoutineTimeSlot::query()
            ->where('class_id', $timeSlot->class_id)
            ->ordered()
            ->get()
            ->values();

        $index = $ordered->search(fn (RoutineTimeSlot $slot) => $slot->id === $timeSlot->id);

        if ($index === false) {
            return back();
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $ordered->count()) {
            return back()->with('success', $direction === 'up'
                ? 'This time slot is already first.'
                : 'This time slot is already last.');
        }

        $items = $ordered->all();
        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

        DB::transaction(function () use ($items) {
            foreach ($items as $position => $slot) {
                $slot->update(['sort_order' => $position + 1]);
            }
        });

        $this->audit->record(
            'routine_time_slot_moved',
            'Academics',
            "Moved timetable period {$timeSlot->name} {$direction}",
            $timeSlot,
            null,
            ['direction' => $direction]
        );

        return redirect()->route('routines.settings', array_filter(['class_id' => $timeSlot->class_id]))->with('success', 'Time slot order updated.');
    }

    public function destroy(RoutineTimeSlot $timeSlot): RedirectResponse
    {
        if ($timeSlot->entries()->exists()) {
            throw ValidationException::withMessages([
                'slot' => 'This time slot is used by one or more timetable cells. Clear those cells before deleting it.',
            ]);
        }

        $before = $timeSlot->only(['class_id', 'name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active']);
        $timeSlot->delete();

        $this->audit->record(
            'routine_time_slot_deleted',
            'Academics',
            "Deleted timetable period {$before['name']}",
            null,
            $before,
            null
        );

        return redirect()->route('routines.settings', array_filter(['class_id' => $timeSlot->class_id]))->with('success', 'Time slot deleted.');
    }

    public function customizeClass(SchoolClass $schoolClass): RedirectResponse
    {
        if ($this->schedule->hasCustomSlots($schoolClass->id)) {
            return redirect()->route('routines.settings', ['class_id' => $schoolClass->id])
                ->with('warning', 'This class already has its own time slots.');
        }

        $defaults = RoutineTimeSlot::query()->whereNull('class_id')->ordered()->get();

        DB::transaction(function () use ($defaults, $schoolClass) {
            $slotMap = [];

            foreach ($defaults as $slot) {
                $copy = RoutineTimeSlot::create([
                    'class_id' => $schoolClass->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'type' => $slot->type,
                    'sort_order' => $slot->sort_order,
                    'is_active' => $slot->is_active,
                ]);
                $slotMap[$slot->id] = $copy->id;
            }

            if ($slotMap !== []) {
                DB::table('routine_entries')
                    ->join('class_routines', 'class_routines.id', '=', 'routine_entries.class_routine_id')
                    ->where('class_routines.class_id', $schoolClass->id)
                    ->whereIn('routine_entries.routine_time_slot_id', array_keys($slotMap))
                    ->select(['routine_entries.id', 'routine_entries.routine_time_slot_id'])
                    ->get()
                    ->each(function ($entry) use ($slotMap) {
                        DB::table('routine_entries')->where('id', $entry->id)
                            ->update(['routine_time_slot_id' => $slotMap[$entry->routine_time_slot_id]]);
                    });
            }
        });

        $this->audit->record(
            'routine_class_slots_customized',
            'Academics',
            "Created class-specific time slots for {$schoolClass->name}",
            $schoolClass,
            null,
            ['copied_periods' => $defaults->count()]
        );

        return redirect()->route('routines.settings', ['class_id' => $schoolClass->id])
            ->with('success', 'Shared periods copied. You can now change times for '.$schoolClass->name.' only.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'type' => ['required', Rule::in(array_keys(RoutineTimeSlot::TYPES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['type'] = (string) $data['type'];
        $data['class_id'] = filled($data['class_id'] ?? null) ? (int) $data['class_id'] : null;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = filled($data['sort_order'] ?? null) ? (int) $data['sort_order'] : null;

        return $data;
    }
}
