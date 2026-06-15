<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_group_id')->nullable()->constrained('company_groups')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('module_type', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module_type', 'is_active']);
            $table->index(['company_id', 'company_group_id']);
            $table->unique(['company_group_id', 'company_id', 'code'], 'approval_workflows_scope_code_unique');
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('approver_type', 100);
            $table->string('approver_role', 100)->nullable();
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approver_job_level_id')->nullable()->constrained('job_levels')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->boolean('can_return')->default(false);
            $table->boolean('is_final_step')->default(false);
            $table->timestamps();

            $table->index(['approval_workflow_id', 'step_order']);
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_group_id')->nullable()->constrained('company_groups')->nullOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->morphs('approvable');
            $table->foreignId('requester_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employee_subject_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('module_type', 100);
            $table->string('status', 50)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('current_step_order')->nullable();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'module_type', 'status']);
            $table->index(['requester_id', 'status']);
        });

        Schema::create('approval_request_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->unsignedInteger('step_order');
            $table->foreignId('approver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('approver_type', 100);
            $table->string('status', 50)->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['approval_request_id', 'step_order']);
            $table->index(['approver_id', 'status']);
        });

        Schema::create('approval_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('action', 100);
            $table->text('comments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['approval_request_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
