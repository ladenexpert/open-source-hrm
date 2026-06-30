<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('period_code', 100)->nullable();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->string('status', 50)->default('draft');
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'payroll_periods_company_status_index');
            $table->index(['company_id', 'period_start', 'period_end'], 'payroll_periods_company_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
