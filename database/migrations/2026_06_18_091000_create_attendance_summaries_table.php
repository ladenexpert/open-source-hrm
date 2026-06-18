<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('attendance_date');
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->foreignId('shift_pattern_detail_id')->nullable()->constrained('shift_pattern_details')->nullOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->foreignId('employee_schedule_id')->nullable()->constrained('employee_schedules')->nullOnDelete();
            $table->foreignId('attendance_policy_id')->nullable()->constrained('attendance_policies')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->unsignedInteger('break_duration_minutes')->default(0);
            $table->dateTime('actual_in_at')->nullable();
            $table->dateTime('actual_out_at')->nullable();
            $table->foreignId('first_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->foreignId('last_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->unsignedInteger('work_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_out_minutes')->default(0);
            $table->string('status', 50);
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_recalculated')->default(false);
            $table->dateTime('calculated_at')->nullable();
            $table->text('calculation_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'attendance_date'], 'attendance_summaries_unique');
            $table->index(['company_id', 'attendance_date'], 'attendance_summaries_company_date_index');
            $table->index(['company_id', 'employee_id', 'attendance_date'], 'attendance_summaries_company_employee_date_index');
            $table->index(['company_id', 'status'], 'attendance_summaries_company_status_index');
            $table->index(['company_id', 'is_complete'], 'attendance_summaries_company_complete_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
