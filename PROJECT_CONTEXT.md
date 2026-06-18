HRMS Enterprise Project Context
Product Vision

Build an enterprise-grade HRMS comparable to Talenta and SAP SuccessFactors for Indonesian enterprises, optimized for Indonesian regulations while supporting multinational organizations, Korean expatriate workforce, multi-company structures, and future SaaS commercialization.

Tech Stack
Laravel 12
Filament 4
PHP 8.2
MySQL
XAMPP localhost
Repository Rules
Independent product
No merge, sync, pull request, or contribution back to the original upstream fork
Commit every sprint
Tag every stable milestone
Maintain PHP 8.2 compatibility
Local-first development approach

Every milestone must pass:

composer validate
composer install --dry-run
php artisan optimize
php artisan migrate --seed
php artisan test
Organizational Scope
Multi-Company Structure

Support for:

Company Groups
Holding Companies
Subsidiary Companies
Shared Services scenarios
Future SaaS multi-tenancy
Workforce Types

Support for:

Permanent Employee (PKWTT)
Fixed-Term Employee (PKWT)
Probation Employee
Outsourced Employee
Internship Employee
Daily Worker
Consultant
Director / Commissioner
Expatriate Employee
Approval Governance Scope

Approval routing must support:

Direct Supervisor
Team Leader
Department Head
HR Head
Finance Head
Company Head
Specific Employee
Role-based Approver
Job-Level-based Approver
Multi-step sequential approval

Future enhancement:

Parallel approval support
Completed Milestones
v1.0-security-stable
RBAC
Policies
Admin hardening
Portal isolation
PHP 8.2 stabilization
v1.1-tenancy-foundation
Company
Branch
Work Location
Cost Center
Company Settings
Subscription Plans
Company Subscriptions
Company-scoped models/policies/resources
v1.2-indonesian-hr-foundation
Company Group foundation
Indonesian HR master data
Organization structure foundation
Employment master data
Employee profile foundation
Expatriate readiness baseline
v1.2.1-operational-baseline
Demo users for local/testing
EmployeeFactory implementation
Asia/Jakarta timezone baseline
Operational readiness verification
v1.2.2-approval-governance
Approval workflow foundation
Approval request lifecycle
Approval logs
Approval inbox
Organization authority service
Future-proof approval architecture
v1.3.0-leave-foundation
Sprint 4A leave foundation completed
Leave type management
Leave policy management
Holiday calendar foundation
Holiday master foundation
Workday pattern foundation
Filament leave administration baseline
Seeder-backed demo leave data
Basic leave foundation tests
v1.3.1-leave-balance
Sprint 4B leave balance completed
Leave entitlement management
Leave transaction ledger
Leave balance and entitlement services
Seeder-backed entitlement generation
Leave balance validation tests
v1.3.2-leave-request
Sprint 4C leave request completed
Leave request and attachment management
Leave calculation and request orchestration services
Employee portal leave request feature
Admin leave request monitoring resource
Seeder-backed leave request demo data
Leave request validation tests
v1.3.3-leave-approval-stable
Sprint 4D leave approval completed
Leave approval workflow integration
LeaveApprovalService adapter
Approval-driven approve/reject lifecycle
Balance deduction on final approval
Balance restore on admin cancellation of approved leave
Approval inbox integration for leave requests
Employee portal approval status and history display
Leave approval notification events
Leave approval validation tests
v1.3.4-access-scope-hardening
Sprint 4D hardening completed
Global access hierarchy hardened
Employee-based authorization confirmed
Super Admin bypass preserved
Approval governance scope filters centralized
Company Admin vs Company Group Admin baseline introduced
Portal self-scope preserved
Approval inbox scope regression coverage added
v1.4.0-attendance-foundation
Phase 3 attendance foundation completed
Attendance policy foundation
Shift pattern and shift assignment foundation
Employee schedule exception foundation
GPS-ready work location schema
Attendance resolver services
Attendance admin resources
Attendance foundation regression coverage added
v1.4.3-attendance-correction
Attendance correction workflow completed
AttendanceCorrection model implemented
AttendanceCorrectionService implemented
AttendanceCorrectionResource implemented
Employee portal correction request implemented
Approval governance integration implemented
Attendance correction overlay mode implemented
Attendance summary recalculation after approval implemented
Raw attendance logs remain immutable
Attendance summaries remain rebuildable calculated snapshots
Repository State

Current stable milestone:

v1.4.3-attendance-correction

Repository status expectations:

Git working tree clean
All committed milestones tagged
Local login verified
migrate --seed verified
php artisan test passing
Current Stable Milestone

v1.4.3-attendance-correction

Current test baseline:
260 tests
617 assertions
No regressions

## License Hygiene Policy

This product targets commercial SaaS distribution under an MIT-licensed stack.

All dependencies must remain compatible with commercial use.

### Allowed licenses

- MIT
- Apache-2.0
- BSD-2-Clause
- BSD-3-Clause
- ISC
- Dual-licensed packages where MIT or BSD is available

### Prohibited licenses

- GPL v2 / GPL v3 (unless dev-only dependency with no production impact)
- AGPL v3
- BUSL
- SSPL
- Proprietary commercial licenses without explicit approval

### Process for adding new packages

1. Verify package license before composer require
2. If license is not allowed: STOP
3. Document package and license in Known Issues
4. Wait for repository owner approval
5. Run composer licenses after any composer require

### Audit history

- v1.3.5: Initial audit - all packages MIT / Apache / BSD / ISC

One noted exception:

nette/* packages are dual-licensed BSD-3-Clause + GPL.

The project uses BSD-3-Clause terms only and those packages are present as development dependencies with no production deployment impact.

Sprint 4A Completion

Milestone name:
v1.3.0-leave-foundation

Tables added:
leave_types
leave_policies
holiday_calendars
holidays
workday_patterns
workday_pattern_days

Models added:
LeaveType
LeavePolicy
HolidayCalendar
Holiday
WorkdayPattern
WorkdayPatternDay

Resources added:
LeaveTypeResource
LeavePolicyResource
HolidayCalendarResource
HolidayResource
WorkdayPatternResource

Seeders added:
LeaveFoundationSeeder

Tests added:
LeaveFoundationSprint4ATest

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed

Next planned sprint:
Sprint 4B - Leave Balance

Sprint 4B Completion

Milestone candidate:
v1.3.1-leave-balance

Tables added:
leave_entitlements
leave_transactions

Models added:
LeaveEntitlement
LeaveTransaction

Services added:
LeaveBalanceService
LeaveEntitlementService

Resources added:
LeaveEntitlementResource
LeaveTransactionResource

Seeders added:
LeaveBalanceSeeder

Tests added:
LeaveBalanceSprint4BTest

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed

Next planned sprint:
Sprint 4C - Leave Request

Sprint 4C Completion

Milestone candidate:
v1.3.2-leave-request

Tables added:
leave_requests
leave_request_attachments

Models added:
LeaveRequest
LeaveRequestAttachment

Services added:
LeaveCalculationService
LeaveRequestService

Resources added:
LeaveRequestResource
Employee portal leave request feature

Seeders added:
LeaveRequestSeeder

Tests added:
LeaveRequestSprint4CTest

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize - passed
php artisan migrate --seed - passed
php artisan test - passed

Next planned sprint:
Sprint 4D - Leave Approval Stable

Sprint 4D Completion

Milestone candidate:
v1.3.3-leave-approval-stable

Services added:
LeaveApprovalService

Workflow updates:
LeaveRequestService submit approval initiation
LeaveRequestService approve reject and cancelApproved flows
Approval inbox leave integration
Employee portal approval history display

Balance updates:
Final approval triggers LEAVE_TAKEN transaction
Admin cancellation of approved leave triggers RESTORE transaction
Leave balance mutations remain centralized in LeaveBalanceService

Events added:
LeaveRequestSubmitted
LeaveRequestApproved
LeaveRequestRejected
LeaveRequestCancelled

Architecture notes:
LeaveApprovalService is a thin adapter over the approval governance engine
Leave requests link to approval requests through the existing polymorphic approvable relation
Approval engine supports multi-step sequential approval
Leave transaction types include LEAVE_TAKEN and RESTORE as string-backed values

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize - passed
php artisan migrate --seed - passed
php artisan test - passed

Next planned sprint:
v1.4.x Attendance

Sprint 4D-Hardening Completion

Milestone candidate:
v1.3.4-access-scope-hardening

Global access hierarchy:
Super Admin / Platform Owner:
Can access all tenants, company groups, companies, and module data without company_id or company_group_id restrictions.
Company Group Admin:
Can access and maintain records within the assigned company group and its companies.
Company Admin:
Can access and maintain records within the assigned company only unless a workflow or role explicitly grants broader approval responsibility.
Employee / Portal User:
Can access only self-service and self-owned records.

Discovery findings summary:
Employee is the authenticated actor across admin and portal panels; no User-based authorization architecture is used.
Super Admin bypass is implemented through Employee::isSuperAdmin() and BasePolicy::before().
Company group wide access was not previously explicit in Employee helpers; several approval queries inferred broader scope directly from company_group_id.
Company Admin and Company Group Admin were not explicitly separated in seeded roles or reusable access helpers.
Portal leave queries were already correctly self-scoped through LeaveRequest::scopeForEmployee().
Approval inbox, approval request resource, approval log resource, and approval workflow resource contained duplicated inline company/group scope logic.
No custom app/Http/Middleware scoping layer currently participates in this hierarchy; scope enforcement lives in Employee helpers, policies, Filament resources, and approval services.
Shared scoped master data remains intentionally manageable by HR master-data actors within the same company group.

Files reviewed:
app/Policies/BasePolicy.php
app/Models/Employee.php
app/Providers/Filament/AdminPanelProvider.php
app/Providers/Filament/EmployeePanelProvider.php
app/Policies/
app/Filament/Resources/
app/Filament/Employee/Resources/LeaveRequests/LeaveRequestResource.php
app/Filament/Pages/MyApprovalInbox.php
app/Services/ApprovalActionService.php
app/Services/ApprovalRequestService.php
app/Services/OrganizationAuthorityService.php
app/Support/ApprovalRoleMap.php
app/Support/OrganizationScope.php
database/seeders/RolePermissionSeeder.php
tests/Feature/

Files modified:
app/Policies/BasePolicy.php
app/Models/Employee.php
app/Filament/Pages/MyApprovalInbox.php
app/Filament/Resources/ApprovalLogs/ApprovalLogResource.php
app/Filament/Resources/ApprovalRequests/ApprovalRequestResource.php
app/Filament/Resources/ApprovalWorkflows/ApprovalWorkflowResource.php
app/Services/ApprovalRequestService.php
app/Support/ApprovalRoleMap.php
app/Support/OrganizationScope.php
database/seeders/RolePermissionSeeder.php
PROJECT_CONTEXT.md

Files created:
tests/Feature/AccessScopeHardeningTest.php

Tests added or updated:
AccessScopeHardeningTest added
LeaveApprovalSprint4DTest regression scenarios revalidated for approval actions and inbox scope
SprintThreeFoundationTest revalidated for shared master-data scope

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize - passed
php artisan migrate --seed - passed
php artisan test - passed (151 tests, 338 assertions)

Sprint 4D-Stabilization Check Completion

Milestone candidate:
v1.3.5-stabilization-check

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed (151 tests, 338 assertions)

Attendance Foundation Completion

Milestone candidate:
v1.4.0-attendance-foundation

Tables added:
attendance_policies
shift_patterns
shift_pattern_details
shift_assignments
employee_schedules

Schema updates:
work_locations.latitude
work_locations.longitude
work_locations.radius_meters
employees.attendance_policy_id
employees.attendance_location_mode_override
companies.default_attendance_policy_id
companies.default_shift_pattern_id

Models added:
AttendancePolicy
ShiftPattern
ShiftPatternDetail
ShiftAssignment
EmployeeSchedule

Services added:
AttendancePolicyResolverService
ShiftResolverService
ShiftResolutionResult

Resources added:
AttendancePolicyResource
ShiftPatternResource
ShiftAssignmentResource

Seeders added:
AttendanceFoundationSeeder

Tests added:
AttendanceFoundationV140Test

Phase 3 ADR baseline:
ADR-1 Employee override > Department > Branch > Company default shift priority is locked.
ADR-2 Employee override > AttendancePolicy > fixed location-mode resolution is locked.
ADR-3 Overnight shifts are supported when end_time is earlier than start_time. Equal start and end time is invalid and must be rejected.
ADR-4 Work locations are now GPS-ready through nullable latitude, longitude, and radius_meters columns.
ADR-5 Fixed, flexible, and scheduled location strategies are preserved for later logging and validation sprints.
ADR-6 Office and manufacturing deployments share the same configurable attendance schema.
ADR-7 Attendance policy resolution order is employee policy > company default policy > null.
ADR-8 WorkdayPattern and ShiftPattern remain separate concerns.
ADR-9 Shift assignments remain period-based and are not materialized per employee per day.
ADR-10 Companies can maintain multiple attendance policies.
ADR-11 ShiftPatternDetail stores per-day working rules and hours.
ADR-12 EmployeeSchedule is exception-only and does not replace assignment-based scheduling.

Day of week convention:
Attendance foundation follows the repository's existing WorkdayPatternDay convention of 1=Monday through 7=Sunday.

Implementation notes:
Employee->department() uses department_id and Employee->branch() uses branch_id for the shift cascade.
Shift assignment overlap validation is enforced at the model layer and surfaced as validation errors before persistence.
Shift resolution returns null when no schedule, assignment, or company default applies; that null case is intentionally deferred to attendance logging behavior in v1.4.1.

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed (178 tests, 386 assertions)

Attendance Log Completion

Milestone candidate:
v1.4.1-attendance-log

Tables added:
attendance_logs

Models added:
AttendanceLog

Services added:
AttendanceLogService
AttendanceLocationValidationService

Resources added:
AttendanceLogResource
Employee portal attendance clock capability

Architecture notes:
Attendance logs are event-based raw records with one row per clock event.
Raw attendance logs are immutable through policy and admin UI.
Source tracking is stored on each log entry.
GPS latitude and longitude are stored as snapshot fields on the raw log.
Selfie path is prepared as a nullable snapshot field on the raw log.
Invalid location attempts are still stored for audit with is_valid=false and validation_message.
No late, early out, absent, work duration, overtime, or payroll calculation is implemented in this sprint.

Tests added:
AttendanceLogV141Test

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed (200 tests, 451 assertions)

Attendance Calculation Completion

Milestone candidate:
v1.4.2-attendance-calculation

Tables added:
attendance_summaries

Models added:
AttendanceSummary

Services added:
AttendanceCalculationService

Resources added:
AttendanceSummaryResource
Employee portal attendance summary view

Architecture notes:
attendance_summaries is the rebuildable daily snapshot layer for enterprise attendance calculation.
Raw AttendanceLog records remain the source event stream and are not mutated during calculation.
Attendance summaries are recalculated from attendance_logs, shift scheduling, attendance policies, approved leave, holidays, and workday patterns.
Daily status enum is locked to present, late, early_out, absent, holiday, weekend, leave, incomplete, and no_schedule.
Status priority is leave > holiday > weekend > no_schedule > absent > incomplete > late > early_out > present.
If both late and early_out apply on the same day, status resolves to late while early_out_minutes remains populated.
Overnight shifts are calculated as a single scheduled workday using the scheduled shift date.
Late and early-out tolerance use AttendancePolicy minutes without introducing overtime logic.
Invalid raw logs are ignored for actual_in_at and actual_out_at calculation but remain stored for audit and are noted on the summary.
No correction workflow, overtime calculation, payroll integration, or monthly lock is implemented in this sprint.
Legacy Attendance remains in place for coexistence; enterprise daily calculation is implemented separately through AttendanceSummary.

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed (230 tests, 533 assertions)

Attendance Correction Completion

Milestone candidate:
v1.4.3-attendance-correction

Models added:
AttendanceCorrection

Services added:
AttendanceCorrectionService

Resources added:
AttendanceCorrectionResource
Employee portal correction request capability

Architecture notes:
AttendanceLog
    ->
AttendanceCorrection (approved overlay)
    ->
AttendanceCalculationService
    ->
AttendanceSummary
AttendanceLog remains the immutable audit trail for raw clock events.
AttendanceCorrection acts as the approved adjustment layer and never overwrites raw logs.
AttendanceSummary remains a rebuildable calculated snapshot.
Approved corrections trigger recalculation through AttendanceCalculationService.
Raw attendance logs are never overwritten or backfilled by correction records.

Validation result:
composer validate - passed
composer install --dry-run - passed
php artisan optimize:clear - passed
php artisan migrate:fresh --seed - passed
php artisan test - passed (260 tests, 617 assertions)

License audit:
Clean
All packages verified as MIT / Apache / BSD / ISC compatible for commercial SaaS use.
nette/* remains the only noted dual-licensed exception and is used under BSD-3-Clause terms as a development-only dependency.

Schema deferral:
work_locations now contains latitude, longitude, and radius_meters.
GPS-ready attendance location support is available for Phase 3 attendance work.

Phase transition confirmation:
Phase 2 Leave Management is confirmed complete across v1.3.0 through v1.3.5.
Phase 3 Attendance Enterprise is active on the completed v1.4.3-attendance-correction baseline.

Known issues or intentional deferrals:
No new permission framework was introduced; hardening stays within the existing Employee, policy, Filament resource, and approval-service architecture.
Shared group-scoped HR master data remains intentionally available to same-group HR master-data managers.
Approval workflow business rules were not rewritten; this sprint only hardened reusable access-scope boundaries and role detection.
Browser geolocation capture is not yet implemented in the portal UI, but the raw logging service supports GPS payloads.
Selfie upload is not yet implemented in the portal UI, but selfie_path is supported on the raw log model and service payload.
Legacy Attendance remains in place for coexistence; raw enterprise attendance logging is implemented separately in AttendanceLog.
Shift resolution intentionally returns null when no employee schedule, assignment, or company default applies; raw attendance logging stores that state without introducing calculation.
WorkdayPatternDay uses the existing 1=Monday through 7=Sunday convention, so shift_pattern_details matches that internal convention instead of the originally proposed 0=Sunday through 6=Saturday format.
Half-day leave is already modeled in LeaveRequest, but attendance summary leave override remains limited to approved full-day leave handling in v1.4.2.
Holiday and weekend resolution uses the existing company-scoped active holiday calendar and active/default workday pattern; no employee-specific calendar assignment layer has been introduced yet.
No overtime calculation is implemented yet.
No payroll integration is implemented yet.
Approved correction reversal remains deferred to a future sprint.
Bulk correction remains deferred.
Monthly attendance lock remains deferred.

Next planned phase:
Phase 3 - Attendance Enterprise (v1.4.4 Attendance Portal Enhancement is next)

Next Sprint
v1.4.4-attendance-portal-enhancement

Roadmap Update

Sprint 5 Attendance Enterprise
✅ v1.4.0 Attendance Foundation
✅ v1.4.1 Attendance Log
✅ v1.4.2 Attendance Calculation
✅ v1.4.3 Attendance Correction
⏳ v1.4.4 Attendance Portal Enhancement

Phase 2 Complete
v1.3.0 Leave Foundation
v1.3.1 Leave Balance
v1.3.2 Leave Request
v1.3.3 Leave Approval Stable
v1.3.4 Access Scope Hardening
v1.3.5 Stabilization Check

Next Phase
v1.4.4 Attendance Portal Enhancement

Next Planned Milestone:
v1.4.4-attendance-portal-enhancement

Sprint 4 prerequisites already completed:

Security & RBAC
Tenancy Foundation
Indonesian HR Foundation
Operational Baseline
Approval Governance Foundation

Important principle:

Leave Enterprise MUST consume the existing Approval Governance Foundation and MUST NOT implement separate hardcoded approval logic.
Leave approval orchestration now flows through LeaveApprovalService as the adapter between leave and the approval engine.

Local Demo Credentials

For local, development, and testing environments only.

DemoUserSeeder runs only in local and testing environments.

Admin Panel

Email:
admin@hrms.local

Password:
Password123!

Role:
super_admin

Employee Portal

Email:
employee@hrms.local

Password:
Password123!

Role:
employee

These credentials must never be exposed in production environments.

Long-Term Goal

Deliver an enterprise HRMS platform capable of evolving into a multi-tenant SaaS solution comparable to Talenta while maintaining:

Indonesian regulatory compliance
Enterprise scalability
Strong approval governance
Multi-company capability
Expatriate readiness
Excellent user experience
Modular architecture
Backward compatibility between milestones
Clear separation between security, approval, and business domains
