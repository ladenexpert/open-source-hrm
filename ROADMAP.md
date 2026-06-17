HRMS Enterprise Roadmap
Completed

## Sprint 1
## v1.0-security-stable
- Security hardening
- RBAC & Policies
- Admin panel protection
- Portal isolation
- PHP 8.2 stabilization
- Composer reproducibility
- Local XAMPP compatibility

## Sprint 2
## v1.1-tenancy-foundation
- Tenancy Foundation
- Company management
- Branch management
- Work Location management
- Cost Center management
- Company Settings
- Subscription Plans
- Company Subscriptions
- Company-scoped models, policies, and resources

## Sprint 3
## v1.2-indonesian-hr-foundation
- Company Group (Holding/Subsidiary) foundation
- Indonesian HR Master Data
- Organization Structure Foundation
- Division management
- Job Level management
- Job Grade management
- Employment Status management
- Employment Type management
- Contract Type management
- Identity Type management
- Bank master data
- Religion master data
- Marital Status master data
- Employee Indonesian Profile fields
- Expatriate / Korean Workforce Readiness

## v1.2.1-operational-baseline
- Local demo accounts for development/testing
- EmployeeFactory implementation
- Removal of stale UserFactory
- Asia/Jakarta timezone baseline
- Operational readiness verification
- Local login verification (Admin & Employee Portal)
- migrate --seed verification
- php artisan test verification

## v1.2.2-approval-governance
- Approval Governance Foundation
- Generic Approval Workflow engine
- Approval Workflow Steps
- Approval Requests
- Approval Request Steps
- Approval Logs
- Organization Authority Service
- Approval Workflow Resolver Service
- Approval Request Service
- Approval Action Service
- Approval Inbox
- Reusable approval layer for future HR modules

## Seeded approval workflows for:

- Leave
- Attendance Correction
- Overtime
- Payroll
- Salary Change
- Mutation
- Promotion
- Demotion
- Recruitment
- Appraisal / KPI
- Employee Data Change
- Reimbursement
- Loan

## Phase 2 – Leave Management (Completed)
## Sprint 4
## v1.3.0-leave-foundation
- Leave Types
- Leave Policies
- Holiday Calendars
- Workday Patterns

## v1.3.1-leave-balance
- Leave Entitlements
- Leave Transactions
- Leave Balance Services

## v1.3.2-leave-request
- Employee Leave Portal
- Leave Request Workflow
- Attachment Support
- Leave Calculation

## v1.3.3-leave-approval-stable
- Approval Engine Integration
- Leave Approval Flow
- Balance Deduction
- Balance Restore

## v1.3.4-access-scope-hardening
- Super Admin Scope
- Company Group Scope
- Company Scope
- Portal Scope Hardening

## v1.3.5-stabilization-check
- Full Validation
- License Audit
- UAT Verification
- Phase 2 Closure

## Current Phase

## Phase 3 – Attendance Enterprise
## Sprint 5
## v1.4.0-attendance-foundation
- Attendance Policy
- Shift Pattern
- Shift Assignment
- Work Location GPS Foundation
- Attendance Resources
- Attendance Services

## v1.4.1-attendance-log
- Clock In / Clock Out
- GPS Capture
- Selfie Capture
- Raw Attendance Logs

## v1.4.2-attendance-calculation
- Late Calculation
- Early Leave Calculation
- Work Hour Calculation
- Overnight Shift Support

## v1.4.3-attendance-correction
- Attendance Correction Request
- Approval Workflow Integration

## v1.4.4-attendance-portal
- Employee Attendance Portal
- Attendance History
- Attendance Dashboard
- Future Phases

## Sprint 6
## Phase 4 – Overtime Management
- Overtime Policies
- Overtime Requests
- Overtime Approval
- Overtime Calculation

## Sprint 7
## Phase 5 – Payroll Foundation
- Payroll Periods
- Payroll Runs
- Salary Components
- Payslips

## Sprint 8
## Phase 6 – Indonesian Compliance
- BPJS
- PPh21
- THR

## Sprint 9
## Phase 7 – Employee Self Service Enhancement
- Import / Export Framework
- Bulk Operations
- Productivity Tools

## Sprint 10
## Phase 8 – Recruitment Management
## Sprint 11
## Phase 9 – Performance Management
## Sprint 12
## Phase 10 – Reimbursement & Loan
## Sprint 13
## Phase 11 – Learning Management System
## Sprint 14
## Phase 12 – SaaS Billing & Subscription Automation