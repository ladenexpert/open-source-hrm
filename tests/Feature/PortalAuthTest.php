<?php

namespace Tests\Feature;

use App\Models\Employee;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_portal_login_page_can_be_accessed(): void
    {
        $this->get('/portal/login')->assertOk();
    }

    public function test_portal_reset_password_page_can_be_accessed(): void
    {
        $this->get('/portal/password-reset/request')->assertOk();
    }

    public function test_portal_registration_page_is_not_available(): void
    {
        $this->get('/portal/register')->assertNotFound();
    }

    public function test_active_employee_can_access_portal_panel(): void
    {
        $employee = $this->makeEmployee('employee');

        $this->actingAs($employee)
            ->get('/portal')
            ->assertOk();
    }

    public function test_inactive_employee_cannot_access_portal_panel(): void
    {
        $employee = $this->makeEmployee('employee', [
            'is_active' => false,
        ]);

        $this->actingAs($employee)
            ->get('/portal')
            ->assertForbidden();
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;

        $employee = Employee::create(array_merge([
            'employee_code' => sprintf('EMP-P-%03d', $sequence),
            'first_name' => 'Portal',
            'last_name' => "User {$sequence}",
            'email' => sprintf('portal-auth-%03d@example.com', $sequence),
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->assignRole($role);

        return $employee;
    }
}
