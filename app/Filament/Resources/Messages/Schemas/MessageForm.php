<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            //
            TextInput::make('subject')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->label('Subject'),
            Select::make('receiver_id')
                ->label('receiver')
                ->required()
                ->multiple()
                ->options(
                    fn () => Employee::query()
                        ->when(
                            auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin(),
                            fn (Builder $query): Builder => $query->forCompany(auth()->user()->getEffectiveCompanyId()),
                        )
                        ->when(
                            auth()->check(),
                            fn (Builder $query): Builder => $query->whereKeyNot(auth()->id()),
                        )
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->email])
                        ->all()

                )
                ->columnSpanFull()
                ->searchable(['email']),

            RichEditor::make('content')
                // ->extraAttributes(['style' => 'height: 400px;'])
                ->columnSpanFull(),
        ]);
    }
}
