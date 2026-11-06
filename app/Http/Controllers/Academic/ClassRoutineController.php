<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\RoutineEntry;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Services\AuditLogService;
use App\Services\RoutineScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClassRoutineController extends Controller
{
    public function __construct(
        private readonly RoutineScheduleService $schedule,
        private readonly AuditLogService $audit
    ) {}

    public function index(Request $request): View
    {
        $sessionId = $request->filled('session_id')
            ? $request->integer('session_id')
            : current_session()?->id;

        $routines = ClassRoutine::query()
            ->with(['academicSession', 'class', 'section'])
            ->withCount('entries')
            ->when($sessionId, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->integer('section_id')))
            ->orderByDesc('academic_session_id')
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->paginate(20)
            ->withQueryString();

        return view('academics.routines.index', [
            'routines' => $routines,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::with('sections')->ordered()->get(),
            'sections' => Section::with('classes')->ordered()->get(),
            'selectedSessionId' => $sessionId,
            'hasTimeSlots' => $this->schedule->hasAnyActiveSlots(),
        ]);
    }

    public function create(): View
    {
        $session = current_session();

        return view('academics.routines.form', [
            'routine' => new ClassRoutine([
                'academic_session_id' => $session?->id,
                'effective_from' => ($session?->start_date ?? today())->format('Y-m-d'),
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRoutine($request);
        $this->assertClassSection($data['class_id'], $data['section_id']);
        $this->assertUniqueContext($data);

        $routine = ClassRoutine::create($data + [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->audit->record(
            'class_routine_created',
            'Academics',
            "Created class routine for {$routine->class?->name} Section {$routine->section?->name}",
            $routine,
            null,
            $routine->only(['academic_session_id', 'class_id', 'section_id', 'effective_from', 'effective_to'])
        );

        return redirect()->route('routines.show', $routine)
            ->with('success', 'Class routine created. Click any timetable cell to assign a subject and teacher.');
    }

    public function show(ClassRoutine $routine): View
    {
        return view('academics.routines.show', $this->timetableData($routine));
    }

    public function edit(ClassRoutine $routine): View
    {
        return view('academics.routines.form', [
            'routine' => $routine,
            ...$this->formData(),
        ]);
    }

    public function update(Request $request, ClassRoutine $routine): RedirectResponse
    {
        $data = $this->validatedRoutine($request);
        $this->assertClassSection($data['class_id'], $data['section_id']);
        $this->assertUniqueContext($data, $routine->id);

        $before = $routine->only(['academic_session_id', 'class_id', 'section_id', 'effective_from', 'effective_to', 'notes']);
        $data['updated_by'] = $request->user()?->id;
        $routine->update($data);

        $this->audit->record(
            'class_routine_updated',
            'Academics',
            "Updated class routine #{$routine->id}",
            $routine,
            $before,
            $routine->only(['academic_session_id', 'class_id', 'section_id', 'effective_from', 'effective_to', 'notes'])
        );

        return redirect()->route('routines.show', $routine)->with('success', 'Class routine details updated.');
    }

    public function copyForm(ClassRoutine $routine): View
    {
        $session = current_session();

        return view('academics.routines.copy', [
            'routine' => $routine->load(['academicSession', 'class', 'section']),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::with('sections')->ordered()->get(),
            'sections' => Section::with('classes')->ordered()->get(),
            'targetSessionId' => $session?->id,
            'targetEffectiveFrom' => ($session?->start_date ?? today())->format('Y-m-d'),
        ]);
    }

    public function copy(Request $request, ClassRoutine $routine): RedirectResponse
    {
        $data = $this->validatedRoutine($request);
        $this->assertClassSection($data['class_id'], $data['section_id']);

        if ((int) $routine->academic_session_id === (int) $data['academic_session_id']
            && (int) $routine->class_id === (int) $data['class_id']
            && (int) $routine->section_id === (int) $data['section_id']) {
            throw ValidationException::withMessages([
                'class_id' => 'Choose a different academic year, class or section to copy this routine into.',
            ]);
        }

        $this->assertUniqueContext($data);

        $routine->load(['entries.timeSlot']);

        $slotMap = [];
        foreach ($routine->entries as $entry) {
            $sourceSlot = $entry->timeSlot;
            $targetSlot = $sourceSlot ? $this->schedule->resolveSlotForClass($data['class_id'], $sourceSlot) : null;
            if (! $targetSlot) {
                throw ValidationException::withMessages([
                    'class_id' => 'The target class uses different periods. Configure matching periods before copying this routine.',
                ]);
            }
            $slotMap[$entry->routine_time_slot_id] = $targetSlot->id;
        }

        $copy = DB::transaction(function () use ($routine, $data, $request, $slotMap) {
            $copy = ClassRoutine::create([
                ...$data,
                'notes' => $routine->notes,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            foreach ($routine->entries as $entry) {
                $this->schedule->saveEntry($copy, [
                    'day' => $entry->day,
                    'routine_time_slot_id' => $slotMap[$entry->routine_time_slot_id],
                    'subject_id' => $entry->subject_id,
                    'staff_id' => $entry->staff_id,
                    'classroom' => $entry->classroom,
                    'notes' => $entry->notes,
                ]);
            }

            return $copy;
        });

        $this->audit->record(
            'class_routine_copied',
            'Academics',
            "Copied class routine #{$routine->id} to {$copy->class?->name} Section {$copy->section?->name}",
            $copy,
            $routine->only(['academic_session_id', 'class_id', 'section_id']),
            $copy->only(['academic_session_id', 'class_id', 'section_id'])
        );

        return redirect()->route('routines.show', $copy)
            ->with('success', 'Routine copied. Review the new timetable before editing it.');
    }

    public function destroy(Request $request, ClassRoutine $routine): RedirectResponse
    {
        $before = $routine->only(['academic_session_id', 'class_id', 'section_id', 'effective_from']);
        $label = $routine->class?->name.' Section '.$routine->section?->name;

        DB::transaction(fn () => $routine->delete());

        $this->audit->record(
            'class_routine_deleted',
            'Academics',
            "Deleted class routine for {$label}",
            null,
            $before,
            null
        );

        return redirect()->route('routines.index')
            ->with('success', 'Class routine deleted. All timetable cells were removed with it.');
    }

    public function saveEntry(Request $request, ClassRoutine $routine): RedirectResponse
    {
        $data = $request->validate([
            'day' => ['required', 'string', 'max:16'],
            'routine_time_slot_id' => ['required', 'integer', 'exists:routine_time_slots,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where('status', 'active')],
            'classroom' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $entry = $routine->entries()
            ->where('day', $data['day'])
            ->where('routine_time_slot_id', (int) $data['routine_time_slot_id'])
            ->first();

        $saved = $this->schedule->saveEntry($routine, $data, $entry);

        $this->audit->record(
            $entry ? 'routine_cell_updated' : 'routine_cell_created',
            'Academics',
            ($entry ? 'Updated' : 'Created')." timetable cell for {$routine->class?->name} Section {$routine->section?->name} on {$this->schedule->dayLabel($saved->day)}",
            $saved,
            $entry ? $entry->only(['subject_id', 'staff_id', 'classroom', 'notes']) : null,
            $saved->only(['day', 'routine_time_slot_id', 'subject_id', 'staff_id', 'classroom', 'notes'])
        );

        return $this->backToRoutine($routine, $saved, $entry ? 'Timetable cell updated.' : 'Timetable cell assigned.');
    }

    public function clearEntry(Request $request, ClassRoutine $routine): RedirectResponse
    {
        $data = $request->validate([
            'day' => ['required', 'string', 'max:16'],
            'routine_time_slot_id' => ['required', 'integer', 'exists:routine_time_slots,id'],
        ]);

        $entry = RoutineEntry::query()
            ->where('class_routine_id', $routine->id)
            ->where('day', $data['day'])
            ->where('routine_time_slot_id', (int) $data['routine_time_slot_id'])
            ->first();

        if (! $entry) {
            return $this->backToRoutine($routine, null, 'That timetable cell is already empty.');
        }

        $before = $entry->only(['day', 'routine_time_slot_id', 'subject_id', 'staff_id', 'classroom', 'notes']);
        $entry->delete();

        $this->audit->record(
            'routine_cell_cleared',
            'Academics',
            "Cleared timetable cell for {$routine->class?->name} Section {$routine->section?->name}",
            null,
            $before,
            null
        );

        return $this->backToRoutine($routine, null, 'Timetable cell cleared. The rest of the routine is unchanged.');
    }

    public function print(ClassRoutine $routine, Request $request): View
    {
        return view('academics.routines.print', [
            ...$this->timetableData($routine),
            ...$this->routineBrandData(),
            'routineFontRegular' => asset('fonts/NotoSansBengali-Regular.ttf'),
            'routineFontBold' => asset('fonts/NotoSansBengali-Bold.ttf'),
            'pdfDownloadUrl' => route('routines.pdf', $routine),
            'backUrl' => route('routines.show', $routine),
            'isEmbedded' => $request->boolean('embed'),
        ]);
    }

    public function pdf(ClassRoutine $routine): Response
    {
        return $this->downloadPdf($routine);
    }

    public function publicPdf(ClassRoutine $routine): Response
    {
        abort_unless($routine->is_published, 404);

        return $this->downloadPdf($routine, 'bn');
    }

    public function publicShow(ClassRoutine $routine, Request $request): View
    {
        abort_unless($routine->is_published, 404);

        return view('academics.routines.print', [
            ...$this->timetableData($routine),
            ...$this->routineBrandData(),
            'routineFontRegular' => asset('fonts/NotoSansBengali-Regular.ttf'),
            'routineFontBold' => asset('fonts/NotoSansBengali-Bold.ttf'),
            'pdfDownloadUrl' => route('website.class-routine.pdf', $routine),
            'backUrl' => route('website.class-routine'),
            'isEmbedded' => $request->boolean('embed'),
            // The public website is Bangla-first, so its printable routine stays
            // Bangla even when the visitor's interface locale is English.
            'documentLocale' => 'bn',
        ]);
    }

    public function toggleWebsiteVisibility(Request $request, ClassRoutine $routine): RedirectResponse
    {
        $publish = ! $routine->is_published;
        if ($publish && ! $routine->entries()->exists()) {
            return back()->with('error', 'Add timetable subjects before publishing this routine.');
        }

        $before = $routine->is_published;
        $routine->update(['is_published' => $publish, 'updated_by' => $request->user()?->id]);
        $this->audit->record(
            $publish ? 'class_routine_published' : 'class_routine_unpublished',
            'Academics',
            ($publish ? 'Published' : 'Unpublished')." {$routine->class?->name} Section {$routine->section?->name} routine on website",
            $routine,
            ['is_published' => $before],
            ['is_published' => $publish]
        );

        return back()->with('success', $publish ? 'Routine is now visible on the website.' : 'Routine has been hidden from the website.');
    }

    private function downloadPdf(ClassRoutine $routine, ?string $documentLocale = null): Response
    {
        $fontDir = storage_path('fonts');
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0775, true);
        }
        foreach (['NotoSansBengali-Regular.ttf', 'NotoSansBengali-Bold.ttf'] as $font) {
            if (! is_file($fontDir.DIRECTORY_SEPARATOR.$font)) {
                copy(public_path('fonts/'.$font), $fontDir.DIRECTORY_SEPARATOR.$font);
            }
        }

        $filename = str($routine->class?->name.'-'.$routine->section?->name.'-routine')->slug().'.pdf';

        $pdf = Pdf::loadView('academics.routines.pdf', [
            ...$this->timetableData($routine),
            ...$this->routineBrandData(embedLogo: true),
            'documentLocale' => $documentLocale,
        ])->setPaper('a4', 'landscape');

        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        foreach ([
            ['weight' => 'normal', 'path' => $fontDir.'/NotoSansBengali-Regular.ttf'],
            ['weight' => 'bold', 'path' => $fontDir.'/NotoSansBengali-Bold.ttf'],
        ] as $font) {
            if (! $fontMetrics->registerFont(
                ['family' => 'RoutineBengali', 'weight' => $font['weight'], 'style' => 'normal'],
                str_replace('\\', '/', $font['path'])
            )) {
                throw new \RuntimeException('Could not register the Bengali routine font.');
            }
        }

        return $pdf->download($filename);
    }

    /** @return array{routineLogo: ?string, routineLogoMark: string, routineEstablishedYear: mixed} */
    private function routineBrandData(bool $embedLogo = false): array
    {
        $path = (string) school_setting('website_logo_path', '');
        $logo = null;

        if ($path !== '') {
            $disk = Storage::disk('public');
            if ($embedLogo && $disk->exists($path)) {
                $mime = $disk->mimeType($path);
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
                    $logo = 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
                }
            } elseif (! $embedLogo) {
                $logo = asset('storage/'.ltrim($path, '/'));
            }
        }

        return [
            'routineLogo' => $logo,
            'routineLogoMark' => (string) school_setting('website_logo_mark', 'HS'),
            'routineEstablishedYear' => school_setting('school_established_year'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::with('sections')->ordered()->get(),
            'sections' => Section::with('classes')->ordered()->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timetableData(ClassRoutine $routine): array
    {
        $routine->load([
            'academicSession',
            'class',
            'section',
            'entries.subject',
            'entries.teacher',
            'entries.timeSlot',
        ]);

        $entryMap = $this->schedule->entryMap($routine);

        $cellData = $entryMap->mapWithKeys(fn (RoutineEntry $entry, string $key) => [
            $key => [
                'id' => $entry->id,
                'subject_id' => $entry->subject_id,
                'staff_id' => $entry->staff_id,
                'classroom' => $entry->classroom,
                'notes' => $entry->notes,
                'subject' => $entry->subject?->name,
                'teacher' => $entry->teacher?->full_name,
            ],
        ]);

        return [
            'routine' => $routine,
            'days' => $this->schedule->workingDays(),
            'slots' => $this->schedule->activeSlots((int) $routine->class_id),
            'entryMap' => $entryMap,
            'cellData' => $cellData,
            'subjects' => Subject::query()->orderBy('name')->get(),
            'teachers' => Staff::query()->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRoutine(Request $request): array
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['academic_session_id'] = (int) $data['academic_session_id'];
        $data['class_id'] = (int) $data['class_id'];
        $data['section_id'] = (int) $data['section_id'];
        $data['notes'] = filled($data['notes'] ?? null) ? trim($data['notes']) : null;

        return $data;
    }

    private function assertClassSection(int $classId, int $sectionId): void
    {
        $exists = DB::table('class_section')
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'section_id' => 'The selected section is not available for this class.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertUniqueContext(array $data, ?int $ignoreId = null): void
    {
        $exists = ClassRoutine::query()
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('class_id', $data['class_id'])
            ->where('section_id', $data['section_id'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'class_id' => 'A routine already exists for this academic year, class and section. Open it to edit instead of creating a duplicate.',
            ]);
        }
    }

    private function backToRoutine(ClassRoutine $routine, ?RoutineEntry $entry, string $message): RedirectResponse
    {
        $fragment = $entry ? '#cell-'.$entry->day.'-'.$entry->routine_time_slot_id : '';

        return redirect()->to(route('routines.show', $routine).$fragment)->with('success', $message);
    }
}
