<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(TenancySeeder::class);
        $this->call(HrMasterDataSeeder::class);
        $this->call(SampleWorkforceSeeder::class);
        $this->call(PayrollComponentSeeder::class);
        $this->call(ApprovalWorkflowSeeder::class);
        $this->call(LeaveFoundationSeeder::class);
        $this->call(LeaveBalanceSeeder::class);
        $this->call(LeaveRequestSeeder::class);
        $this->call(LeaveApprovalSeeder::class);
        $this->call(AttendanceFoundationSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
