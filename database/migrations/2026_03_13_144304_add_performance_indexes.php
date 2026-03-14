<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('account_id');
            $table->index('category_id');
            $table->index('payee_id');
            $table->index('transaction_type');
            $table->index(['account_id', 'transaction_date']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->index('next_due_date');
            $table->index('is_active');
            $table->index(['ledger_id', 'is_active', 'next_due_date']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['payee_id']);
            $table->dropIndex(['transaction_type']);
            $table->dropIndex(['account_id', 'transaction_date']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['next_due_date']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['ledger_id', 'is_active', 'next_due_date']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
        });
    }
};
