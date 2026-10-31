<?php

namespace App\Http\Controllers\Fee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeStructureRequest;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditLogService;
use App\Services\FeeAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeeStructureController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly FeeAssignmentService $fees
    ) {}

    public function index(Request $request): View
    {
        $session = $request->filled('session_id')
            ? AcademicSession::find($request->integer('session_id'))
            : current_session();

        $structures = FeeStructure::query()
            ->with(['class', 'items.category', 'academicSession'])
            ->withCount('assignments')
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('fees.structures.index', [
            'structures' => $structures,
            'session' => $session,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('fees.structures.form', [
            'structure' => new FeeStructure([
                'academic_session_id' => current_session()?->id,
                'effective_from' => now()->toDateString(),
                'status' => 'active',
            ]),
            'categories' => FeeCategory::orderBy('name')->get(),
            'classes' => SchoolClass::ordered()->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'initialItems' => [],
        ]);
    }

    public function store(StoreFeeStructureRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = array_values($data['items']);
        unset($data['items']);

        $structure = DB::transaction(function () use ($data, $items, $request) {
            $structure = FeeStructure::create($data + ['created_by' => $request->user()->id]);

            foreach ($items as $item) {
                $structure->items()->create([
                    'fee_category_id' => $item['fee_category_id'],
                    'amount' => $item['amount'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'frequency' => $item['frequency'],
                    'due_day' => $item['due_day'],
                    'is_optional' => (bool) ($item['is_optional'] ?? false),
                ]);
            }

            $this->audit->record(
                'fee_structure_created',
                'Fees',
                "Fee structure {$structure->name} created for {$structure->class?->name}",
                $structure,
                null,
                ['items' => count($items)]
            );

            return $structure;
        });

        return redirect()->route('fees.structures.index')->with('success', "Fee structure “{$structure->name}” created. Assign it to students from the collection page or the Assign button.");
    }

    public function edit(FeeStructure $feeStructure): View
    {
        $feeStructure->load('items');

        return view('fees.structures.form', [
            'structure' => $feeStructure,
            'categories' => FeeCategory::orderBy('name')->get(),
            'classes' => SchoolClass::ordered()->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'initialItems' => $feeStructure->items->map(fn ($item) => [
                'fee_category_id' => (string) $item->fee_category_id,
                'amount' => (string) $item->amount,
                'discount_amount' => (string) $item->discount_amount,
                'frequency' => $item->frequency,
                'due_day' => (string) $item->due_day,
                'is_optional' => (bool) $item->is_optional,
            ])->all(),
        ]);
    }

    public function update(StoreFeeStructureRequest $request, FeeStructure $feeStructure): RedirectResponse
    {
        $data = $request->validated();
        $items = array_values($data['items']);
        unset($data['items']);

        $before = $feeStructure->getAttributes();

        DB::transaction(function () use ($feeStructure, $data, $items, $before) {
            $feeStructure->update($data);
            $keep = [];

            foreach ($items as $item) {
                $existing = $feeStructure->items()->where('fee_category_id', $item['fee_category_id'])->first();

                if ($existing) {
                    $existing->update([
                        'amount' => $item['amount'],
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'frequency' => $item['frequency'],
                        'due_day' => $item['due_day'],
                        'is_optional' => (bool) ($item['is_optional'] ?? false),
                    ]);
                    $keep[] = $existing->id;
                } else {
                    $created = $feeStructure->items()->create([
                        'fee_category_id' => $item['fee_category_id'],
                        'amount' => $item['amount'],
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'frequency' => $item['frequency'],
                        'due_day' => $item['due_day'],
                        'is_optional' => (bool) ($item['is_optional'] ?? false),
                    ]);
                    $keep[] = $created->id;
                }
            }

            $removed = $feeStructure->items()->whereNotIn('id', $keep)->get();

            foreach ($removed as $item) {
                if ($item->assignments()->exists()) {
                    throw ValidationException::withMessages([
                        'items' => 'A fee category with assigned student dues cannot be removed. Mark the structure inactive instead.',
                    ]);
                }

                $item->delete();
            }

            $this->audit->record(
                'fee_structure_updated',
                'Fees',
                "Fee structure {$feeStructure->name} updated",
                $feeStructure,
                $before,
                $feeStructure->fresh()->getAttributes()
            );
        });

        return redirect()->route('fees.structures.index')->with('success', 'Fee structure updated.');
    }

    public function assign(Request $request, FeeStructure $feeStructure): RedirectResponse
    {
        $session = $feeStructure->academicSession;

        if ($request->filled('student_id')) {
            $student = Student::findOrFail($request->integer('student_id'));
            $created = $this->fees->assignStructure($student, $feeStructure, $session, [], $request->user());

            return back()->with('success', "{$created} fee row(s) assigned to {$student->full_name}.");
        }

        $created = $this->fees->assignForClass($feeStructure, $session, $request->user());

        return back()->with('success', "Fee structure assigned to the class. {$created} new fee row(s) created.");
    }
}
