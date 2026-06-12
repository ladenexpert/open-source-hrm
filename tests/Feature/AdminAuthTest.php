<?php

namespace Tests\Feature;

use App\Models\Employee;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_page_can_be_accessed(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_register_page_is_not_publicly_accessible(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_reset_password_page_can_be_accessed(): void
    {
        $this->get('/password-reset/request')->assertOk();
    }

    public function test_authorized_admin_can_access_admin_panel(): void
    {
        $admin = $this->makeEmployee('admin');

        $this->actingAs($admin)
            ->get('/')
            ->assertOk();
    }

    public function test_regular_employee_cannot_access_admin_panel(): void
    {
        $employee = $this->makeEmployee('employee');

        $this->actingAs($employee)
            ->get('/')
            ->assertForbidden();
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;

        $employee = Employee::create(array_merge([
            'employee_code' => sprintf('EMP-A-%03d', $sequence),
            'first_name' => 'Admin',
            'last_name' => "User {$sequence}",
            'email' => sprintf('admin-auth-%03d@example.com', $sequence),
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->assignRole($role);

        return $employee;
    }
}
