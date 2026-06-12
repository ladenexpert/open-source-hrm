<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
                Select::make('company_id')
                    ->label('Company')
                    ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => auth()->user() instanceof Employee ? auth()->user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                    ->required()
                    ->disabled(fn (): bool => auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin())
                    ->dehydrated(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Department Name')
                    ->placeholder('Enter department name'),
                TextInput::make('code')
                    ->maxLength(50)
                    ->label('Department Code')
                    ->placeholder('Enter department code'),
                Textarea::make('description')
                    ->maxLength(500)
                    ->label('Description')
                    ->placeholder('Enter department description'),
                Select::make('manager_id')
                    ->options(function () {
                        $query = Employee::query()->orderBy('first_name');

                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                        }

                        return $query->get()->pluck('name', 'id')->all();
                    })
                    ->label('Manager')

                    ->placeholder('Select a manager')
                    ->preload()
                    ->searchable()
                    ->nullable(),
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(function (): array {
                        $query = Branch::query()->orderBy('name');

                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                        }

                        return $query->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->nullable(),

            ]);
    }
}
