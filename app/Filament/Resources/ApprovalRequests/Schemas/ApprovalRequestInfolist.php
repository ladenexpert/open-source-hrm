<?php

namespace App\Filament\Resources\ApprovalRequests\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApprovalRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Overview')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('module_type')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('company.name')
                            ->label('Company'),
                        TextEntry::make('requester.full_name')
                            ->label('Requester'),
                        TextEntry::make('employeeSubject.full_name')
                            ->label('Subject'),
                        TextEntry::make('current_step_order')
                            ->label('Current Step'),
                        TextEntry::make('submitted_at')
                            ->dateTime(),
                        TextEntry::make('completed_at')
                            ->dateTime(),
                        TextEntry::make('summary')
                            ->columnSpanFull(),
                        TextEntry::make('payload')
                            ->formatStateUsing(fn ($state): string => blank($state) ? '-' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->columnSpanFull(),
                    ]),
                ]),
            Section::make('Approval Steps')
                ->schema([
                    RepeatableEntry::make('steps')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('step_order')
                                    ->label('Order'),
                                TextEntry::make('workflowStep.name')
                                    ->label('Step'),
                                TextEntry::make('approver_type')
                                    ->badge(),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('approver.full_name')
                                    ->label('Approver'),
                                TextEntry::make('acted_at')
                                    ->dateTime(),
                                TextEntry::make('comments')
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ]),
            Section::make('Approval Log')
                ->schema([
                    RepeatableEntry::make('logs')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),
                                TextEntry::make('action')
                                    ->badge(),
                                TextEntry::make('actor.full_name')
                                    ->label('Actor'),
                                TextEntry::make('comments'),
                            ]),
                        ]),
                ]),
        ]);
    }
}
