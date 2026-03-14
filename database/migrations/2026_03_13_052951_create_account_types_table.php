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
        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_credit')->default(false);
            $table->timestamps();

            $table->unique(['ledger_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
