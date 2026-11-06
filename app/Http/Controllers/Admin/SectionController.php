<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.sections', [
            'sections' => Section::withCount(['enrollments', 'classes'])->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'unique:sections,name'],
            'numeric_level' => ['required', 'integer', 'min:0', 'max:99'],
        ]);
        $section = Section::create($data);
        $this->audit->record('section_created', 'Administration', "Section {$section->name} created", $section, null, $data);

        return back()->with('success', 'Section created.');
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'unique:sections,name,'.$section->id],
            'numeric_level' => ['required', 'integer', 'min:0', 'max:99'],
        ]);
        $before = $section->getAttributes();
        $section->update($data);
        $this->audit->record('section_updated', 'Administration', "Section {$section->name} updated", $section, $before, $data);

        return back()->with('success', 'Section updated.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        if ($section->enrollments()->exists()) {
            return back()->with('error', 'This section has student enrolments and cannot be deleted.');
        }

        $old = $section->getAttributes();
        $section->delete();
        $this->audit->record('section_deleted', 'Administration', "Section {$section->name} deleted", null, $old, null);

        return back()->with('success', 'Section deleted.');
    }
}
