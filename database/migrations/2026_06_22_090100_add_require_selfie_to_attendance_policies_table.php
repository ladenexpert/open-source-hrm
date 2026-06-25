<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->boolean('require_selfie')->default(false)->after('selfie_required');
        });

        DB::table('attendance_policies')->update([
            'require_selfie' => DB::raw('selfie_required'),
        ]);
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->dropColumn('require_selfie');
        });
    }
};
