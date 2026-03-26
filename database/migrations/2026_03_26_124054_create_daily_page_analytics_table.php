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
        Schema::create('daily_page_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('page_key');
            $table->string('audience');
            $table->string('membership_tier')->nullable();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'page_key', 'audience', 'membership_tier'], 'daily_page_analytics_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_page_analytics');
    }
};
