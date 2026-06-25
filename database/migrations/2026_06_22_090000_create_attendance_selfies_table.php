<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_selfies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_log_id')->constrained('attendance_logs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('image_path');
            $table->dateTime('captured_at')->nullable();
            $table->json('device_info')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('attendance_log_id');
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_selfies');
    }
};
