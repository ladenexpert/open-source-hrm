<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('attendance_payroll_snapshot_id')->nullable()->constrained('attendance_payroll_snapshots')->restrictOnDelete();
            $table->string('status', 50)->default('draft');
            $table->text('readiness_message')->nullable();
            $table->string('snapshot_status', 50)->nullable();
            $table->unsignedInteger('total_work_days')->default(0);
            $table->unsignedInteger('total_present_days')->default(0);
            $table->unsignedInteger('total_absent_days')->default(0);
            $table->unsignedInteger('total_late_minutes')->default(0);
            $table->unsignedInteger('total_early_leave_minutes')->default(0);
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('total_overtime_minutes')->default(0);
            $table->decimal('total_leave_days', 8, 2)->default(0);
            $table->unsignedInteger('total_correction_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_run_employees_run_employee_unique');
            $table->index(['company_id', 'status'], 'payroll_run_employees_company_status_index');
            $table->index(['payroll_run_id', 'status'], 'payroll_run_employees_run_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_employees');
    }
};
