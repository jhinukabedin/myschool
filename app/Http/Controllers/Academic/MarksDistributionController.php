<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\ClassSubjectMarkOverride;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectMarkDefault;
use App\Services\AuditLogService;
use App\Services\MarksDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarksDistributionController extends Controller
{
    public function __construct(
        private readonly MarksDistributionService $distributions,
        private readonly AuditLogService $audit
    ) {}

    public function index(Request $request): View
    {
        $scope = $request->query('scope') === 'class' ? 'class' : 'global';
        $editing = $scope === 'class'
            ? ClassSubjectMarkOverride::with(['class', 'subject'])->find($request->integer('id'))
            : SubjectMarkDefault::with('subject')->find($request->integer('id'));

        $classSubjectMap = ClassSubject::query()->select('class_id', 'subject_id')->distinct()->get()
            ->groupBy('subject_id')->map(fn ($rows) => $rows->pluck('class_id')->map(fn ($id) => (int) $id)->values());

        return view('academics.exams.distributions', [
            'scope' => $scope,
            'editing' => $editing,
            'classes' => SchoolClass::ordered()->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'classSubjectMap' => $classSubjectMap,
            'globalDefaults' => SubjectMarkDefault::with('subject')->orderBy('subject_id')->get(),
            'classOverrides' => ClassSubjectMarkOverride::with(['class', 'subject'])->orderBy('class_id')->orderBy('subject_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:global,class'],
            'class_id' => ['nullable', 'required_if:scope,class', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'full_marks' => ['required', 'numeric', 'gt:0', 'max:999'],
            'pass_marks' => ['required', 'numeric', 'min:0', 'lte:full_marks'],
            'components' => ['nullable', 'array'],
            'components.*.name' => ['nullable', 'string', 'max:80'],
            'components.*.full_marks' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'components.*.pass_marks' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'components.*.requires_pass' => ['nullable', 'boolean'],
        ]);

        if ($data['scope'] === 'class' && ! ClassSubject::query()
            ->where('class_id', $data['class_id'])->where('subject_id', $data['subject_id'])->exists()) {
            return back()->withInput()->withErrors(['subject_id' => 'Choose a subject assigned to the selected class.']);
        }

        $config = $this->distributions->normalize($data);
        $identity = $data['scope'] === 'class'
            ? ['class_id' => $data['class_id'], 'subject_id' => $data['subject_id']]
            : ['subject_id' => $data['subject_id']];
        $modelClass = $data['scope'] === 'class' ? ClassSubjectMarkOverride::class : SubjectMarkDefault::class;
        $before = $modelClass::query()->where($identity)->first()?->getAttributes();
        $model = DB::transaction(function () use ($data, $config) {
            if ($data['scope'] === 'class') {
                return ClassSubjectMarkOverride::updateOrCreate(
                    ['class_id' => $data['class_id'], 'subject_id' => $data['subject_id']],
                    $config
                );
            }

            return SubjectMarkDefault::updateOrCreate(['subject_id' => $data['subject_id']], $config);
        });

        $this->audit->record(
            'marks_distribution_saved',
            'Examinations',
            'Saved '.($data['scope'] === 'class' ? 'class override' : 'global default').' marks distribution for '.$model->subject?->name,
            $model,
            $before,
            ['scope' => $data['scope'], 'class_id' => $data['class_id'] ?? null, ...$model->fresh()->getAttributes()]
        );

        return redirect()->route('exams.distributions', ['scope' => $data['scope']])->with('success', 'Marks distribution saved. Existing examinations keep their stored configuration.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:global,class'],
            'id' => ['required', 'integer'],
        ]);
        $model = $data['scope'] === 'class'
            ? ClassSubjectMarkOverride::findOrFail($data['id'])
            : SubjectMarkDefault::findOrFail($data['id']);
        $oldValues = $model->getAttributes();
        $model->delete();
        $this->audit->record('marks_distribution_deleted', 'Examinations', 'Removed marks distribution for '.$model->subject?->name, $model, $oldValues, null);

        return back()->with('success', 'Configuration removed. Existing examinations retain their snapshots.');
    }
}
