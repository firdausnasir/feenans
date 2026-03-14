<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type');
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->uuid('transfer_pair_id')->nullable()->index();
            $table->timestamps();

            $table->index(['ledger_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
