<?php

namespace App\Filament\Resources\ApprovalLogs;

use App\Filament\Resources\ApprovalLogs\Pages\ListApprovalLogs;
use App\Models\ApprovalLog;
use App\Models\Employee;
use App\Support\ApprovalRoleMap;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApprovalLogResource extends Resource
{
    protected static ?string $model = ApprovalLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 42;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('request.module_type')
                ->label('Module')
                ->badge(),
            TextColumn::make('request.summary')
                ->label('Summary')
                ->limit(50)
                ->wrap(),
            TextColumn::make('action')
                ->badge(),
            TextColumn::make('actor.full_name')
                ->label('Actor')
                ->toggleable(),
            TextColumn::make('comments')
                ->limit(60)
                ->wrap(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalLogs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['request', 'actor']);
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas('request', function (Builder $requestQuery) use ($user): void {
            $requestQuery->where('requester_id', $user->getKey())
                ->orWhereHas('steps', fn (Builder $steps): Builder => $steps->where('approver_id', $user->getKey()));

            if (ApprovalRoleMap::matches($user, ApprovalRoleMap::workflowManagerRoles())) {
                $requestQuery->orWhere(function (Builder $scope) use ($user): void {
                    $scope->whereIn('company_id', $user->accessibleCompanyIds())
                        ->orWhere('company_group_id', $user->getEffectiveCompanyGroupId());
                });
            }
        });
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Gate::forUser(Auth::user())->allows('viewAny', ApprovalLog::class);
    }
}
