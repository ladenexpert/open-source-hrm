<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_pattern_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('shift_pattern_id')->constrained('shift_patterns')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('break_duration_minutes')->default(0);
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();

            $table->unique(['shift_pattern_id', 'day_of_week'], 'shift_pattern_details_day_unique');
            $table->index(['company_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_pattern_details');
    }
};
