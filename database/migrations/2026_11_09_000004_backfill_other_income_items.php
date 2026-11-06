<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy other-income entries carried a single amount. Create one item row
     * per entry so the invoice editor and item counts stay consistent, without
     * changing any recorded amount.
     */
    public function up(): void
    {
        if (! Schema::hasTable('other_income_items')) {
            return;
        }

        $incomes = DB::table('other_incomes')
            ->whereNotIn('id', DB::table('other_income_items')->select('other_income_id'))
            ->get(['id', 'title', 'amount']);

        $rows = [];
        $now = now();

        foreach ($incomes as $income) {
            $rows[] = [
                'other_income_id' => $income->id,
                'description' => $income->title ?: 'Income',
                'quantity' => 1,
                'unit_price' => $income->amount,
                'amount' => $income->amount,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('other_income_items')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Backfilled rows are kept.
    }
};
