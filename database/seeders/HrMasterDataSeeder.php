<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\Division;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\IdentityType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\MaritalStatus;
use App\Models\Religion;
use Illuminate\Database\Seeder;

class HrMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $companyGroup = CompanyGroup::findOrCreateDefault();
        $holdingCompany = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $this->seedRecords(EmploymentStatus::class, [
            ['code' => 'ACTIVE', 'name' => 'Active', 'sort_order' => 1],
            ['code' => 'PROBATION', 'name' => 'Probation', 'sort_order' => 2],
            ['code' => 'CONTRACT_ENDED', 'name' => 'Contract Ended', 'sort_order' => 3],
            ['code' => 'RESIGNED', 'name' => 'Resigned', 'sort_order' => 4],
            ['code' => 'TERMINATED', 'name' => 'Terminated', 'sort_order' => 5],
            ['code' => 'SUSPENDED', 'name' => 'Suspended', 'sort_order' => 6],
            ['code' => 'INACTIVE', 'name' => 'Inactive', 'sort_order' => 7],
        ]);

        $this->seedRecords(EmploymentType::class, [
            ['code' => 'PKWTT', 'name' => 'PKWTT / Permanent', 'sort_order' => 1],
            ['code' => 'PKWT', 'name' => 'PKWT / Contract', 'sort_order' => 2],
            ['code' => 'OUTSOURCE', 'name' => 'Outsource', 'sort_order' => 3],
            ['code' => 'INTERN', 'name' => 'Internship / Intern', 'sort_order' => 4],
            ['code' => 'EXPATRIATE', 'name' => 'Expatriate', 'sort_order' => 5],
            ['code' => 'DAILY_WORKER', 'name' => 'Daily Worker / Harian Lepas', 'sort_order' => 6],
            ['code' => 'CONSULTANT', 'name' => 'Consultant', 'sort_order' => 7],
            ['code' => 'PROBATION_WORKER', 'name' => 'Probation', 'sort_order' => 8],
            ['code' => 'SECONDEE', 'name' => 'Secondee', 'sort_order' => 9],
        ]);

        $this->seedRecords(ContractType::class, [
            ['code' => 'PERMANENT', 'name' => 'Permanent', 'sort_order' => 1],
            ['code' => 'FIXED_TERM', 'name' => 'Fixed Term', 'sort_order' => 2],
            ['code' => 'OUTSOURCE_AGREEMENT', 'name' => 'Outsource Agreement', 'sort_order' => 3],
            ['code' => 'INTERNSHIP_AGREEMENT', 'name' => 'Internship Agreement', 'sort_order' => 4],
            ['code' => 'EXPAT_ASSIGNMENT', 'name' => 'Expatriate Assignment', 'sort_order' => 5],
            ['code' => 'DAILY_WORKER', 'name' => 'Daily Worker', 'sort_order' => 6],
            ['code' => 'CONSULTANT_AGREEMENT', 'name' => 'Consultant Agreement', 'sort_order' => 7],
        ]);

        $this->seedRecords(IdentityType::class, [
            ['code' => 'KTP', 'name' => 'KTP', 'sort_order' => 1],
            ['code' => 'SIM', 'name' => 'SIM', 'sort_order' => 2],
            ['code' => 'PASSPORT', 'name' => 'Passport', 'sort_order' => 3],
            ['code' => 'KITAS', 'name' => 'KITAS / KITAP', 'sort_order' => 4],
        ]);

        $this->seedRecords(Religion::class, [
            ['code' => 'ISLAM', 'name' => 'Islam', 'sort_order' => 1],
            ['code' => 'CHRISTIAN', 'name' => 'Christian', 'sort_order' => 2],
            ['code' => 'CATHOLIC', 'name' => 'Catholic', 'sort_order' => 3],
            ['code' => 'HINDU', 'name' => 'Hindu', 'sort_order' => 4],
            ['code' => 'BUDDHIST', 'name' => 'Buddhist', 'sort_order' => 5],
            ['code' => 'CONFUCIAN', 'name' => 'Confucian', 'sort_order' => 6],
        ]);

        $this->seedRecords(MaritalStatus::class, [
            ['code' => 'SINGLE', 'name' => 'Single', 'sort_order' => 1],
            ['code' => 'MARRIED', 'name' => 'Married', 'sort_order' => 2],
            ['code' => 'DIVORCED', 'name' => 'Divorced', 'sort_order' => 3],
            ['code' => 'WIDOWED', 'name' => 'Widowed', 'sort_order' => 4],
        ]);

        $this->seedRecords(Bank::class, [
            ['code' => 'BCA', 'name' => 'Bank Central Asia', 'sort_order' => 1],
            ['code' => 'MANDIRI', 'name' => 'Bank Mandiri', 'sort_order' => 2],
            ['code' => 'BNI', 'name' => 'Bank Negara Indonesia', 'sort_order' => 3],
            ['code' => 'BRI', 'name' => 'Bank Rakyat Indonesia', 'sort_order' => 4],
            ['code' => 'CIMB', 'name' => 'CIMB Niaga', 'sort_order' => 5],
        ]);

        $this->seedRecords(JobLevel::class, [
            ['code' => 'L1', 'name' => 'Staff', 'sort_order' => 1],
            ['code' => 'L2', 'name' => 'Senior Staff', 'sort_order' => 2],
            ['code' => 'L3', 'name' => 'Manager', 'sort_order' => 3],
            ['code' => 'L4', 'name' => 'Director', 'sort_order' => 4],
        ], $companyGroup->id);

        $this->seedRecords(JobGrade::class, [
            ['code' => 'G1', 'name' => 'Grade 1', 'sort_order' => 1],
            ['code' => 'G2', 'name' => 'Grade 2', 'sort_order' => 2],
            ['code' => 'G3', 'name' => 'Grade 3', 'sort_order' => 3],
            ['code' => 'G4', 'name' => 'Grade 4', 'sort_order' => 4],
        ], $companyGroup->id);

        $peopleOps = Department::query()->firstOrCreate(
            [
                'company_id' => $holdingCompany->id,
                'code' => 'HR',
            ],
            [
                'company_group_id' => $companyGroup->id,
                'name' => 'People Operations',
                'description' => 'Human resources and people operations.',
            ]
        );

        $engineering = Department::query()->firstOrCreate(
            [
                'company_id' => $holdingCompany->id,
                'code' => 'ENG',
            ],
            [
                'company_group_id' => $companyGroup->id,
                'name' => 'Engineering',
                'description' => 'Product engineering and platform delivery.',
            ]
        );

        $this->seedRecords(Division::class, [
            [
                'company_group_id' => $companyGroup->id,
                'company_id' => $holdingCompany->id,
                'department_id' => $peopleOps->id,
                'code' => 'HR-BP',
                'name' => 'HR Business Partner',
                'sort_order' => 1,
            ],
            [
                'company_group_id' => $companyGroup->id,
                'company_id' => $holdingCompany->id,
                'department_id' => $engineering->id,
                'code' => 'ENG-PLATFORM',
                'name' => 'Platform Engineering',
                'sort_order' => 2,
            ],
        ], null, false);
    }

    private function seedRecords(string $modelClass, array $records, ?int $companyGroupId = null, bool $setGroupByDefault = true): void
    {
        foreach ($records as $record) {
            if ($setGroupByDefault && ! array_key_exists('company_group_id', $record)) {
                $record['company_group_id'] = $companyGroupId;
            }

            $modelClass::query()->updateOrCreate(
                ['code' => $record['code'], 'company_id' => $record['company_id'] ?? null],
                array_merge([
                    'description' => $record['name'],
                    'is_active' => true,
                ], $record)
            );
        }
    }
}
