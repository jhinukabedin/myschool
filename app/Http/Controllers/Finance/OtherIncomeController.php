<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOtherIncomeRequest;
use App\Models\AcademicSession;
use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OtherIncomeController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $incomes = OtherIncome::query()
            ->with(['category', 'recordedBy', 'academicSession', 'items'])
            ->withCount('items')
            ->forSession($session)
            ->when($request->filled('category_id'), fn ($query) => $query->where('other_income_category_id', $request->integer('category_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('income_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('income_date', '<=', $request->date('to')))
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->input('q').'%'))
            ->latest('income_date')
            ->paginate(20)
            ->withQueryString();

        $byCategory = OtherIncome::query()
            ->forSession($session)
            ->selectRaw('other_income_category_id, SUM(amount) as total')
            ->groupBy('other_income_category_id')
            ->with('category')
            ->get();

        return view('finance.other-incomes.index', [
            'incomes' => $incomes,
            'categories' => OtherIncomeCategory::ordered()->get(),
            'session' => $session,
            'byCategory' => $byCategory,
            'summary' => [
                'total' => $session ? OtherIncome::forSession($session)->sum('amount') : 0,
                'month' => $session ? OtherIncome::forSession($session)
                    ->whereBetween('income_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('amount') : 0,
                'count' => $session ? OtherIncome::forSession($session)->count() : 0,
            ],
        ]);
    }

    public function create(): View
    {
        return view('finance.other-incomes.form', [
            'income' => new OtherIncome(['income_date' => now()->toDateString()]),
            'categories' => OtherIncomeCategory::ordered()->get(),
        ]);
    }

    public function store(StoreOtherIncomeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $this->normaliseItems($data['items'] ?? []);
        unset($data['items']);

        $data['amount'] = $this->itemsTotal($items);
        $data['academic_session_id'] = current_session()?->id;
        $data['recorded_by'] = auth()->id();

        $income = DB::transaction(function () use ($data, $items) {
            $income = OtherIncome::create($data);
            $this->syncItems($income, $items);

            return $income;
        });

        $this->audit->record('other_income_created', 'Finance', "Other income {$income->title} created with ".count($items).' item(s)', $income, null, $data);

        return redirect()->route('other-incomes.index')->with('success', 'Other income recorded.');
    }

    public function edit(OtherIncome $otherIncome): View
    {
        $otherIncome->load('items');

        return view('finance.other-incomes.form', [
            'income' => $otherIncome,
            'categories' => OtherIncomeCategory::ordered()->get(),
        ]);
    }

    public function update(StoreOtherIncomeRequest $request, OtherIncome $otherIncome): RedirectResponse
    {
        $data = $request->validated();
        $items = $this->normaliseItems($data['items'] ?? []);
        unset($data['items']);

        $data['amount'] = $this->itemsTotal($items);
        $before = $otherIncome->getAttributes();

        DB::transaction(function () use ($otherIncome, $data, $items) {
            $otherIncome->update($data);
            $this->syncItems($otherIncome, $items);
        });

        $this->audit->record('other_income_updated', 'Finance', "Other income {$otherIncome->title} updated", $otherIncome, $before, $data);

        return redirect()->route('other-incomes.index')->with('success', 'Other income updated.');
    }

    /**
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

    private function syncItems(OtherIncome $income, array $items): void
    {
        $income->items()->delete();

        if ($items !== []) {
            $income->items()->createMany($items);
        }
    }

    public function destroy(OtherIncome $otherIncome): RedirectResponse
    {
        $old = $otherIncome->getAttributes();
        $otherIncome->delete();
        $this->audit->record('other_income_deleted', 'Finance', "Other income deleted", null, $old, null);

        return back()->with('success', 'Other income deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:other_income_categories,name'],
        ]);

        OtherIncomeCategory::create($data + ['is_active' => true]);

        return back()->with('success', 'Category created.');
    }
}
