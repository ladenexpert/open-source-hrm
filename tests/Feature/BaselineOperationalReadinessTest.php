<?php

namespace Tests\Feature;

use App\Models\Employee;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaselineOperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_super_admin_can_access_admin_panel(): void
    {
        $superAdmin = Employee::query()->where('email', 'admin@hrms.local')->firstOrFail();

        $this->assertTrue($superAdmin->hasNormalizedRole('super_admin'));

        $this->actingAs($superAdmin)
            ->get('/')
            ->assertOk();
    }

    public function test_seeded_employee_can_access_portal(): void
    {
        $employee = Employee::query()->where('email', 'employee@hrms.local')->firstOrFail();

        $this->assertTrue($employee->hasNormalizedRole('employee'));

        $this->actingAs($employee)
            ->get('/portal')
            ->assertOk();
    }

    public function test_employee_factory_is_available_and_user_factory_is_removed(): void
    {
        $employee = Employee::factory()->create();

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertNotSame('Password123!', $employee->getAuthPassword());
        $this->assertFileDoesNotExist(database_path('factories/UserFactory.php'));
    }

    public function test_timezone_defaults_to_asia_jakarta(): void
    {
        $this->assertSame(env('APP_TIMEZONE', 'Asia/Jakarta'), config('app.timezone'));
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }
}
