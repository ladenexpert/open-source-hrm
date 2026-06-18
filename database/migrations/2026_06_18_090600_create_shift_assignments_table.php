<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('assignable_type', 50);
            $table->unsignedBigInteger('assignable_id');
            $table->foreignId('shift_pattern_id')->constrained('shift_patterns')->restrictOnDelete();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'assignable_type', 'assignable_id', 'effective_date'], 'shift_assignments_scope_index');
            $table->index(['company_id', 'shift_pattern_id'], 'shift_assignments_company_pattern_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
