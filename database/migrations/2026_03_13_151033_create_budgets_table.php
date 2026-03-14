<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('period')->default('monthly'); // monthly, weekly, yearly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('rollover')->default(false);
            $table->timestamps();

            $table->index(['ledger_id', 'is_active']);
            $table->index(['ledger_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
