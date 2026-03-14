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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->unsignedTinyInteger('statement_day')->nullable();
            $table->boolean('include_in_totals')->default(true);
            $table->timestamps();

            $table->unique(['ledger_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
