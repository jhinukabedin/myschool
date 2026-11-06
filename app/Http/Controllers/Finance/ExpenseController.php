<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\AcademicSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $expenses = Expense::query()
            ->with(['category', 'recordedBy', 'academicSession', 'items'])
            ->withCount('items')
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->when($request->filled('category_id'), fn ($query) => $query->where('expense_category_id', $request->integer('category_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->date('to')))
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->input('q').'%'))
            ->latest('expense_date')
            ->paginate(20)
            ->withQueryString();

        $byCategory = Expense::query()
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->with('category')
            ->get();

        return view('finance.expenses.index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'session' => $session,
            'byCategory' => $byCategory,
            'summary' => [
                'total' => $session ? Expense::where('academic_session_id', $session->id)->sum('amount') : 0,
                'month' => $session ? Expense::where('academic_session_id', $session->id)
                    ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('amount') : 0,
                'count' => $session ? Expense::where('academic_session_id', $session->id)->count() : 0,
            ],
        ]);
    }

    public function create(): View
    {
        return view('finance.expenses.form', [
            'expense' => new Expense([
                'academic_session_id' => current_session()?->id,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ]),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $this->normaliseItems($data['items'] ?? []);
        unset($data['items'], $data['receipt']);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('expenses', 'public');
        }

        $data['amount'] = $this->itemsTotal($items);
        $data['recorded_by'] = $request->user()->id;

        $expense = DB::transaction(function () use ($data, $items) {
            $expense = Expense::create($data);
            $this->syncItems($expense, $items);

            return $expense;
        });

        $this->audit->record(
            'expense_recorded',
            'Finance',
            "Expense “{$expense->title}” of ".money($expense->amount).' recorded with '.count($items).' item(s)',
            $expense,
            null,
            $expense->getAttributes()
        );

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense): View
    {
        $expense->load('items');

        return view('finance.expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();
        $items = $this->normaliseItems($data['items'] ?? []);
        unset($data['items'], $data['receipt']);
        $before = $expense->getAttributes();

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('expenses', 'public');
        }

        $data['amount'] = $this->itemsTotal($items);

        DB::transaction(function () use ($expense, $data, $items) {
            $expense->update($data);
            $this->syncItems($expense, $items);
        });

        $this->audit->record(
            'expense_updated',
            'Finance',
            "Expense “{$expense->title}” updated",
            $expense,
            $before,
            $expense->fresh()->getAttributes()
        );

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    /**
     * Keep only complete item rows and compute each line amount defensively
     * on the server (never trust the browser total).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{description: string, quantity: float, unit_price: float, amount: float, sort_order: int}>
     */
    private function normaliseItems(array $items): array
    {
        $normalised = [];

        foreach (array_values($items) as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = max(0.001, round((float) ($item['quantity'] ?? 1), 3));
            $unitPrice = max(0, round((float) ($item['unit_price'] ?? 0), 2));

            if ($description === '' && $unitPrice <= 0) {
                continue;
            }

            $normalised[] = [
                'description' => $description !== '' ? $description : 'Item '.($index + 1),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
                'sort_order' => $index,
            ];
        }

        return $normalised;
    }

    private function itemsTotal(array $items): float
    {
        return round(array_sum(array_column($items, 'amount')), 2);
    }

    /**
     * Replace the item lines of an expense with the submitted set.
     */
    private function syncItems(Expense $expense, array $items): void
    {
        $expense->items()->delete();

        if ($items !== []) {
            $expense->items()->createMany($items);
        }
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $before = $expense->getAttributes();
        $expense->delete();

        $this->audit->record(
            'expense_soft_deleted',
            'Finance',
            "Expense “{$expense->title}” of ".money($expense->amount).' removed from active reporting (recoverable)',
            $expense,
            $before,
            ['deleted_at' => now()->toDateTimeString()]
        );

        return back()->with('success', 'Expense archived. Financial history is preserved and can be restored by an administrator.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
        ]);

        $category = ExpenseCategory::create($data);
        $this->audit->record('expense_category_created', 'Finance', "Expense category {$category->name} created", $category, null, $data);

        return back()->with('success', 'Expense category added.');
    }
}
