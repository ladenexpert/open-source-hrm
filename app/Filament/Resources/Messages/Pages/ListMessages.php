<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    public function getTabs(): array
    {
        $user = Auth::user();
        $type = $user instanceof Employee ? Employee::class : Employee::class;

        return [
            'all' => Tab::make(),
            'Sent' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->where('creator_id', $user->id)

                ),
            'Received' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->where('receiver_id', $user->id)

                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Actions\Action::make('refresh')
                ->label(' ')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refresh()),
        ];
    }
}
