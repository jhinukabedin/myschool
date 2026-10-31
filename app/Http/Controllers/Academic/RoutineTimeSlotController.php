<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\RoutineTimeSlot;
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

    public function index(): View
    {
        $slots = $this->schedule->allSlots()->loadCount('entries');

        return view('academics.routines.settings', [
            'slots' => $slots,
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

        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be later than the start time.',
            ]);
        }

        $this->schedule->assertSlotDoesNotOverlap($data);

        if (! isset($data['sort_order']) || $data['sort_order'] === null) {
            $data['sort_order'] = ((int) RoutineTimeSlot::query()->max('sort_order')) + 1;
        }

        $slot = RoutineTimeSlot::create($data);

        $this->audit->record(
            'routine_time_slot_created',
            'Academics',
            "Created timetable period {$slot->name} ({$slot->time_range})",
            $slot,
            null,
            $slot->only(['name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active'])
        );

        return redirect()->route('routines.settings')->with('success', 'Time slot added.');
    }

    public function update(Request $request, RoutineTimeSlot $timeSlot): RedirectResponse
    {
        $data = $this->validated($request);

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

        $before = $timeSlot->only(['name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active']);
        $data['sort_order'] = $data['sort_order'] ?? $timeSlot->sort_order;
        $timeSlot->update($data);

        $this->audit->record(
            'routine_time_slot_updated',
            'Academics',
            "Updated timetable period {$timeSlot->name}",
            $timeSlot,
            $before,
            $timeSlot->only(['name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active'])
        );

        return redirect()->route('routines.settings')->with('success', 'Time slot updated.');
    }

    public function move(Request $request, RoutineTimeSlot $timeSlot): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $ordered = RoutineTimeSlot::query()
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

        return redirect()->route('routines.settings')->with('success', 'Time slot order updated.');
    }

    public function destroy(RoutineTimeSlot $timeSlot): RedirectResponse
    {
        if ($timeSlot->entries()->exists()) {
            throw ValidationException::withMessages([
                'slot' => 'This time slot is used by one or more timetable cells. Clear those cells before deleting it.',
            ]);
        }

        $before = $timeSlot->only(['name', 'start_time', 'end_time', 'type', 'sort_order', 'is_active']);
        $timeSlot->delete();

        $this->audit->record(
            'routine_time_slot_deleted',
            'Academics',
            "Deleted timetable period {$before['name']}",
            null,
            $before,
            null
        );

        return redirect()->route('routines.settings')->with('success', 'Time slot deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'type' => ['required', Rule::in(array_keys(RoutineTimeSlot::TYPES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['type'] = (string) $data['type'];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = filled($data['sort_order'] ?? null) ? (int) $data['sort_order'] : null;

        return $data;
    }
}
