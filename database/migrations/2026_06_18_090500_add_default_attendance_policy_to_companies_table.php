<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('default_attendance_policy_id')->nullable()->constrained('attendance_policies')->nullOnDelete();
            $table->foreignId('default_shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_attendance_policy_id');
            $table->dropConstrainedForeignId('default_shift_pattern_id');
        });
    }
};
