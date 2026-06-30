<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('attendance_summary_id')->nullable()->constrained('attendance_summaries')->nullOnDelete();
            $table->date('overtime_date');
            $table->dateTime('requested_start_at')->nullable();
            $table->dateTime('requested_end_at')->nullable();
            $table->unsignedInteger('requested_minutes')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 50);
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'overtime_date'], 'ot_req_company_employee_date_idx');
            $table->index(['company_id', 'status'], 'ot_req_company_status_idx');
            $table->index(['company_id', 'approval_request_id'], 'ot_req_company_approval_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
