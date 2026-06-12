<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class TaskBoard extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Task Board';

    protected static ?string $title = 'Task Board';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected string $view = 'filament.pages.task-board';
}
