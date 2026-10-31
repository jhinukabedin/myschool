<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\GradeRule;
use App\Models\GradingScheme;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GradingSchemeController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('academics.grading-schemes.index', [
            'schemes' => GradingScheme::query()
                ->with(['academicSession'])
                ->withCount(['rules', 'exams'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'exam_type' => ['nullable', 'string', 'max:60'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'copy_rules_from' => ['nullable', 'exists:grading_schemes,id'],
        ]);

        $sourceRules = $data['copy_rules_from'] ?? null
            ? GradeRule::query()->where('grading_scheme_id', $data['copy_rules_from'])->ordered()->get()
            : GradeRule::query()->whereNull('grading_scheme_id')->ordered()->get();

        if ($sourceRules->isEmpty()) {
            throw ValidationException::withMessages([
                'name' => 'No grade rules are available to copy into the new scheme. Configure the default grade rules first.',
            ]);
        }

        $scheme = DB::transaction(function () use ($request, $data, $sourceRules) {
            $isDefault = $request->boolean('is_default');

            if ($isDefault) {
                GradingScheme::query()
                    ->where('academic_session_id', $data['academic_session_id'] ?? null)
                    ->update(['is_default' => false]);
            }

            $scheme = GradingScheme::create([
                'name' => $data['name'],
                'exam_type' => $data['exam_type'] ?? null,
                'academic_session_id' => $data['academic_session_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => $request->boolean('is_active', true),
            ]);

            foreach ($sourceRules as $rule) {
                $scheme->rules()->create([
                    'name' => $rule->name,
                    'min_percent' => $rule->min_percent,
                    'max_percent' => $rule->max_percent,
                    'grade' => $rule->grade,
                    'grade_point' => $rule->grade_point,
                    'is_fail' => $rule->is_fail,
                ]);
            }

            $this->audit->record(
                'grading_scheme_created', 'Academics',
                "Grading scheme {$scheme->name} created with {$sourceRules->count()} copied rules",
                $scheme, null, $scheme->getAttributes()
            );

            return $scheme;
        });

        return redirect()->route('grading-schemes.edit', $scheme)->with('success', 'Grading scheme created. Review the boundaries before assigning it to examinations.');
    }

    public function edit(GradingScheme $scheme): View
    {
        return view('academics.grading-schemes.edit', [
            'scheme' => $scheme->load(['rules', 'academicSession', 'exams']),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'schemes' => GradingScheme::query()->whereKeyNot($scheme->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, GradingScheme $scheme): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'exam_type' => ['nullable', 'string', 'max:60'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.name' => ['required', 'string', 'max:40'],
            'rules.*.min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'rules.*.max_percent' => ['required', 'numeric', 'min:0', 'max:100', 'gte:rules.*.min_percent'],
            'rules.*.grade' => ['required', 'string', 'max:10'],
            'rules.*.grade_point' => ['required', 'numeric', 'min:0', 'max:10'],
            'rules.*.is_fail' => ['nullable', 'boolean'],
        ]);

        $this->validateBoundaries($data['rules']);

        DB::transaction(function () use ($request, $scheme, $data) {
            $before = $scheme->getAttributes();
            $isDefault = $request->boolean('is_default');

            if ($isDefault) {
                GradingScheme::query()
                    ->whereKeyNot($scheme->id)
                    ->where('academic_session_id', $data['academic_session_id'] ?? null)
                    ->update(['is_default' => false]);
            }

            $scheme->update([
                'name' => $data['name'],
                'exam_type' => $data['exam_type'] ?? null,
                'academic_session_id' => $data['academic_session_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => $request->boolean('is_active'),
            ]);

            $scheme->rules()->delete();
            foreach ($data['rules'] as $rule) {
                $scheme->rules()->create([
                    'name' => $rule['name'],
                    'min_percent' => $rule['min_percent'],
                    'max_percent' => $rule['max_percent'],
                    'grade' => $rule['grade'],
                    'grade_point' => $rule['grade_point'],
                    'is_fail' => (bool) ($rule['is_fail'] ?? false),
                ]);
            }

            $this->audit->record(
                'grading_scheme_updated', 'Academics',
                "Grading scheme {$scheme->name} updated; existing result snapshots keep the old rules",
                $scheme, $before, $scheme->fresh()->getAttributes()
            );
        });

        return back()->with('success', 'Grading scheme updated. Existing published results keep their stored grading snapshot.');
    }

    public function destroy(GradingScheme $scheme): RedirectResponse
    {
        if ($scheme->exams()->exists()) {
            return back()->with('error', 'This scheme is assigned to one or more examinations. Change those examinations first.');
        }

        if ($scheme->is_default) {
            return back()->with('error', 'A default scheme cannot be deleted. Mark another scheme as default first.');
        }

        $payload = $scheme->only(['name', 'exam_type', 'academic_session_id']);
        $scheme->delete();
        $this->audit->record('grading_scheme_deleted', 'Academics', "Grading scheme {$payload['name']} deleted", null, $payload, null);

        return redirect()->route('grading-schemes.index')->with('success', 'Grading scheme deleted.');
    }

    /**
     * Reject overlaps and gaps in the 0-100 boundary range.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function validateBoundaries(array $rules): void
    {
        $sorted = collect($rules)->sortBy(fn ($rule) => (float) $rule['min_percent'])->values();
        $previousMax = null;

        foreach ($sorted as $index => $rule) {
            $min = round((float) $rule['min_percent'], 2);
            $max = round((float) $rule['max_percent'], 2);

            if ($index === 0 && abs($min) > 0.001) {
                throw ValidationException::withMessages(['rules' => 'The first grade boundary must start at 0%.']);
            }

            if ($previousMax !== null && abs($min - ($previousMax + 0.01)) > 0.011) {
                throw ValidationException::withMessages([
                    'rules' => 'Grade boundaries must be contiguous and cannot overlap or leave gaps (expected the next minimum to be '.number_format($previousMax + 0.01, 2).'%).',
                ]);
            }

            $previousMax = $max;
        }

        if ($previousMax === null || abs($previousMax - 100) > 0.001) {
            throw ValidationException::withMessages(['rules' => 'The last grade boundary must end at 100%.']);
        }
    }
}
