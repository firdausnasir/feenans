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
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('position');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('bill_id');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('transaction_type');
        });

        Schema::table('payees', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });

        Schema::table('payees', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });
    }
};
