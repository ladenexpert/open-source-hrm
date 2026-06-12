<?php

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MessageTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->modifyQueryUsing(function ($query) {
                $userId = Auth::id();

                return $query->where(function ($query) use ($userId) {
                    $query
                        ->where('creator_id', $userId)
                        ->orWhere('receiver_id', $userId);
                });
            })
            ->columns([
                //
                TextColumn::make('creator.name')
                    ->sortable([
                        'first_name',
                        'last_name',
                    ])
                    ->searchable([
                        'first_name',
                        'last_name',
                    ])
                    ->label('Sender')
                    ->weight(function ($record) {
                        return $record
                            ->message()
                            ->whereNull('read_at')
                            ->exists()
                            ? FontWeight::Bold
                            : FontWeight::Light;
                    })
                    ->color(function ($record) {
                        return $record
                            ->message()
                            ->whereNull('read_at')
                            ->exists()
                            ? 'light'
                            : 'gray';
                    }),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(20)
                    ->color(
                        color: function ($record) {
                            return $record
                                ->message()
                                ->whereNull('read_at')
                                ->exists()
                                ? 'light'
                                : 'gray';
                        },
                    )
                    ->weight(function ($record) {
                        return $record
                            ->message()
                            ->whereNull('read_at')
                            ->exists()
                            ? FontWeight::Bold
                            : FontWeight::Light;
                    }),

                TextColumn::make('created_at')
                    ->label('Created at')

                    ->formatStateUsing(
                        fn ($state) => $state->format('D, M-d-Y H:i A'),
                    )
                    ->weight(function ($record) {
                        return $record
                            ->message()
                            ->whereNull('read_at')
                            ->exists()
                            ? FontWeight::Bold
                            : FontWeight::Light;
                    })
                    ->color(function ($record) {
                        return $record
                            ->message()
                            ->whereNull('read_at')
                            ->exists()
                            ? 'light'
                            : 'gray';
                    }),
            ])

            ->recordActions([
                ActionGroup::make([

                    ViewAction::make(),
                    DeleteAction::make(

                    ),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('created_at', 'desc');
    }
}
