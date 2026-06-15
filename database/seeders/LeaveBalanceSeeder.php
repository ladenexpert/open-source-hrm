<?php

namespace Database\Seeders;

use App\Services\LeaveEntitlementService;
use Illuminate\Database\Seeder;

class LeaveBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = now('Asia/Jakarta')->year;

        app(LeaveEntitlementService::class)->generateAnnualEntitlements($currentYear);
    }
}
