<?php

namespace App\Http\Controllers\Fee;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentScholarship;
use App\Services\ScholarshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScholarshipController extends Controller
{
    public function __construct(private readonly ScholarshipService $service) {}

    public function index(Request $request): View
    {
        $this->service->expireOverdue();

        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id') ?: current_session()?->id;
        $classes = \App\Models\SchoolClass::ordered()->get();

        $scholarships = StudentScholarship::query()
            ->with(['student.guardians', 'academicSession', 'items.category'])
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->when($request->filled('class_id'), fn ($q) => $q->whereHas('student.enrollments', fn ($e) => $e->where('academic_session_id', $sessionId)->where('class_id', $request->integer('class_id'))))
            ->when($request->filled('section_id'), fn ($q) => $q->whereHas('student.enrollments', fn ($e) => $e->where('academic_session_id', $sessionId)->where('section_id', $request->integer('section_id'))))
            ->when($request->filled('scholarship_type'), fn ($q) => $q->where('scholarship_type', $request->string('scholarship_type')))
            ->when($request->filled('discount_method'), fn ($q) => $q->where('discount_method', $request->string('discount_method')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $base = StudentScholarship::query()->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId));

        $cards = [
            'recipients' => (clone $base)->count(),
            'active_count' => (clone $base)->where('status', 'active')->count(),
            'expired' => (clone $base)->where('status', 'expired')->count(),
            'suspended' => (clone $base)->where('status', 'suspended')->count(),
            'monthly_amount' => (float) (clone $base)->where('status', 'active')->where('discount_frequency', 'monthly')->sum('discount_value'),
            'year_amount' => (float) (clone $base)->where('status', 'active')->where('discount_frequency', 'academic_year')->sum('discount_value'),
        ];

        return view('fees.scholarships.dashboard', [
            'scholarships' => $scholarships,
            'sessions' => $sessions,
            'sessionId' => $sessionId,
            'classes' => $classes,
            'types' => StudentScholarship::TYPES,
            'statuses' => StudentScholarship::STATUSES,
            'cards' => $cards,
        ]);
    }

    public function create(Request $request): View
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $sessionId = $request->integer('session_id') ?: current_session()?->id;
        $selectedStudent = $request->filled('student_id') ? Student::with('guardians')->find($request->integer('student_id')) : null;

        return view('fees.scholarships.create', [
            'sessions' => $sessions,
            'sessionId' => $sessionId,
            'categories' => FeeCategory::orderBy('name')->get(),
            'types' => StudentScholarship::TYPES,
            'frequencies' => StudentScholarship::FREQUENCIES,
            'selectedStudent' => $selectedStudent,
            'form' => null,
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $sessionId = $request->integer('session_id');
        $term = trim((string) $request->query('q'));

        if ($sessionId && ! AcademicSession::whereKey($sessionId)->exists()) {
            return response()->json([]);
        }

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $students = Enrollment::query()
            ->with(['student.guardians', 'class', 'section'])
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->where('status', '!=', 'inactive')
            ->whereHas('student', fn ($q) => $q
                ->where('status', 'active')
                ->where(function ($w) use ($term) {
                    $w->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('admission_no', 'like', "%{$term}%")
                        ->orWhere('student_code', 'like', "%{$term}%")
                        ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%"));
                }))
            ->orderBy('class_id')
            ->orderBy('roll_no')
            ->limit(10)
            ->get()
            ->map(fn ($enrollment) => [
                'id' => $enrollment->student->id,
                'name' => $enrollment->student->full_name,
                'admission_no' => $enrollment->student->admission_no,
                'student_code' => $enrollment->student->student_code,
                'class' => $enrollment->class?->name,
                'section' => $enrollment->section?->name,
                'roll' => $enrollment->roll_no,
                'guardian_phone' => $enrollment->student->guardians->first()?->phone,
            ]);

        return response()->json($students);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'discount_method' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'applies_to' => ['nullable', 'in:all,selected'],
            'fee_category_ids' => ['nullable', 'array'],
            'fee_category_ids.*' => ['integer', 'exists:fee_categories,id'],
        ]);

        if ($data['discount_method'] === 'percent' && (float) $data['discount_value'] > 100) {
            return response()->json(['error' => 'Percentage must not exceed 100.'], 422);
        }

        $student = Student::with('guardians')->findOrFail($data['student_id']);
        $sessionId = (int) $data['academic_session_id'];

        $enrollment = Enrollment::query()
            ->with(['class', 'section'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', '!=', 'inactive')
            ->first();

        if (! $enrollment) {
            return response()->json(['error' => 'This student is not enrolled in the selected academic year.'], 422);
        }

        $draft = StudentScholarship::make([
            'student_id' => $student->id,
            'academic_session_id' => $sessionId,
            'discount_method' => $data['discount_method'],
            'discount_value' => $data['discount_value'],
            'applies_to' => $data['applies_to'] ?? 'all',
            'discount_frequency' => 'monthly',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        if (($draft->applies_to ?? 'all') === 'selected') {
            $selected = $data['fee_category_ids'] ?? [];
            $draft->setRelation(
                'items',
                collect($selected)->map(fn ($id) => (clone new \App\Models\StudentScholarshipItem)
                    ->forceFill(['fee_category_id' => $id]))
            );
        } else {
            $draft->setRelation('items', collect());
        }

        $structures = FeeStructure::with(['items.category'])
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $enrollment->class_id)
            ->where('status', 'active')
            ->get();

        $rows = [];
        $totals = ['original' => 0.0, 'discount' => 0.0, 'payable' => 0.0];

        foreach ($structures as $structure) {
            foreach ($structure->items as $item) {
                $gross = (float) $item->amount;
                $discount = $draft->discountForFeeCategory((int) $item->fee_category_id, $gross);
                $payable = max(0, $gross - $discount);

                if ($gross <= 0) {
                    continue;
                }

                $rows[] = [
                    'category' => $item->category?->name ?? 'Fee',
                    'original' => round($gross, 2),
                    'discount_type' => $data['discount_method'],
                    'discount_value' => (float) $data['discount_value'],
                    'discount_amount' => round($discount, 2),
                    'payable' => round($payable, 2),
                ];

                $totals['original'] += $gross;
                $totals['discount'] += $discount;
                $totals['payable'] += $payable;
            }
        }

        if ($rows === []) {
            $rows = [[
                'category' => 'No active fee structure items for this class in the selected session.',
                'original' => 0, 'discount_type' => $data['discount_method'],
                'discount_value' => 0, 'discount_amount' => 0, 'payable' => 0,
            ]];
        }

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'admission_no' => $student->admission_no,
                'student_code' => $student->student_code,
                'class' => $enrollment->class?->name,
                'section' => $enrollment->section?->name,
                'roll' => $enrollment->roll_no,
            ],
            'rows' => $rows,
            'totals' => array_map(fn ($v) => round($v, 2), $totals),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'scholarship_name' => ['required', 'string', 'max:120'],
            'scholarship_type' => ['required', 'in:'.implode(',', array_keys(StudentScholarship::TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,pending,approved,active'],
            'discount_method' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'applies_to' => ['required', 'in:all,selected'],
            'discount_frequency' => ['required', 'in:once,monthly,per_invoice,academic_year'],
            'fee_category_ids' => ['nullable', 'array'],
            'fee_category_ids.*' => ['integer', 'exists:fee_categories,id'],
            'apply_to_existing' => ['nullable', 'boolean'],
        ]);

        if ($data['discount_method'] === 'percent' && (float) $data['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Percentage must not exceed 100.'])->withInput();
        }

        if ($data['applies_to'] === 'selected' && empty($data['fee_category_ids'])) {
            return back()->withErrors(['fee_category_ids' => 'Select at least one fee category.'])->withInput();
        }

        $data['items'] = collect($data['fee_category_ids'] ?? [])->map(fn ($id) => [
            'fee_category_id' => $id,
            'discount_type' => $data['discount_method'],
            'discount_value' => $data['discount_value'],
            'discount_frequency' => $data['discount_frequency'],
            'maximum_discount_amount' => $data['maximum_discount_amount'] ?? null,
        ])->all();

        unset($data['fee_category_ids']);

        $scholarship = $this->service->create($data, $request->user());

        if ($request->boolean('apply_to_existing') && in_array($scholarship->status, ['active', 'approved'], true)) {
            $count = $this->service->applyToExistingInvoices($scholarship, $request->user());
            return redirect()
                ->route('fees.scholarships.show', $scholarship)
                ->with('success', "Scholarship saved and applied to {$count} unpaid invoice row(s).");
        }

        return redirect()
            ->route('fees.scholarships.show', $scholarship)
            ->with('success', 'Scholarship saved.');
    }

    public function show(StudentScholarship $scholarship): View
    {
        $scholarship->load(['student.guardians', 'academicSession', 'items.category', 'adjustments']);

        $unpaid = \App\Models\StudentFeeAssignment::query()
            ->with('category')
            ->where('student_id', $scholarship->student_id)
            ->where('academic_session_id', $scholarship->academic_session_id)
            ->whereIn('status', ['pending', 'partial'])
            ->withSum(['paymentAllocations as paid_total' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'completed'))], 'amount')
            ->orderBy('due_date')
            ->get();

        return view('fees.scholarships.show', compact('scholarship', 'unpaid'));
    }

    public function update(StudentScholarship $scholarship, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scholarship_name' => ['sometimes', 'string', 'max:120'],
            'scholarship_type' => ['sometimes', 'in:'.implode(',', array_keys(StudentScholarship::TYPES))],
            'discount_method' => ['sometimes', 'in:percent,fixed'],
            'discount_value' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (($data['discount_method'] ?? $scholarship->discount_method) === 'percent'
            && (float) ($data['discount_value'] ?? $scholarship->discount_value) > 100) {
            return back()->withErrors(['discount_value' => 'Percentage must not exceed 100.'])->withInput();
        }

        $old = $scholarship->only(['scholarship_name', 'discount_method', 'discount_value', 'status']);

        $scholarship->fill($data)->save();

        app(\App\Services\AuditLogService::class)->record(
            'scholarship_updated',
            'Fees',
            "Scholarship {$scholarship->scholarship_name} updated for {$scholarship->student?->full_name}",
            $scholarship,
            $old,
            $scholarship->only(['scholarship_name', 'discount_method', 'discount_value', 'status'])
        );

        return back()->with('success', 'Scholarship updated.');
    }

    public function destroy(StudentScholarship $scholarship): RedirectResponse
    {
        if ($scholarship->adjustments()->exists()) {
            return back()->withErrors(['scholarship' => 'This scholarship has invoice adjustments and cannot be deleted. Suspend it instead.']);
        }

        $name = $scholarship->scholarship_name;
        $studentName = $scholarship->student?->full_name;
        $scholarship->delete();

        app(\App\Services\AuditLogService::class)->record(
            'scholarship_deleted',
            'Fees',
            "Scholarship {$name} deleted for {$studentName}",
            $scholarship,
            null,
            null
        );

        return redirect()->route('fees.scholarships.index')->with('success', 'Scholarship deleted.');
    }

    public function approve(StudentScholarship $scholarship, Request $request): RedirectResponse
    {
        $request->validate(['apply_to_existing' => ['nullable', 'boolean']]);

        $this->service->activate($scholarship, $request->user(), $request->boolean('apply_to_existing'));

        return back()->with('success', 'Scholarship approved and activated.');
    }

    public function reject(StudentScholarship $scholarship): RedirectResponse
    {
        $scholarship->forceFill(['status' => 'rejected'])->save();

        return back()->with('success', 'Scholarship rejected.');
    }

    public function suspend(StudentScholarship $scholarship): RedirectResponse
    {
        $this->service->suspend($scholarship, request()->user());

        return back()->with('success', 'Scholarship suspended.');
    }

    public function reactivate(StudentScholarship $scholarship): RedirectResponse
    {
        $this->service->reactivate($scholarship, request()->user());

        return back()->with('success', 'Scholarship reactivated.');
    }

    public function renew(StudentScholarship $scholarship): RedirectResponse
    {
        $copy = $this->service->renew($scholarship, [
            'student_id' => $scholarship->student_id,
            'academic_session_id' => $scholarship->academic_session_id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ], request()->user());

        return redirect()->route('fees.scholarships.show', $copy)->with('success', 'Scholarship renewed as pending approval.');
    }

    public function applyToInvoices(StudentScholarship $scholarship, Request $request): RedirectResponse
    {
        abort_unless(in_array($scholarship->status, ['active', 'approved'], true), 422, 'Only active scholarships can be applied to invoices.');

        $count = $this->service->applyToExistingInvoices($scholarship, $request->user());

        return back()->with('success', "Scholarship applied to {$count} unpaid invoice row(s).");
    }

    public function history(StudentScholarship $scholarship): View
    {
        $scholarship->load(['student.guardians', 'items.category', 'adjustments']);

        $audit = \App\Models\AuditLog::query()
            ->with('user')
            ->where(function ($q) use ($scholarship) {
                $q->where('model_type', StudentScholarship::class)
                    ->where('model_id', $scholarship->id)
                    ->orWhere('description', 'like', "%{$scholarship->scholarship_name}%");
            })
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('fees.scholarships.history', compact('scholarship', 'audit'));
    }
}
