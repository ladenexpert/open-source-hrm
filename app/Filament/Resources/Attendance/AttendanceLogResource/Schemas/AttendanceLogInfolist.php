<?php

namespace App\Filament\Resources\Attendance\AttendanceLogResource\Schemas;

use App\Models\AttendanceLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\URL;

class AttendanceLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attendance Log')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('employee.full_name')->label('Employee'),
                        TextEntry::make('attendance_date')->date(),
                        TextEntry::make('clocked_at')->dateTime(),
                        TextEntry::make('event_type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => AttendanceLog::eventTypeLabels()[$state] ?? $state),
                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => AttendanceLog::sourceLabels()[$state] ?? $state),
                        TextEntry::make('workLocation.name')->label('Work Location')->placeholder('-'),
                        TextEntry::make('shiftPattern.name')->label('Shift Pattern')->placeholder('-'),
                        TextEntry::make('latitude')->placeholder('-'),
                        TextEntry::make('longitude')->placeholder('-'),
                        TextEntry::make('is_valid')
                            ->label('Validity')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Valid' : 'Invalid')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('createdBy.full_name')->label('Created By')->placeholder('-'),
                        TextEntry::make('validation_message')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('user_agent')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                    ]),
                ]),
            Section::make('Selfie Verification')
                ->hidden(fn (AttendanceLog $record): bool => $record->attendanceSelfie === null)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('attendanceSelfie.captured_at')
                            ->label('Captured At')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('attendanceSelfie.image_path')
                            ->label('Stored Path')
                            ->placeholder('-'),
                        TextEntry::make('selfie_secure_link')
                            ->label('Secure Selfie')
                            ->state('Open secure selfie')
                            ->url(fn (AttendanceLog $record): ?string => $record->attendanceSelfie === null
                                ? null
                                : URL::temporarySignedRoute(
                                    'attendance-selfies.show',
                                    now()->addMinutes(5),
                                    ['attendanceSelfie' => $record->attendanceSelfie],
                                ),
                                shouldOpenInNewTab: true),
                        TextEntry::make('selfie_source')
                            ->label('Upload Source')
                            ->state(fn (AttendanceLog $record): ?string => $record->attendanceSelfie?->metadata['capture_source'] ?? null)
                            ->placeholder('-'),
                        TextEntry::make('selfie_device_info')
                            ->label('Device Info')
                            ->state(fn (AttendanceLog $record): string => self::formatJson($record->attendanceSelfie?->device_info))
                            ->columnSpanFull()
                            ->placeholder('-'),
                        TextEntry::make('selfie_metadata')
                            ->label('Metadata')
                            ->state(fn (AttendanceLog $record): string => self::formatJson($record->attendanceSelfie?->metadata))
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ]),
                ]),
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private static function formatJson(?array $payload): string
    {
        if ($payload === null || $payload === []) {
            return '-';
        }

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
