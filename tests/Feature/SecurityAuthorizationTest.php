<?php

namespace Tests\Feature;

use App\Filament\Resources\Payrolls\PayrollResource;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Leave;
use App\Models\Message;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\Shift;
use App\Models\Task;
use App\Models\Topic;
use App\Policies\AttendancePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EventPolicy;
use App\Policies\LeavePolicy;
use App\Policies\MessagePolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PositionPolicy;
use App\Policies\ShiftPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TopicPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_finance_can_access_payroll_resource(): void
    {
        $finance = $this->makeEmployee('finance');

        $this->actingAs($finance)
            ->get(PayrollResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_non_finance_cannot_access_payroll_resource(): void
    {
        $admin = $this->makeEmployee('admin');

        $this->actingAs($admin)
            ->get(PayrollResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertForbidden();
    }

    public function test_employee_cannot_access_another_employees_data(): void
    {
        $employee = $this->makeEmployee('employee');
        $otherEmployee = $this->makeEmployee('employee');

        $this->assertFalse(
            Gate::forUser($employee)->allows('view', $otherEmployee)
        );
    }

    public function test_policies_are_registered_for_existing_models(): void
    {
        $expectedPolicies = [
            Employee::class => EmployeePolicy::class,
            Department::class => DepartmentPolicy::class,
            Position::class => PositionPolicy::class,
            Shift::class => ShiftPolicy::class,
            Attendance::class => AttendancePolicy::class,
            Leave::class => LeavePolicy::class,
            Payroll::class => PayrollPolicy::class,
            Message::class => MessagePolicy::class,
            Topic::class => TopicPolicy::class,
            Task::class => TaskPolicy::class,
            Event::class => EventPolicy::class,
        ];

        foreach ($expectedPolicies as $model => $policy) {
            $this->assertInstanceOf($policy, Gate::getPolicyFor($model));
        }
    }

    public function test_policies_are_enforced_on_existing_models(): void
    {
        $hr = $this->makeEmployee('hr');
        $finance = $this->makeEmployee('finance');
        $manager = $this->makeEmployee('department_manager');
        $employee = $this->makeEmployee('employee');
        $otherEmployee = $this->makeEmployee('employee');
        $outsider = $this->makeEmployee('employee');

        $managedDepartment = Department::create([
            'name' => 'Operations',
            'code' => 'OPS',
            'manager_id' => $manager->id,
        ]);

        $otherDepartment = Department::create([
            'name' => 'Technology',
            'code' => 'TEC',
        ]);

        $manager->update(['department_id' => $managedDepartment->id]);
        $employee->update(['department_id' => $managedDepartment->id]);
        $otherEmployee->update(['department_id' => $otherDepartment->id]);
        $outsider->update(['department_id' => $otherDepartment->id]);

        $managedPosition = Position::create([
            'title' => 'Ops Lead',
            'department_id' => $managedDepartment->id,
        ]);

        $otherPosition = Position::create([
            'title' => 'Engineer',
            'department_id' => $otherDepartment->id,
        ]);

        $shift = Shift::create([
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'shift_id' => $shift->id,
        ]);

        $otherAttendance = Attendance::create([
            'employee_id' => $otherEmployee->id,
            'date' => now()->addDay()->toDateString(),
            'shift_id' => $shift->id,
        ]);

        $leave = Leave::create([
            'employee_id' => $employee->id,
            'leave_type' => 'Vacation',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'Pending',
        ]);

        $otherLeave = Leave::create([
            'employee_id' => $otherEmployee->id,
            'leave_type' => 'Sick Leave',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'Pending',
        ]);

        $ownPayroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_date' => now()->toDateString(),
            'period' => '2026-06',
            'gross_pay' => 1000,
            'net_pay' => 900,
            'status' => 'pending',
        ]);

        $otherPayroll = Payroll::create([
            'employee_id' => $otherEmployee->id,
            'pay_date' => now()->toDateString(),
            'period' => '2026-07',
            'gross_pay' => 1200,
            'net_pay' => 1000,
            'status' => 'pending',
        ]);

        $topic = Topic::create([
            'subject' => 'Project Update',
            'creator_id' => $employee->id,
            'receiver_id' => $otherEmployee->id,
        ]);

        $message = Message::create([
            'topic_id' => $topic->id,
            'sender_id' => $employee->id,
            'receiver_id' => $otherEmployee->id,
            'content' => 'Hello team',
        ]);

        $task = Task::create([
            'title' => 'Prepare report',
            'assignee_id' => $employee->id,
            'status' => 'todo',
        ]);

        $event = Event::create([
            'title' => 'Town Hall',
            'start_time' => now(),
            'end_time' => now()->addHour(),
        ]);

        $this->assertTrue(Gate::forUser($hr)->allows('create', Shift::class));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $managedDepartment));
        $this->assertFalse(Gate::forUser($manager)->allows('view', $otherDepartment));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $managedPosition));
        $this->assertFalse(Gate::forUser($manager)->allows('view', $otherPosition));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $attendance));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $otherAttendance));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $leave));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $otherLeave));
        $this->assertTrue(Gate::forUser($finance)->allows('viewAny', Payroll::class));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $ownPayroll));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $otherPayroll));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $message));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $message));
        $this->assertTrue(Gate::forUser($otherEmployee)->allows('view', $topic));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $topic));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $task));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $task));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $event));
        $this->assertFalse(Gate::forUser($employee)->allows('create', Event::class));
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;

        $employee = Employee::create(array_merge([
            'employee_code' => sprintf('EMP-S-%03d', $sequence),
            'first_name' => 'Security',
            'last_name' => "User {$sequence}",
            'email' => sprintf('security-%03d@example.com', $sequence),
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->assignRole($role);

        return $employee;
    }
}
