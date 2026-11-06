<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('other_income_items')) {
            return;
        }

        Schema::create('other_income_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('other_income_id')->constrained('other_incomes')->cascadeOnDelete();
            $table->string('description', 200);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('other_income_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_income_items');
    }
};
