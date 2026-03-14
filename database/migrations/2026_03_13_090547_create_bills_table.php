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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->string('recurrence_type');
            $table->integer('recurrence_interval')->default(1);
            $table->integer('recurrence_day')->nullable();
            $table->date('next_due_date');
            $table->boolean('auto_create')->default(false);
            $table->string('end_type')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('end_after_occurrences')->nullable();
            $table->integer('occurrences_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
