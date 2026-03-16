<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['transactions', 'accounts', 'bills', 'budgets', 'payees', 'tags'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                // Hard delete any soft-deleted rows first
                DB::table($table)->whereNotNull('deleted_at')->delete();

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('deleted_at');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['transactions', 'accounts', 'bills', 'budgets', 'payees', 'tags'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }
};
