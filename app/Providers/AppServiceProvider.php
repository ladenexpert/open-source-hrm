<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Message;
use App\Models\ShiftAssignment;
use App\Models\Task;
use App\Observers\DepartmentObserver;
use App\Observers\EmployeeObserver;
use App\Observers\MessageObserver;
use App\Observers\TaskObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        $this->configureCommands();
        $this->configureModels();
        $this->configureUrl();
        Task::observe(TaskObserver::class);
        Message::observe(MessageObserver::class);
        Department::observe(DepartmentObserver::class);
        Employee::observe(EmployeeObserver::class);

    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->environment('production')
        );
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict();
        Relation::morphMap([
            ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE => Employee::class,
            ShiftAssignment::ASSIGNABLE_TYPE_DEPARTMENT => Department::class,
            ShiftAssignment::ASSIGNABLE_TYPE_BRANCH => Branch::class,
        ]);
    }

    public function configureUrl(): void
    {
        if ($this->app->environment('production')) {

            URL::forceScheme('https');
        }
    }
}
