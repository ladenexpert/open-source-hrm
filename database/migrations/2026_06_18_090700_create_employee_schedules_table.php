<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('schedule_date');
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->string('override_reason', 50);
            $table->foreignId('requested_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_id', 'schedule_date'], 'employee_schedules_scope_unique');
            $table->index(['company_id', 'schedule_date'], 'employee_schedules_company_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
