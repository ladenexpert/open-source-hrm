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
        $this->call(ApprovalWorkflowSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
