<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\ApprovalActionService;
use App\Services\Leave\LeaveApprovalService;
use App\Support\OrganizationScope;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class MyApprovalInbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'My Approval Inbox';

    protected static ?string $title = 'My Approval Inbox';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 39;

    protected string $view = 'filament.pages.my-approval-inbox';

    public static function canAccess(): bool
    {
        return auth()->user() instanceof Employee && auth()->user()->is_active;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof Employee) {
            return null;
        }

        $count = static::pendingStepsQueryFor($user)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        /** @var Employee|null $user */
        $user = auth()->user();

        return $table
            ->query(
                $user instanceof Employee
                    ? static::pendingStepsQueryFor($user)
                    : ApprovalRequestStep::query()->whereRaw('1 = 0')
            )
            ->columns([
                TextColumn::make('request.module_type')
                    ->label('Module')
                    ->badge(),
                TextColumn::make('request.company.name')
                    ->label('Company')
                    ->toggleable(),
                TextColumn::make('request.requester.full_name')
                    ->label('Requester')
                    ->searchable(),
                TextColumn::make('leave_request.employee')
                    ->label('Employee')
                    ->state(fn (ApprovalRequestStep $record): string => $this->resolveLeaveRequest($record->request)?->employee?->full_name ?? '-')
                    ->toggleable(),
                TextColumn::make('leave_request.leave_type')
                    ->label('Leave Type')
                    ->state(fn (ApprovalRequestStep $record): string => $this->resolveLeaveRequest($record->request)?->leaveType?->name ?? '-')
                    ->toggleable(),
                TextColumn::make('leave_request.period')
                    ->label('Period')
                    ->state(function (ApprovalRequestStep $record): string {
                        $leaveRequest = $this->resolveLeaveRequest($record->request);

                        if (! $leaveRequest instanceof LeaveRequest) {
                            return '-';
                        }

                        return $leaveRequest->start_date->toDateString().' - '.$leaveRequest->end_date->toDateString();
                    })
                    ->toggleable(),
                TextColumn::make('leave_request.requested_days')
                    ->label('Requested Days')
                    ->state(fn (ApprovalRequestStep $record): ?float => $this->resolveLeaveRequest($record->request)?->requested_days)
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(),
                TextColumn::make('request.employeeSubject.full_name')
                    ->label('Subject')
                    ->toggleable(),
                TextColumn::make('workflowStep.name')
                    ->label('Step'),
                TextColumn::make('step_order')
                    ->label('Order'),
                TextColumn::make('request.summary')
                    ->label('Summary')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('request.submitted_at')
                    ->label('Submitted')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->schema([
                        Textarea::make('comments')
                            ->rows(4),
                    ])
                    ->action(function (ApprovalRequestStep $record, array $data): void {
                        if ($this->resolveLeaveRequest($record->request) instanceof LeaveRequest) {
                            app(LeaveApprovalService::class)->processApproval($record->request, auth()->user(), 'approved', $data['comments'] ?? null);
                        } else {
                            app(ApprovalActionService::class)->approveCurrentStep($record->request, auth()->user(), $data['comments'] ?? null);
                        }

                        Notification::make()->title('Approval step completed.')->success()->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('comments')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (ApprovalRequestStep $record, array $data): void {
                        if ($this->resolveLeaveRequest($record->request) instanceof LeaveRequest) {
                            app(LeaveApprovalService::class)->processApproval($record->request, auth()->user(), 'rejected', $data['comments'] ?? null);
                        } else {
                            app(ApprovalActionService::class)->rejectCurrentStep($record->request, auth()->user(), $data['comments'] ?? null);
                        }

                        Notification::make()->title('Approval request rejected.')->success()->send();
                    }),
                Action::make('open')
                    ->label('Open Request')
                    ->url(fn (ApprovalRequestStep $record): string => $this->resolveOpenUrl($record->request)),
            ]);
    }

    private function resolveLeaveRequest(?ApprovalRequest $approvalRequest): ?LeaveRequest
    {
        if (! $approvalRequest instanceof ApprovalRequest) {
            return null;
        }

        $approvalRequest->loadMissing('approvable');
        $approvable = $approvalRequest->approvable;

        if (! $approvable instanceof LeaveRequest) {
            return null;
        }

        $approvable->loadMissing(['employee', 'leaveType']);

        return $approvable;
    }

    private function resolveOpenUrl(ApprovalRequest $approvalRequest): string
    {
        $leaveRequest = $this->resolveLeaveRequest($approvalRequest);

        if ($leaveRequest instanceof LeaveRequest) {
            return LeaveRequestResource::getUrl('view', ['record' => $leaveRequest]);
        }

        return ApprovalRequestResource::getUrl('view', ['record' => $approvalRequest]);
    }

    private static function pendingStepsQueryFor(Employee $user): Builder
    {
        return ApprovalRequestStep::query()
            ->with(['request.company', 'request.requester', 'request.employeeSubject', 'request.approvable', 'workflowStep'])
            ->where('approver_id', $user->getKey())
            ->where('status', 'pending')
            ->whereHas('request', function (Builder $query) use ($user): Builder {
                $query->where('status', 'pending');

                if ($user->isSuperAdmin()) {
                    return $query;
                }

                return OrganizationScope::applyCompanyOrGroupScope($query, $user);
            })
            ->latest('id');
    }
}
