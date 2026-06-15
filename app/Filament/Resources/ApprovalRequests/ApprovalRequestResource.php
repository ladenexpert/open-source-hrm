<?php

namespace App\Filament\Resources\ApprovalRequests;

use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Filament\Resources\ApprovalRequests\Pages\ListApprovalRequests;
use App\Filament\Resources\ApprovalRequests\Pages\ViewApprovalRequest;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\ApprovalActionService;
use App\Support\ApprovalRoleMap;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 41;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('module_type')
                    ->label('Module')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->toggleable(),
                TextColumn::make('requester.full_name')
                    ->label('Requester')
                    ->searchable(),
                TextColumn::make('employeeSubject.full_name')
                    ->label('Subject')
                    ->toggleable(),
                TextColumn::make('current_step_order')
                    ->label('Current Step')
                    ->sortable(),
                TextColumn::make('summary')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ApprovalRequestStatus::options()),
                SelectFilter::make('module_type')
                    ->options(ApprovalModuleType::options()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->schema([
                        Textarea::make('comments')
                            ->label('Comments')
                            ->rows(4),
                    ])
                    ->visible(fn (ApprovalRequest $record): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('approve', $record))
                    ->action(function (ApprovalRequest $record, array $data): void {
                        app(ApprovalActionService::class)->approveCurrentStep($record, Auth::user(), $data['comments'] ?? null);

                        Notification::make()
                            ->title('Approval step completed.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('comments')
                            ->label('Comments')
                            ->required()
                            ->rows(4),
                    ])
                    ->visible(fn (ApprovalRequest $record): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('reject', $record))
                    ->action(function (ApprovalRequest $record, array $data): void {
                        app(ApprovalActionService::class)->rejectCurrentStep($record, Auth::user(), $data['comments'] ?? null);

                        Notification::make()
                            ->title('Approval request rejected.')
                            ->success()
                            ->send();
                    }),
                Action::make('view')
                    ->url(fn (ApprovalRequest $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequests::route('/'),
            'view' => ViewApprovalRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'requester',
            'employeeSubject',
        ]);
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where('requester_id', $user->getKey())
                ->orWhereHas('steps', fn (Builder $steps): Builder => $steps->where('approver_id', $user->getKey()));

            if (ApprovalRoleMap::matches($user, ApprovalRoleMap::workflowManagerRoles())) {
                $scope->orWhere(function (Builder $managerScope) use ($user): void {
                    $managerScope->whereIn('company_id', $user->accessibleCompanyIds())
                        ->orWhere('company_group_id', $user->getEffectiveCompanyGroupId());
                });
            }

            if (ApprovalRoleMap::matches($user, ApprovalRoleMap::financeRoles())) {
                $scope->orWhere(function (Builder $financeScope) use ($user): void {
                    $financeScope->where('company_id', $user->getEffectiveCompanyId())
                        ->whereIn('module_type', [
                            ApprovalModuleType::PAYROLL->value,
                            ApprovalModuleType::SALARY_CHANGE->value,
                        ]);
                });
            }
        });
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Gate::forUser(Auth::user())->allows('viewAny', ApprovalRequest::class);
    }
}
