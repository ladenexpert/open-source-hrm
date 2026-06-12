<?php

namespace App\Filament\Resources\Payrolls\Schema;

use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
                Select::make('employee_id')
                    ->options(function () {
                        $query = Employee::query()->orderBy('first_name');

                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                        }

                        return $query->get()->pluck('name', 'id')->all();
                    })
                    ->searchable(
                        [
                            'first_name',
                            'last_name',
                        ]
                    )
                    ->required()
                    ->label('Employee'),
                DatePicker::make('pay_date')
                    ->label('Pay Date')
                    ->required(),
                TextInput::make('period')
                    ->label('Period')
                    ->placeholder('e.g., 2025-01')
                    ->required()
                    ->maxLength(255),
                TextInput::make('gross_pay')
                    ->label('Gross Pay')
                    ->required()
                    ->numeric(),

                TextInput::make('net_pay')
                    ->label('Net Pay')
                    ->required()
                    ->numeric(),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending'),
                KeyValue::make('deductions')
                    ->label('Deductions')
                    ->keyLabel('Type')

                    ->valueLabel('Amount'),

                KeyValue::make('allowances')
                    ->label('Allowances')
                    ->keyLabel('Type')

                    ->valueLabel('Amount'),
                KeyValue::make('bonuses')
                    ->label('Bonuses')
                    ->keyLabel('Type')

                    ->valueLabel('Amount'),
                Textarea::make('notes')
                    ->label('Notes')
                    ->nullable()
                    ->columnSpan('full'),
            ]);
    }
}
