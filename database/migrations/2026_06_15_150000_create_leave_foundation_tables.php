<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('allow_carry_forward')->default(false);
            $table->decimal('max_carry_forward_days', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->constrained('job_levels')->nullOnDelete();
            $table->decimal('entitlement_days', 8, 2);
            $table->unsignedInteger('minimum_service_months')->default(0);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'leave_type_id', 'is_active']);
        });

        Schema::create('holiday_calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('year');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'year', 'name']);
            $table->index(['company_id', 'year', 'is_active']);
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->string('type', 50)->default('company');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();

            $table->unique(['holiday_calendar_id', 'date', 'name']);
            $table->index(['company_id', 'date', 'type']);
        });

        Schema::create('workday_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_default', 'is_active']);
        });

        Schema::create('workday_pattern_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workday_pattern_id')->constrained('workday_patterns')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->decimal('working_hours', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['workday_pattern_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workday_pattern_days');
        Schema::dropIfExists('workday_patterns');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
