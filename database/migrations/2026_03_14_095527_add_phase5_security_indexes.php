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
        Schema::table('tags', function (Blueprint $table) {
            $table->index('ledger_id');
        });

        Schema::table('tag_transaction', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex(['ledger_id']);
        });

        Schema::table('tag_transaction', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['tag_id']);
        });
    }
};
