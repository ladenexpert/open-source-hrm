<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->string('run_code', 100)->nullable();
            $table->string('run_type', 50)->default('regular');
            $table->string('status', 50)->default('draft');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('total_employees')->default(0);
            $table->unsignedInteger('ready_employees')->default(0);
            $table->unsignedInteger('blocked_employees')->default(0);
            $table->dateTime('prepared_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'payroll_runs_company_status_index');
            $table->index(['company_id', 'run_type', 'status'], 'payroll_runs_company_type_status_index');
            $table->index(['payroll_period_id', 'run_type'], 'payroll_runs_period_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
