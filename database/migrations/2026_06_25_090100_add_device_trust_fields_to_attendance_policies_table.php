<?php

use App\Models\AttendancePolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->string('trusted_device_mode', 20)
                ->default(AttendancePolicy::TRUSTED_DEVICE_MODE_NONE)
                ->after('radius_meters');
            $table->boolean('auto_trust_first_device')
                ->default(false)
                ->after('trusted_device_mode');
            $table->unsignedInteger('max_trusted_devices')
                ->nullable()
                ->after('auto_trust_first_device');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->dropColumn([
                'trusted_device_mode',
                'auto_trust_first_device',
                'max_trusted_devices',
            ]);
        });
    }
};
