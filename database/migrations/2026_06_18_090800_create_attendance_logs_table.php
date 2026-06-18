<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('attendance_date');
            $table->string('event_type', 50);
            $table->dateTime('clocked_at');
            $table->string('source', 50);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->foreignId('employee_schedule_id')->nullable()->constrained('employee_schedules')->nullOnDelete();
            $table->boolean('is_valid')->default(true);
            $table->text('validation_message')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('device_identifier')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'attendance_date']);
            $table->index(['company_id', 'clocked_at']);
            $table->index(['company_id', 'event_type']);
            $table->index(['company_id', 'is_valid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
