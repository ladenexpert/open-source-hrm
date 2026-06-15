<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('company_group_id')->nullable()->after('id')->constrained('company_groups')->nullOnDelete();
            $table->foreignId('parent_company_id')->nullable()->after('company_group_id')->constrained('companies')->nullOnDelete();
            $table->string('company_type')->nullable()->after('tax_id');
            $table->boolean('is_legal_entity')->default(true)->after('company_type');
        });

        $this->createScopedMasterTable('job_levels');
        $this->createScopedMasterTable('job_grades');
        $this->createScopedMasterTable('employment_statuses');
        $this->createScopedMasterTable('employment_types');
        $this->createScopedMasterTable('contract_types');
        $this->createScopedMasterTable('identity_types');
        $this->createScopedMasterTable('banks');
        $this->createScopedMasterTable('religions');
        $this->createScopedMasterTable('marital_statuses');

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('company_group_id')->nullable()->constrained('company_groups')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['company_group_id', 'company_id', 'code'], 'divisions_scope_code_unique');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('company_group_id')->nullable()->after('company_id')->constrained('company_groups')->nullOnDelete();
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->after('department_id')->constrained('divisions')->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->after('division_id')->constrained('job_levels')->nullOnDelete();
            $table->foreignId('job_grade_id')->nullable()->after('job_level_id')->constrained('job_grades')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('salary');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('company_group_id')->nullable()->after('company_id')->constrained('company_groups')->nullOnDelete();
            $table->string('full_name')->nullable()->after('employee_code');
            $table->string('nik_ktp')->nullable()->after('national_id');
            $table->string('npwp_number')->nullable()->after('nik_ktp');
            $table->string('passport_number')->nullable()->after('npwp_number');
            $table->string('kitas_kitap_number')->nullable()->after('passport_number');
            $table->string('bpjs_kesehatan_number')->nullable()->after('kitas_kitap_number');
            $table->string('bpjs_ketenagakerjaan_number')->nullable()->after('bpjs_kesehatan_number');
            $table->foreignId('bank_id')->nullable()->after('cost_center_id')->constrained('banks')->nullOnDelete();
            $table->string('bank_account_number')->nullable()->after('bank_id');
            $table->string('bank_account_holder_name')->nullable()->after('bank_account_number');
            $table->foreignId('division_id')->nullable()->after('department_id')->constrained('divisions')->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->after('position_id')->constrained('job_levels')->nullOnDelete();
            $table->foreignId('job_grade_id')->nullable()->after('job_level_id')->constrained('job_grades')->nullOnDelete();
            $table->foreignId('employment_status_id')->nullable()->after('employment_type')->constrained('employment_statuses')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->after('employment_status_id')->constrained('employment_types')->nullOnDelete();
            $table->foreignId('contract_type_id')->nullable()->after('employment_type_id')->constrained('contract_types')->nullOnDelete();
            $table->foreignId('identity_type_id')->nullable()->after('contract_type_id')->constrained('identity_types')->nullOnDelete();
            $table->foreignId('religion_id')->nullable()->after('identity_type_id')->constrained('religions')->nullOnDelete();
            $table->foreignId('marital_status_id')->nullable()->after('religion_id')->constrained('marital_statuses')->nullOnDelete();
            $table->date('join_date')->nullable()->after('hire_date');
            $table->date('probation_end_date')->nullable()->after('join_date');
            $table->date('contract_start_date')->nullable()->after('probation_end_date');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->date('resign_date')->nullable()->after('contract_end_date');
            $table->foreignId('direct_supervisor_id')->nullable()->after('resign_date')->constrained('employees')->nullOnDelete();
            $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_phone');
            $table->string('nationality')->nullable()->after('emergency_contact_relation');
            $table->string('citizenship_status')->nullable()->after('nationality');
            $table->string('expatriate_status')->nullable()->after('citizenship_status');
            $table->string('visa_type')->nullable()->after('expatriate_status');
            $table->string('visa_number')->nullable()->after('visa_type');
            $table->date('visa_issued_date')->nullable()->after('visa_number');
            $table->date('visa_expiry_date')->nullable()->after('visa_issued_date');
            $table->string('work_permit_number')->nullable()->after('visa_expiry_date');
            $table->date('work_permit_expiry_date')->nullable()->after('work_permit_number');
            $table->date('passport_expiry_date')->nullable()->after('work_permit_expiry_date');
            $table->string('home_country')->nullable()->after('passport_expiry_date');
            $table->string('home_company')->nullable()->after('home_country');
            $table->foreignId('host_company_id')->nullable()->after('home_company')->constrained('companies')->nullOnDelete();
            $table->date('assignment_start_date')->nullable()->after('host_company_id');
            $table->date('assignment_end_date')->nullable()->after('assignment_start_date');
            $table->string('payroll_scheme')->nullable()->after('assignment_end_date');
            $table->string('tax_residency_status')->nullable()->after('payroll_scheme');
            $table->boolean('bpjs_eligible')->default(true)->after('tax_residency_status');
            $table->boolean('thr_eligible')->default(true)->after('bpjs_eligible');
        });

        $defaultGroupId = $this->ensureDefaultGroup();

        DB::table('companies')->whereNull('company_group_id')->update([
            'company_group_id' => $defaultGroupId,
        ]);

        DB::table('companies')
            ->whereNull('company_type')
            ->update([
                'company_type' => 'holding',
                'is_legal_entity' => true,
            ]);

        DB::table('departments as departments')
            ->leftJoin('companies', 'departments.company_id', '=', 'companies.id')
            ->whereNull('departments.company_group_id')
            ->update([
                'departments.company_group_id' => DB::raw('companies.company_group_id'),
            ]);

        DB::table('employees as employees')
            ->leftJoin('companies', 'employees.company_id', '=', 'companies.id')
            ->update([
                'employees.company_group_id' => DB::raw('companies.company_group_id'),
                'employees.full_name' => DB::raw("NULLIF(TRIM(CONCAT(COALESCE(employees.first_name, ''), ' ', COALESCE(employees.last_name, ''))), '')"),
                'employees.employee_code' => DB::raw("CASE WHEN employees.employee_code IS NULL OR employees.employee_code = '' THEN CONCAT('EMP-', LPAD(employees.id, 5, '0')) ELSE employees.employee_code END"),
                'employees.join_date' => DB::raw('COALESCE(employees.join_date, employees.hire_date)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('host_company_id');
            $table->dropColumn([
                'home_company',
                'home_country',
                'passport_expiry_date',
                'work_permit_expiry_date',
                'work_permit_number',
                'visa_expiry_date',
                'visa_issued_date',
                'visa_number',
                'visa_type',
                'expatriate_status',
                'citizenship_status',
                'nationality',
                'emergency_contact_relation',
            ]);
            $table->dropConstrainedForeignId('direct_supervisor_id');
            $table->dropColumn([
                'resign_date',
                'contract_end_date',
                'contract_start_date',
                'probation_end_date',
                'join_date',
            ]);
            $table->dropConstrainedForeignId('marital_status_id');
            $table->dropConstrainedForeignId('religion_id');
            $table->dropConstrainedForeignId('identity_type_id');
            $table->dropConstrainedForeignId('contract_type_id');
            $table->dropConstrainedForeignId('employment_type_id');
            $table->dropConstrainedForeignId('employment_status_id');
            $table->dropConstrainedForeignId('job_grade_id');
            $table->dropConstrainedForeignId('job_level_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn([
                'bank_account_holder_name',
                'bank_account_number',
            ]);
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn([
                'bpjs_ketenagakerjaan_number',
                'bpjs_kesehatan_number',
                'kitas_kitap_number',
                'passport_number',
                'npwp_number',
                'nik_ktp',
                'full_name',
                'payroll_scheme',
                'tax_residency_status',
                'bpjs_eligible',
                'thr_eligible',
                'assignment_start_date',
                'assignment_end_date',
            ]);
            $table->dropConstrainedForeignId('company_group_id');
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->dropColumn('is_active');
            $table->dropConstrainedForeignId('job_grade_id');
            $table->dropConstrainedForeignId('job_level_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_group_id');
        });

        Schema::dropIfExists('divisions');
        Schema::dropIfExists('marital_statuses');
        Schema::dropIfExists('religions');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('identity_types');
        Schema::dropIfExists('contract_types');
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('employment_statuses');
        Schema::dropIfExists('job_grades');
        Schema::dropIfExists('job_levels');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('is_legal_entity');
            $table->dropColumn('company_type');
            $table->dropConstrainedForeignId('parent_company_id');
            $table->dropConstrainedForeignId('company_group_id');
        });

        Schema::dropIfExists('company_groups');
    }

    private function createScopedMasterTable(string $tableName): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('company_group_id')->nullable()->constrained('company_groups')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['company_group_id', 'company_id', 'code'], "{$tableName}_scope_code_unique");
        });
    }

    private function ensureDefaultGroup(): int
    {
        $existingGroup = DB::table('company_groups')->where('code', 'DEFAULT-GROUP')->first();

        if ($existingGroup) {
            return (int) $existingGroup->id;
        }

        return (int) DB::table('company_groups')->insertGetId([
            'code' => 'DEFAULT-GROUP',
            'name' => 'Default Company Group',
            'legal_name' => 'Default Company Group',
            'description' => 'Starter holding structure for the default tenant.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
