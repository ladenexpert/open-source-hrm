<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->unsignedInteger('year');
            $table->decimal('entitled_days', 8, 2)->default(0);
            $table->decimal('carried_forward_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->decimal('remaining_days', 8, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'leave_type_id', 'year'], 'leave_entitlements_scope_unique');
            $table->index(['company_id', 'year']);
            $table->index(['employee_id', 'leave_type_id']);
        });

        Schema::create('leave_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->foreignId('leave_entitlement_id')->nullable()->constrained('leave_entitlements')->nullOnDelete();
            $table->string('transaction_type', 50);
            $table->decimal('days', 8, 2);
            $table->decimal('balance_before', 8, 2)->nullable();
            $table->decimal('balance_after', 8, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'transaction_type']);
            $table->index(['employee_id', 'leave_type_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_transactions');
        Schema::dropIfExists('leave_entitlements');
    }
};
