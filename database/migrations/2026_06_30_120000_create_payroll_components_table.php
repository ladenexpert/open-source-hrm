<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('component_code', 100)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('component_type', 50);
            $table->string('value_type', 50);
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->decimal('default_percentage', 8, 4)->nullable();
            $table->boolean('taxable')->default(false);
            $table->boolean('tax_deductible')->default(false);
            $table->boolean('bpjs_applicable')->default(false);
            $table->boolean('thr_applicable')->default(false);
            $table->boolean('proratable')->default(false);
            $table->boolean('recurring')->default(true);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'component_code'], 'payroll_components_company_code_unique');
            $table->index(['company_id', 'component_type'], 'payroll_components_company_type_index');
            $table->index(['company_id', 'value_type'], 'payroll_components_company_value_type_index');
            $table->index(['company_id', 'active'], 'payroll_components_company_active_index');
            $table->index(['company_id', 'sort_order'], 'payroll_components_company_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
    }
};
