<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('location_mode', 20)->default('fixed');
            $table->boolean('gps_required')->default(false);
            $table->boolean('selfie_required')->default(false);
            $table->boolean('radius_validation_enabled')->default(false);
            $table->unsignedInteger('radius_meters')->nullable();
            $table->unsignedInteger('late_tolerance_minutes')->default(0);
            $table->unsignedInteger('early_out_tolerance_minutes')->default(0);
            $table->unsignedInteger('minimum_work_minutes')->nullable();
            $table->unsignedInteger('auto_absent_after_minutes')->nullable();
            $table->unsignedInteger('overtime_threshold_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'location_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
