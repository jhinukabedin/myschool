<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy expenses were recorded as a single amount without line items.
     * Create one item row per legacy expense so the invoice editor and the
     * item count stay consistent. Existing amounts are preserved exactly.
     */
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('expense_items')) {
            return;
        }

        $expenses = DB::table('expenses')
            ->whereNotIn('id', DB::table('expense_items')->select('expense_id'))
            ->get(['id', 'title', 'amount']);

        $rows = [];
        $now = now();

        foreach ($expenses as $expense) {
            $rows[] = [
                'expense_id' => $expense->id,
                'description' => $expense->title ?: 'Expense',
                'quantity' => 1,
                'unit_price' => $expense->amount,
                'amount' => $expense->amount,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('expense_items')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Backfilled rows are indistinguishable from real data; keep them.
    }
};
