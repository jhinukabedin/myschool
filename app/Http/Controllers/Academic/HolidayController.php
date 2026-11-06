<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\SchoolSetting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $year = (int) ($request->input('year') ?: now()->year);

        $holidays = Holiday::query()
            ->when($year, fn ($query) => $query->whereYear('start_date', $year)->orWhereYear('end_date', $year))
            ->orderBy('start_date')
            ->paginate(25)
            ->withQueryString();

        $upcoming = Holiday::query()
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        return view('academics.holidays.index', [
            'holidays' => $holidays,
            'upcoming' => $upcoming,
            'year' => $year,
            'years' => Holiday::query()->selectRaw('YEAR(start_date) as year')->distinct()->orderByDesc('year')->pluck('year'),
            'types' => Holiday::TYPES,
            'weekdays' => Holiday::DAYS,
            'weeklyOff' => Holiday::weeklyOffDays(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $holiday = Holiday::create($data);

        $this->audit->record('holiday_created', 'Academics', "Holiday {$holiday->name} added ({$holiday->start_date->toDateString()} → {$holiday->end_date->toDateString()})", $holiday, null, $data);

        return back()->with('success', 'Holiday added to the calendar.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $data = $this->validated($request, $holiday);
        $before = $holiday->getAttributes();
        $holiday->update($data);

        $this->audit->record('holiday_updated', 'Academics', "Holiday {$holiday->name} updated", $holiday, $before, $data);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $before = $holiday->only(['name', 'start_date', 'end_date']);
        $holiday->delete();

        $this->audit->record('holiday_deleted', 'Academics', "Holiday {$before['name']} removed from the calendar", null, $before, null);

        return back()->with('success', 'Holiday removed.');
    }

    public function weekdays(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['string', 'in:'.implode(',', array_keys(Holiday::DAYS))],
        ]);

        SchoolSetting::put('holiday_weekdays', json_encode(array_values($data['weekdays'] ?? [])), 'academics');

        $this->audit->record(
            'holiday_weekdays_updated',
            'Academics',
            'Weekly off days set to '.implode(', ', $data['weekdays'] ?? []),
            null,
            null,
            ['weekdays' => $data['weekdays'] ?? []]
        );

        return back()->with('success', 'Weekly off days saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:'.implode(',', array_keys(Holiday::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'applies_to' => ['required', 'in:all,students,staff'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
