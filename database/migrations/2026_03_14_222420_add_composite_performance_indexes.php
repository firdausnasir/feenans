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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['ledger_id', 'account_id']);
            $table->index(['ledger_id', 'category_id']);
            $table->index(['ledger_id', 'payee_id']);
            $table->index(['ledger_id', 'transaction_type']);
            $table->index('description');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->index(['ledger_id', 'next_due_date']);
            $table->index(['ledger_id', 'is_active']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['ledger_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['ledger_id', 'account_id']);
            $table->dropIndex(['ledger_id', 'category_id']);
            $table->dropIndex(['ledger_id', 'payee_id']);
            $table->dropIndex(['ledger_id', 'transaction_type']);
            $table->dropIndex(['description']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['ledger_id', 'next_due_date']);
            $table->dropIndex(['ledger_id', 'is_active']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['ledger_id', 'parent_id']);
        });
    }
};
