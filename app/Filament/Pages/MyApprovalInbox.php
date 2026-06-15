<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Models\ApprovalRequestStep;
use App\Models\Employee;
use App\Services\ApprovalActionService;
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

        $count = ApprovalRequestStep::query()
            ->where('approver_id', $user->getKey())
            ->where('status', 'pending')
            ->whereHas('request', fn (Builder $query): Builder => $query->where('status', 'pending'))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ApprovalRequestStep::query()
                    ->with(['request.company', 'request.requester', 'request.employeeSubject', 'workflowStep'])
                    ->where('approver_id', auth()->id())
                    ->where('status', 'pending')
                    ->whereHas('request', fn (Builder $query): Builder => $query->where('status', 'pending'))
                    ->latest('id')
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
                        app(ApprovalActionService::class)->approveCurrentStep($record->request, auth()->user(), $data['comments'] ?? null);

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
                        app(ApprovalActionService::class)->rejectCurrentStep($record->request, auth()->user(), $data['comments'] ?? null);

                        Notification::make()->title('Approval request rejected.')->success()->send();
                    }),
                Action::make('open')
                    ->label('Open Request')
                    ->url(fn (ApprovalRequestStep $record): string => ApprovalRequestResource::getUrl('view', ['record' => $record->request])),
            ]);
    }
}
