<?php

namespace App\Filament\Employee\Resources\AttendanceLogs\Pages;

use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\Attendance\AttendanceLocationValidationService;
use App\Services\Attendance\AttendanceLogService;
use App\Services\Attendance\AttendancePolicyResolverService;
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
        ?string $selfie = null,
        array $deviceInfo = [],
        array $metadata = [],
        ?string $deviceUuid = null,
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
                'selfie' => $selfie,
                'device_info' => $this->sanitizeJsonPayload($deviceInfo),
                'metadata' => $this->sanitizeJsonPayload($metadata),
                'device_identifier' => $deviceUuid,
                'device_uuid' => $deviceUuid,
            ]);
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())
                ->flatten()
                ->filter()
                ->values();
            $title = $messages->first() ?: 'Attendance submission could not be recorded.';
            $body = $messages->count() > 1
                ? $messages->slice(1)->join(' ')
                : null;

            Notification::make()
                ->title($title)
                ->body($body)
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

    public function handleGeolocationFailure(
        string $method,
        string $reason,
        ?string $selfie = null,
        array $deviceInfo = [],
        array $metadata = [],
        ?string $deviceUuid = null,
    ): bool
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

        return $this->submitAttendanceEvent($method, null, null, $selfie, $deviceInfo, $metadata, $deviceUuid);
    }

    public function handleSelfieRequirementNotMet(string $method): bool
    {
        if (! $this->isSupportedAttendanceMethod($method)) {
            return false;
        }

        Notification::make()
            ->title(sprintf(
                '%s requires a selfie upload under the active attendance policy.',
                $method === 'clockOut' ? 'Clock Out' : 'Clock In',
            ))
            ->danger()
            ->send();

        return false;
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

    public function requiresSelfieVerification(): bool
    {
        $user = auth()->user();

        if (! $user instanceof Employee) {
            return false;
        }

        return app(AttendancePolicyResolverService::class)
            ->resolvePolicy($user)
            ?->requiresSelfie() ?? false;
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

    /**
     * @return array<string, mixed>
     */
    private function sanitizeJsonPayload(array $payload): array
    {
        return collect($payload)
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(static function (mixed $value): mixed {
                if (is_scalar($value) || is_array($value)) {
                    return $value;
                }

                return (string) $value;
            })
            ->all();
    }
}
