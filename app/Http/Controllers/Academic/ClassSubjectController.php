<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassSubjectController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $session = current_session();
        $class = $request->filled('class_id') ? SchoolClass::find($request->integer('class_id')) : null;

        $mappings = ClassSubject::query()
            ->with(['class', 'subject', 'academicSession'])
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->when($class, fn ($query) => $query->where('class_id', $class->id))
            ->orderBy('class_id')
            ->orderBy('subject_id')
            ->paginate(30)
            ->withQueryString();

        return view('academics.class-subjects', [
            'mappings' => $mappings,
            'session' => $session,
            'class' => $class,
            'classes' => SchoolClass::ordered()->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $exists = ClassSubject::query()
            ->where('academic_session_id', $data['academic_session_id'])
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['subject_id' => 'This subject is already mapped to the class for the session.']);
        }

        $mapping = ClassSubject::create($data);
        $this->audit->record('class_subject_mapped', 'Academics', 'Subject mapped to class', $mapping, null, $data);

        return back()->with('success', 'Subject mapped to class.');
    }

    public function destroy(ClassSubject $classSubject): RedirectResponse
    {
        $data = $classSubject->getAttributes();
        $classSubject->delete();
        $this->audit->record('class_subject_removed', 'Academics', 'Subject mapping removed', null, $data, null);

        return back()->with('success', 'Subject mapping removed. Existing exam records are unaffected.');
    }
}
