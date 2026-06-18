<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('attendance_summary_id')->nullable()->constrained('attendance_summaries')->nullOnDelete();
            $table->date('attendance_date');

            $table->string('correction_type', 100);
            $table->text('reason');

            $table->timestamp('requested_clock_in_at')->nullable();
            $table->timestamp('requested_clock_out_at')->nullable();
            $table->foreignId('requested_work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->text('requested_notes')->nullable();

            $table->timestamp('approved_clock_in_at')->nullable();
            $table->timestamp('approved_clock_out_at')->nullable();
            $table->foreignId('approved_work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->text('approved_notes')->nullable();

            $table->string('status', 50)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('employees')->nullOnDelete();

            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'attendance_date'], 'att_corr_company_employee_date_idx');
            $table->index(['company_id', 'status'], 'att_corr_company_status_idx');
            $table->index(['company_id', 'correction_type'], 'att_corr_company_type_idx');
            $table->index(['company_id', 'approval_request_id'], 'att_corr_company_approval_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
