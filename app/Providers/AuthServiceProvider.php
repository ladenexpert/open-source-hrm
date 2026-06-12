<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanySubscription;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Leave;
use App\Models\Message;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\Shift;
use App\Models\SubscriptionPlan;
use App\Models\Task;
use App\Models\Topic;
use App\Models\WorkLocation;
use App\Policies\AttendancePolicy;
use App\Policies\BranchPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CompanySettingPolicy;
use App\Policies\CompanySubscriptionPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EventPolicy;
use App\Policies\LeavePolicy;
use App\Policies\MessagePolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PositionPolicy;
use App\Policies\ShiftPolicy;
use App\Policies\SubscriptionPlanPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TopicPolicy;
use App\Policies\WorkLocationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Branch::class => BranchPolicy::class,
        WorkLocation::class => WorkLocationPolicy::class,
        CostCenter::class => CostCenterPolicy::class,
        CompanySetting::class => CompanySettingPolicy::class,
        SubscriptionPlan::class => SubscriptionPlanPolicy::class,
        CompanySubscription::class => CompanySubscriptionPolicy::class,
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

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
