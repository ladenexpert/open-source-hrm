<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Schemas\MessageInfolist;
use App\Models\Message;
use App\Models\Topic;
use Filament\Actions\Action as FAction;
use Filament\Forms\Components\RichEditor as FRichEditor;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    public $poll = '3s';

    public function getTitle(): string
    {
        return 'View Conversation';
    }

    public function mount($record): void
    {
        parent::mount($record);

        Message::where('topic_id', $this->record->id)
            ->whereNull('read_at')
            ->where('sender_id', '!=', auth()->id())
            ->update([
                'read_at' => now(),
            ]);
    }

    public function getSubheading(): string
    {

        // return 'Subject: ' . optional($this->record->topic)->subject;
        return 'Subject: '.$this->record->subject;
    }

    public function refreshInfo()
    {
        $this->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            FAction::make('MarkUnread')->label('Mark as unread')
                ->color('gray')
                ->action(function () {
                    Message::where('topic_id', $this->record->id)
                        // ->where('receiver_id', auth()->id())
                        ->update(['read_at' => null]);
                    Notification::make()
                        ->title('Messages marked as unread')
                        ->success()
                        ->send();

                    return $this->redirect(MessageResource::getUrl('index'));

                })
                ->visible(
                    fn () => $this->record->receiver_id === auth()->id()

                ),
            FAction::make('Reply')->label('Reply')
                ->schema([
                    FRichEditor::make('content')
                        ->required()
                        ->autofocus(),
                    // ->extraAttributes(['style' => 'height: 400px;'])
                ])
                ->action(function ($data) {

                    $userId = auth()->id();
                    $receiverId = $userId === $this->record->receiver_id ? $this->record->creator_id : $this->record->receiver_id;
                    Message::create([
                        'topic_id' => $this->record->id,
                        'sender_id' => auth()->id(),
                        'content' => $data['content'],
                        'receiver_id' => $receiverId,
                    ]);
                    $this->record->load('message');
                    $this->refresh();
                    Notification::make()
                        ->title('Reply sent successfully')
                        ->success()
                        ->send();
                }),
            FAction::make('refresh')
                ->label(' ')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refresh()),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return MessageInfolist::configure($schema);
    }
}
