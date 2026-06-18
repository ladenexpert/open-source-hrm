<?php

namespace App\Filament\Employee\Resources\AttendanceLogs\Pages;

use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource;
use App\Models\Employee;
use App\Services\Attendance\AttendanceLogService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceLogs extends ListRecords
{
    protected static string $resource = AttendanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clockIn')
                ->label('Clock In')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                ->action(function (): void {
                    $user = auth()->user();

                    if (! $user instanceof Employee) {
                        return;
                    }

                    app(AttendanceLogService::class)->clockIn($user, [
                        'source' => 'web',
                        'ip_address' => request()->ip(),
                        'user_agent' => (string) request()->userAgent(),
                    ]);

                    Notification::make()->title('Clock in recorded.')->success()->send();
                }),
            Action::make('clockOut')
                ->label('Clock Out')
                ->icon('heroicon-o-arrow-left-circle')
                ->color('warning')
                ->action(function (): void {
                    $user = auth()->user();

                    if (! $user instanceof Employee) {
                        return;
                    }

                    app(AttendanceLogService::class)->clockOut($user, [
                        'source' => 'web',
                        'ip_address' => request()->ip(),
                        'user_agent' => (string) request()->userAgent(),
                    ]);

                    Notification::make()->title('Clock out recorded.')->success()->send();
                }),
        ];
    }
}
