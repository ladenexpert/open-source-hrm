<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('overtime_request_id')->nullable()->constrained('overtime_requests')->nullOnDelete();
            $table->foreignId('attendance_summary_id')->nullable()->constrained('attendance_summaries')->nullOnDelete();
            $table->date('calculation_date');
            $table->dateTime('scheduled_end_at')->nullable();
            $table->dateTime('actual_clock_out_at')->nullable();
            $table->unsignedInteger('actual_overtime_minutes')->default(0);
            $table->unsignedInteger('requested_minutes')->nullable();
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->unsignedInteger('calculated_minutes')->default(0);
            $table->string('calculation_status', 50);
            $table->dateTime('calculated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'calculation_date'], 'ot_calc_company_employee_date_unique');
            $table->index(['company_id', 'calculation_status'], 'ot_calc_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_calculations');
    }
};
