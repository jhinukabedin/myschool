<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $subjects = Subject::query()
            ->withCount(['classSubjects', 'examinationSubjects'])
            ->when($request->filled('q'), fn ($query) => $query->where(function ($q) use ($request) {
                $term = $request->string('q');
                $q->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('academics.subjects.index', ['subjects' => $subjects]);
    }

    public function create(): View
    {
        return view('academics.subjects.form', [
            'subject' => new Subject(['type' => 'theory', 'full_marks' => 100, 'pass_marks' => 33]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $subject = Subject::create($data);

        $this->audit->record('subject_created', 'Academics', "Subject {$subject->name} created", $subject, null, $data);

        return redirect()->route('subjects.index')->with('success', 'Subject created.');
    }

    public function edit(Subject $subject): View
    {
        return view('academics.subjects.form', ['subject' => $subject]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate($this->rules($subject));
        $before = $subject->getAttributes();
        $subject->update($data);

        $this->audit->record('subject_updated', 'Academics', "Subject {$subject->name} updated", $subject, $before, $data);

        return redirect()->route('subjects.index')->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->examinationSubjects()->exists()) {
            return back()->with('error', 'This subject is used in an examination and cannot be deleted. Edit it instead.');
        }

        $before = $subject->getAttributes();
        $subject->delete();

        $this->audit->record('subject_deleted', 'Academics', "Subject {$subject->name} deleted", $subject, $before, null);

        return back()->with('success', 'Subject removed.');
    }

    private function rules(?Subject $subject = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($subject)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:theory,practical,both'],
            'full_marks' => ['required', 'numeric', 'min:1', 'max:999'],
            'pass_marks' => ['required', 'numeric', 'min:0', 'lte:full_marks'],
        ];
    }
}
