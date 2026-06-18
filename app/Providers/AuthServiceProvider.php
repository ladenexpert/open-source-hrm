<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy as AttendancePolicyModel;
use App\Models\AttendanceSummary;
use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\CompanySetting;
use App\Models\CompanySubscription;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\EmployeeSchedule;
use App\Models\Event;
use App\Models\IdentityType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Leave;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\LeavePolicy as LeavePolicyModel;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\MaritalStatus;
use App\Models\Message;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\Religion;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\SubscriptionPlan;
use App\Models\Task;
use App\Models\Topic;
use App\Models\WorkdayPattern;
use App\Models\WorkLocation;
use App\Models\Bank;
use App\Models\ContractType;
use App\Policies\AttendancePolicy as AttendanceRecordPolicy;
use App\Policies\AttendanceLogPolicy;
use App\Policies\AttendancePolicyPolicy;
use App\Policies\AttendanceSummaryPolicy;
use App\Policies\ApprovalLogPolicy;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\ApprovalWorkflowPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CompanyGroupPolicy;
use App\Policies\CompanySettingPolicy;
use App\Policies\CompanySubscriptionPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EmployeeSchedulePolicy;
use App\Policies\EventPolicy;
use App\Policies\HolidayCalendarPolicy;
use App\Policies\HolidayPolicy;
use App\Policies\LeavePolicy;
use App\Policies\LeaveEntitlementPolicy;
use App\Policies\LeavePolicyRecordPolicy;
use App\Policies\LeaveRequestAttachmentPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\LeaveTransactionPolicy;
use App\Policies\LeaveTypePolicy;
use App\Policies\MessagePolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PositionPolicy;
use App\Policies\ScopedMasterDataPolicy;
use App\Policies\ShiftPolicy;
use App\Policies\ShiftAssignmentPolicy;
use App\Policies\ShiftPatternPolicy;
use App\Policies\SubscriptionPlanPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TopicPolicy;
use App\Policies\WorkdayPatternPolicy;
use App\Policies\WorkLocationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Company::class => CompanyPolicy::class,
        CompanyGroup::class => CompanyGroupPolicy::class,
        Branch::class => BranchPolicy::class,
        WorkLocation::class => WorkLocationPolicy::class,
        CostCenter::class => CostCenterPolicy::class,
        Division::class => ScopedMasterDataPolicy::class,
        JobLevel::class => ScopedMasterDataPolicy::class,
        JobGrade::class => ScopedMasterDataPolicy::class,
        EmploymentStatus::class => ScopedMasterDataPolicy::class,
        EmploymentType::class => ScopedMasterDataPolicy::class,
        ContractType::class => ScopedMasterDataPolicy::class,
        IdentityType::class => ScopedMasterDataPolicy::class,
        Bank::class => ScopedMasterDataPolicy::class,
        Religion::class => ScopedMasterDataPolicy::class,
        MaritalStatus::class => ScopedMasterDataPolicy::class,
        CompanySetting::class => CompanySettingPolicy::class,
        SubscriptionPlan::class => SubscriptionPlanPolicy::class,
        CompanySubscription::class => CompanySubscriptionPolicy::class,
        Employee::class => EmployeePolicy::class,
        Department::class => DepartmentPolicy::class,
        Position::class => PositionPolicy::class,
        Shift::class => ShiftPolicy::class,
        Attendance::class => AttendanceRecordPolicy::class,
        AttendanceLog::class => AttendanceLogPolicy::class,
        AttendancePolicyModel::class => AttendancePolicyPolicy::class,
        AttendanceSummary::class => AttendanceSummaryPolicy::class,
        ApprovalWorkflow::class => ApprovalWorkflowPolicy::class,
        ApprovalRequest::class => ApprovalRequestPolicy::class,
        ApprovalLog::class => ApprovalLogPolicy::class,
        Leave::class => LeavePolicy::class,
        LeaveType::class => LeaveTypePolicy::class,
        LeavePolicyModel::class => LeavePolicyRecordPolicy::class,
        LeaveEntitlement::class => LeaveEntitlementPolicy::class,
        LeaveTransaction::class => LeaveTransactionPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        LeaveRequestAttachment::class => LeaveRequestAttachmentPolicy::class,
        EmployeeSchedule::class => EmployeeSchedulePolicy::class,
        HolidayCalendar::class => HolidayCalendarPolicy::class,
        Holiday::class => HolidayPolicy::class,
        WorkdayPattern::class => WorkdayPatternPolicy::class,
        ShiftPattern::class => ShiftPatternPolicy::class,
        ShiftAssignment::class => ShiftAssignmentPolicy::class,
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
