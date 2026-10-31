<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\GradeRule;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GradeRuleController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('academics.grade-rules', [
            'rules' => GradeRule::ordered()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'rules.*.max_percent' => ['required', 'numeric', 'min:0', 'max:100', 'gte:rules.*.min_percent'],
            'rules.*.grade' => ['required', 'string', 'max:10'],
            'rules.*.grade_point' => ['required', 'numeric', 'min:0', 'max:10'],
            'rules.*.is_fail' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rules'] as $id => $rule) {
                $record = GradeRule::findOrFail($id);
                $record->update([
                    'min_percent' => $rule['min_percent'],
                    'max_percent' => $rule['max_percent'],
                    'grade' => $rule['grade'],
                    'grade_point' => $rule['grade_point'],
                    'is_fail' => (bool) ($rule['is_fail'] ?? false),
                ]);
            }

            $this->audit->record(
                'grade_rules_updated',
                'Academics',
                'Grade rules updated; existing marks retain their stored grades until re-entered.',
                null,
                null,
                ['rules' => count($data['rules'])]
            );
        });

        return back()->with('success', 'Grade rules updated.');
    }
}
