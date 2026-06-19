<?php

namespace App\Filament\Employee\Resources\AttendanceSummaries\Widgets;

use App\Filament\Employee\Resources\AttendanceSummaries\Pages\ListAttendanceSummaries;
use App\Models\AttendanceSummary;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceHistoryOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ListAttendanceSummaries::class;
    }

    protected function getStats(): array
    {
        $totals = (clone $this->getPageTableQuery())
            ->withoutEagerLoads()
            ->reorder()
            ->toBase()
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as leave_count',
                [
                    AttendanceSummary::STATUS_PRESENT,
                    AttendanceSummary::STATUS_LATE,
                    AttendanceSummary::STATUS_ABSENT,
                    AttendanceSummary::STATUS_LEAVE,
                ],
            )
            ->first();

        return [
            Stat::make('Present', (string) ($totals?->present_count ?? 0))
                ->description('Filtered attendance summaries')
                ->color('success')
                ->icon('heroicon-o-check-badge'),
            Stat::make('Late', (string) ($totals?->late_count ?? 0))
                ->description('Includes late arrival days')
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Absent', (string) ($totals?->absent_count ?? 0))
                ->description('No valid in and out logs')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),
            Stat::make('Leave', (string) ($totals?->leave_count ?? 0))
                ->description('Approved full-day leave')
                ->color('info')
                ->icon('heroicon-o-briefcase'),
        ];
    }
}
