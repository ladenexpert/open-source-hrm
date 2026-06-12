<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('marital_status')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->after('branch_id')->constrained('work_locations')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->after('work_location_id')->constrained('cost_centers')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('manager_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('leave', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('topics', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
        });

        $defaultCompanyId = $this->ensureDefaultCompany();

        DB::table('employees')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
        DB::table('shifts')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
        DB::table('events')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);

        DB::table('departments as departments')
            ->leftJoin('employees as managers', 'departments.manager_id', '=', 'managers.id')
            ->whereNull('departments.company_id')
            ->update([
                'departments.company_id' => DB::raw("COALESCE(managers.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('positions as positions')
            ->leftJoin('departments', 'positions.department_id', '=', 'departments.id')
            ->whereNull('positions.company_id')
            ->update([
                'positions.company_id' => DB::raw("COALESCE(departments.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('attendances as attendances')
            ->leftJoin('employees', 'attendances.employee_id', '=', 'employees.id')
            ->whereNull('attendances.company_id')
            ->update([
                'attendances.company_id' => DB::raw("COALESCE(employees.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('leave as leave_requests')
            ->leftJoin('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->whereNull('leave_requests.company_id')
            ->update([
                'leave_requests.company_id' => DB::raw("COALESCE(employees.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('payrolls')
            ->leftJoin('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->whereNull('payrolls.company_id')
            ->update([
                'payrolls.company_id' => DB::raw("COALESCE(employees.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('topics')
            ->leftJoin('employees as creators', 'topics.creator_id', '=', 'creators.id')
            ->whereNull('topics.company_id')
            ->update([
                'topics.company_id' => DB::raw("COALESCE(creators.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('messages')
            ->leftJoin('topics', 'messages.topic_id', '=', 'topics.id')
            ->leftJoin('employees as senders', 'messages.sender_id', '=', 'senders.id')
            ->whereNull('messages.company_id')
            ->update([
                'messages.company_id' => DB::raw("COALESCE(topics.company_id, senders.company_id, {$defaultCompanyId})"),
            ]);

        DB::table('tasks')
            ->leftJoin('employees as assignees', 'tasks.assignee_id', '=', 'assignees.id')
            ->whereNull('tasks.company_id')
            ->update([
                'tasks.company_id' => DB::raw("COALESCE(assignees.company_id, {$defaultCompanyId})"),
            ]);

        DB::statement('ALTER TABLE departments DROP INDEX departments_name_unique');
        DB::statement('ALTER TABLE departments DROP INDEX departments_code_unique');
        Schema::table('departments', function (Blueprint $table): void {
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
        });

        DB::statement('ALTER TABLE positions DROP INDEX positions_code_unique');
        Schema::table('positions', function (Blueprint $table): void {
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'code']);
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name']);
            $table->dropUnique(['company_id', 'code']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });

        DB::statement('ALTER TABLE departments ADD UNIQUE departments_name_unique (name)');
        DB::statement('ALTER TABLE departments ADD UNIQUE departments_code_unique (code)');
        DB::statement('ALTER TABLE positions ADD UNIQUE positions_code_unique (code)');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropConstrainedForeignId('work_location_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });

        foreach (['shifts', 'attendances', 'leave', 'payrolls', 'topics', 'messages', 'tasks', 'events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }

    private function ensureDefaultCompany(): int
    {
        $company = DB::table('companies')->where('code', Company::DEFAULT_CODE)->first();

        if ($company) {
            return (int) $company->id;
        }

        return (int) DB::table('companies')->insertGetId([
            'code' => Company::DEFAULT_CODE,
            'name' => 'Default Company',
            'legal_name' => 'Default Company',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
