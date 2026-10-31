<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicSessionController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.sessions', [
            'sessions' => AcademicSession::withCount(['enrollments', 'payments', 'examinations'])->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $session = AcademicSession::create($data);

        if ($session->is_current) {
            $this->setCurrent($session);
        }

        $this->audit->record('session_created', 'Administration', "Academic session {$session->name} created", $session, null, $data);

        return back()->with('success', 'Academic session created.');
    }

    public function update(Request $request, AcademicSession $session): RedirectResponse
    {
        $data = $this->validated($request, $session);
        $before = $session->getAttributes();
        $session->update($data);

        if ($session->is_current) {
            $this->setCurrent($session);
        }

        $this->audit->record('session_updated', 'Administration', "Academic session {$session->name} updated", $session, $before, $data);

        return back()->with('success', 'Academic session updated.');
    }

    public function activate(AcademicSession $session): RedirectResponse
    {
        $this->setCurrent($session);
        $this->audit->record('session_activated', 'Administration', "Academic session {$session->name} set as current", $session, null, ['is_current' => true]);

        return back()->with('success', "{$session->name} is now the current academic session.");
    }

    public function destroy(AcademicSession $session): RedirectResponse
    {
        if ($session->enrollments()->exists() || $session->payments()->exists() || $session->examinations()->exists()) {
            return back()->with('error', 'This session has academic or financial records and cannot be deleted. Mark it closed instead.');
        }

        if ($session->is_current) {
            return back()->with('error', 'Switch the current session before deleting it.');
        }

        $old = $session->getAttributes();
        $session->delete();
        $this->audit->record('session_deleted', 'Administration', "Academic session {$session->name} deleted", null, $old, null);

        return back()->with('success', 'Academic session deleted.');
    }

    private function validated(Request $request, ?AcademicSession $session = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('academic_sessions', 'name')->ignore($session)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:active,closed'],
            'is_current' => ['nullable', 'boolean'],
        ]) + ['is_current' => false];
    }

    private function setCurrent(AcademicSession $session): void
    {
        DB::transaction(function () use ($session) {
            AcademicSession::query()->whereKeyNot($session->id)->update(['is_current' => false]);
            $session->update(['is_current' => true, 'status' => 'active']);
            SchoolSetting::put('current_session_id', (string) $session->id, 'academic');
        });
    }
}
