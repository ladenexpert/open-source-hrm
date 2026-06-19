<?php

namespace App\Filament\Employee\Pages;

use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource;
use App\Filament\Employee\Resources\AttendanceLogs\AttendanceLogResource;
use App\Filament\Employee\Resources\AttendanceSummaries\AttendanceSummaryResource;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class MyAttendance extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static ?string $navigationLabel = 'My Attendance';

    protected static ?string $title = 'My Attendance';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'my-attendance';

    protected string $view = 'filament.employee.pages.my-attendance';

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Auth::user()->is_active;
    }

    public function getTodaySummary(): ?AttendanceSummary
    {
        $employee = Auth::user();

        if (! $employee instanceof Employee) {
            return null;
        }

        return AttendanceSummary::query()
            ->tap(fn (Builder $query) => $this->applyAttendanceSummaryScope($query, $employee))
            ->forDate(now(config('app.timezone'))->toDateString())
            ->first();
    }

    /**
     * @return Collection<int, AttendanceSummary>
     */
    public function getRecentSummaries(): Collection
    {
        $employee = Auth::user();

        if (! $employee instanceof Employee) {
            return new Collection();
        }

        return AttendanceSummary::query()
            ->tap(fn (Builder $query) => $this->applyAttendanceSummaryScope($query, $employee))
            ->betweenDates(
                now(config('app.timezone'))->copy()->subDays(6)->toDateString(),
                now(config('app.timezone'))->toDateString(),
            )
            ->orderByDesc('attendance_date')
            ->get();
    }

    public function getHistoryUrl(): string
    {
        return AttendanceSummaryResource::getUrl(panel: 'portal');
    }

    public function getCorrectionsUrl(): string
    {
        return AttendanceCorrectionResource::getUrl(panel: 'portal');
    }

    public function getAttendanceLogUrl(): string
    {
        return AttendanceLogResource::getUrl(panel: 'portal');
    }

    public function getCorrectionCreateUrl(?AttendanceSummary $summary = null): string
    {
        return AttendanceCorrectionResource::getCreateUrlForSummary(
            $summary,
            $summary?->attendance_date?->toDateString() ?? now(config('app.timezone'))->toDateString(),
        );
    }

    public function getStatusLabel(?string $status): string
    {
        return AttendanceSummary::statusLabels()[$status] ?? ($status ?: '-');
    }

    public function getStatusBadgeClasses(?string $status): string
    {
        return match ($status) {
            AttendanceSummary::STATUS_PRESENT => 'bg-success-50 text-success-700 ring-success-600/20',
            AttendanceSummary::STATUS_LATE => 'bg-warning-50 text-warning-700 ring-warning-600/20',
            AttendanceSummary::STATUS_EARLY_OUT => 'bg-warning-50 text-warning-700 ring-warning-600/20',
            AttendanceSummary::STATUS_ABSENT => 'bg-danger-50 text-danger-700 ring-danger-600/20',
            AttendanceSummary::STATUS_INCOMPLETE => 'bg-gray-100 text-gray-700 ring-gray-500/20',
            AttendanceSummary::STATUS_LEAVE => 'bg-info-50 text-info-700 ring-info-600/20',
            AttendanceSummary::STATUS_HOLIDAY,
            AttendanceSummary::STATUS_WEEKEND,
            AttendanceSummary::STATUS_NO_SCHEDULE => 'bg-primary-50 text-primary-700 ring-primary-600/20',
            default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
        };
    }

    public function getPendingCorrectionCount(): int
    {
        $employee = Auth::user();

        if (! $employee instanceof Employee) {
            return 0;
        }

        return AttendanceCorrection::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->status(AttendanceCorrection::STATUS_PENDING)
            ->count();
    }

    private function applyAttendanceSummaryScope(Builder $query, Employee $employee): Builder
    {
        return $query
            ->with([
                'shiftPattern:id,name',
                'workLocation:id,name',
            ])
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee);
    }
}
