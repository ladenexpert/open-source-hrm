<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_overnight')->default(false);
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_overnight']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_patterns');
    }
};
