<?php

namespace App\Filament\Employee\Resources\AttendanceLogs\Pages;

use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource;
use App\Models\Employee;
use App\Services\Attendance\AttendanceLogService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

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
                ->action(fn (): bool => $this->recordAttendanceEvent('clockIn', 'Clock in recorded.')),
            Action::make('clockOut')
                ->label('Clock Out')
                ->icon('heroicon-o-arrow-left-circle')
                ->color('warning')
                ->action(fn (): bool => $this->recordAttendanceEvent('clockOut', 'Clock out recorded.')),
        ];
    }

    private function recordAttendanceEvent(string $method, string $successMessage): bool
    {
        $user = auth()->user();

        if (! $user instanceof Employee) {
            return false;
        }

        try {
            app(AttendanceLogService::class)->{$method}($user, [
                'source' => 'web',
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title($exception->getMessage() ?: 'Attendance submission could not be recorded.')
                ->body(collect($exception->errors())->flatten()->join(' '))
                ->danger()
                ->send();

            return false;
        }

        Notification::make()
            ->title($successMessage)
            ->success()
            ->send();

        return true;
    }
}
