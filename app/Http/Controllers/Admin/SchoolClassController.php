<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.classes', [
            'classes' => SchoolClass::withCount(['enrollments', 'feeStructures'])->ordered()->get(),
            'sections' => Section::withCount('enrollments')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:classes,name'],
            'numeric_level' => ['required', 'integer', 'min:0', 'max:99'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['integer', 'exists:sections,id'],
        ]);

        $class = SchoolClass::create(['name' => $data['name'], 'numeric_level' => $data['numeric_level']]);
        $class->sections()->sync($data['sections'] ?? []);
        $this->audit->record('class_created', 'Administration', "Class {$class->name} created", $class, null, $data);

        return back()->with('success', 'Class created.');
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:classes,name,'.$schoolClass->id],
            'numeric_level' => ['required', 'integer', 'min:0', 'max:99'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['integer', 'exists:sections,id'],
        ]);

        $before = $schoolClass->getAttributes();
        $schoolClass->update(['name' => $data['name'], 'numeric_level' => $data['numeric_level']]);
        $schoolClass->sections()->sync($data['sections'] ?? []);
        $this->audit->record('class_updated', 'Administration', "Class {$schoolClass->name} updated", $schoolClass, $before, $data);

        return back()->with('success', 'Class updated.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        if ($schoolClass->enrollments()->exists() || $schoolClass->feeStructures()->exists()) {
            return back()->with('error', 'This class has students or fee structures and cannot be deleted.');
        }

        $old = $schoolClass->getAttributes();
        $schoolClass->delete();
        $this->audit->record('class_deleted', 'Administration', "Class {$schoolClass->name} deleted", null, $old, null);

        return back()->with('success', 'Class deleted.');
    }
}
