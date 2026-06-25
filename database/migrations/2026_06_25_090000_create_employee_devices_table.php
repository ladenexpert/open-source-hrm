<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('device_uuid');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('status_reason')->nullable();
            $table->dateTime('first_seen_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->foreignId('trusted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('trusted_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->string('last_ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'device_uuid']);
            $table->index('employee_id');
            $table->index('status');
            $table->index('last_used_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_devices');
    }
};
