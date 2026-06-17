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
Repository State

Current stable milestone:

v1.3.3-leave-approval-stable

Repository status expectations:

Git working tree clean
All committed milestones tagged
Local login verified
migrate --seed verified
php artisan test passing
Current Stable Milestone

v1.3.3-leave-approval-stable

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

Next Sprint
v1.4.x Attendance

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
