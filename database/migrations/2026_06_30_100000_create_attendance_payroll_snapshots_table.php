<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_payroll_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('total_work_days')->default(0);
            $table->unsignedInteger('total_present_days')->default(0);
            $table->unsignedInteger('total_absent_days')->default(0);
            $table->unsignedInteger('total_late_minutes')->default(0);
            $table->unsignedInteger('total_early_leave_minutes')->default(0);
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('total_overtime_minutes')->default(0);
            $table->decimal('total_leave_days', 8, 2)->default(0);
            $table->unsignedInteger('total_correction_count')->default(0);
            $table->string('snapshot_status', 50)->default('draft');
            $table->dateTime('calculated_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'employee_id', 'period_start', 'period_end'],
                'attendance_payroll_snapshots_company_employee_period_unique'
            );
            $table->index(['company_id', 'snapshot_status'], 'attendance_payroll_snapshots_company_status_index');
            $table->index(['company_id', 'period_start', 'period_end'], 'attendance_payroll_snapshots_company_period_index');
            $table->index(['employee_id', 'period_start', 'period_end'], 'attendance_payroll_snapshots_employee_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_payroll_snapshots');
    }
};
