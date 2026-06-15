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
Repository State

Current stable milestone:

v1.2.2-approval-governance

Repository status expectations:

Git working tree clean
All committed milestones tagged
Local login verified
migrate --seed verified
php artisan test passing
Current Stable Milestone

v1.2.2-approval-governance

Next Sprint
Sprint 4 – Leave Enterprise

Sprint 4 prerequisites already completed:

Security & RBAC
Tenancy Foundation
Indonesian HR Foundation
Operational Baseline
Approval Governance Foundation

Important principle:

Leave Enterprise MUST consume the existing Approval Governance Foundation and MUST NOT implement separate hardcoded approval logic.

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