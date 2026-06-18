<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('attendance_policy_id')->nullable()->constrained('attendance_policies')->nullOnDelete();
            $table->string('attendance_location_mode_override', 20)->nullable();

            $table->index(['company_id', 'attendance_policy_id'], 'employees_company_attendance_policy_index');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex('employees_company_attendance_policy_index');
            $table->dropConstrainedForeignId('attendance_policy_id');
            $table->dropColumn('attendance_location_mode_override');
        });
    }
};
