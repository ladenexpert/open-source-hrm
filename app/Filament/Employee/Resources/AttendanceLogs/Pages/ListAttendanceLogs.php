<?php

namespace App\Filament\Employee\Resources\AttendanceLogs\Pages;

use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\Attendance\AttendanceLocationValidationService;
use App\Services\Attendance\AttendanceLogService;
use App\Services\Attendance\ShiftResolverService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
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
                ->action(fn () => $this->dispatch('attendance-request-geolocation', method: 'clockIn')),
            Action::make('clockOut')
                ->label('Clock Out')
                ->icon('heroicon-o-arrow-left-circle')
                ->color('warning')
                ->action(fn () => $this->dispatch('attendance-request-geolocation', method: 'clockOut')),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            View::make('filament.employee.resources.attendance-logs.geo-capture-bridge'),
        ]);
    }

    public function submitAttendanceEvent(
        string $method,
        ?float $latitude = null,
        ?float $longitude = null,
    ): bool
    {
        $user = auth()->user();

        if (! $user instanceof Employee || ! $this->isSupportedAttendanceMethod($method)) {
            return false;
        }

        try {
            $log = app(AttendanceLogService::class)->{$method}($user, [
                'source' => AttendanceLog::SOURCE_WEB,
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title($exception->getMessage() ?: 'Attendance submission could not be recorded.')
                ->body(collect($exception->errors())->flatten()->join(' '))
                ->danger()
                ->send();

            return false;
        }

        if (! $log->is_valid) {
            Notification::make()
                ->title($log->validation_message ?: 'Attendance was saved as an invalid audit record.')
                ->danger()
                ->send();

            return false;
        }

        Notification::make()
            ->title($this->resolveSuccessMessage($method))
            ->success()
            ->send();

        return true;
    }

    public function handleGeolocationFailure(string $method, string $reason): bool
    {
        if (! $this->isSupportedAttendanceMethod($method)) {
            return false;
        }

        $message = $this->resolveGeolocationFailureMessage($reason);

        if (! $this->canSubmitAttendanceWithoutGps()) {
            Notification::make()
                ->title($message)
                ->danger()
                ->send();

            return false;
        }

        Notification::make()
            ->title($message)
            ->body('GPS is optional for your current attendance policy. Submission will continue without GPS.')
            ->warning()
            ->send();

        return $this->submitAttendanceEvent($method);
    }

    public function canSubmitAttendanceWithoutGps(): bool
    {
        $user = auth()->user();

        if (! $user instanceof Employee) {
            return false;
        }

        $workLocation = app(ShiftResolverService::class)->resolveWorkLocation(
            $user,
            now(config('app.timezone')),
        );
        $validation = app(AttendanceLocationValidationService::class)->validate(
            $user,
            null,
            null,
            $workLocation,
        );

        return (bool) $validation['is_valid'];
    }

    private function isSupportedAttendanceMethod(string $method): bool
    {
        return in_array($method, ['clockIn', 'clockOut'], true);
    }

    private function resolveSuccessMessage(string $method): string
    {
        return $method === 'clockOut'
            ? 'Clock out recorded.'
            : 'Clock in recorded.';
    }

    private function resolveGeolocationFailureMessage(string $reason): string
    {
        return match ($reason) {
            'permission_denied' => 'Location permission is required for attendance.',
            'unsupported' => 'Your browser does not support location capture.',
            'timeout' => 'Unable to detect your location. Please try again.',
            'unavailable' => 'Unable to detect your location. Please try again.',
            default => 'Unable to detect your location. Please try again.',
        };
    }
}
