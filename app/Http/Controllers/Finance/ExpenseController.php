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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $expenses = Expense::query()
            ->with(['category', 'recordedBy', 'academicSession'])
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
        unset($data['receipt']);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('expenses', 'public');
        }

        $data['recorded_by'] = $request->user()->id;
        $expense = Expense::create($data);

        $this->audit->record(
            'expense_recorded',
            'Finance',
            "Expense “{$expense->title}” of ".money($expense->amount).' recorded',
            $expense,
            null,
            $expense->getAttributes()
        );

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense): View
    {
        return view('finance.expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();
        unset($data['receipt']);
        $before = $expense->getAttributes();

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('expenses', 'public');
        }

        $expense->update($data);

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
