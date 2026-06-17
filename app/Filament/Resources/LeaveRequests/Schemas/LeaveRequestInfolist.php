<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeaveRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request Overview')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('company.name')
                            ->label('Company'),
                        TextEntry::make('employee.full_name')
                            ->label('Employee'),
                        TextEntry::make('leaveType.name')
                            ->label('Leave Type'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => LeaveRequest::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => LeaveRequestResource::statusColor($state)),
                        TextEntry::make('start_date')
                            ->date(),
                        TextEntry::make('end_date')
                            ->date(),
                        TextEntry::make('requested_days')
                            ->label('Requested Days')
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('half_day_type')
                            ->label('Half Day Type')
                            ->formatStateUsing(fn (?string $state): string => blank($state) ? '-' : (LeaveRequest::halfDayTypeOptions()[$state] ?? $state)),
                        TextEntry::make('reason')
                            ->columnSpanFull()
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ]),
                ]),
            Section::make('Timeline')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created At'),
                        TextEntry::make('submitted_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('cancelled_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('cancelledBy.full_name')
                            ->label('Cancelled By')
                            ->placeholder('-'),
                        TextEntry::make('cancellation_reason')
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ]),
                ]),
            Section::make('Attachment')
                ->schema([
                    TextEntry::make('attachment.original_filename')
                        ->label('Attachment')
                        ->placeholder('No attachment uploaded.')
                        ->url(fn (LeaveRequest $record): ?string => $record->attachment?->url(), shouldOpenInNewTab: true),
                    TextEntry::make('attachment.mime_type')
                        ->label('MIME Type')
                        ->placeholder('-'),
                    TextEntry::make('attachment.size_bytes')
                        ->label('Size (bytes)')
                        ->placeholder('-'),
                ]),
        ]);
    }
}
