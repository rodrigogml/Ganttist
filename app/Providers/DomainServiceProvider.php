<?php

namespace App\Providers;

use App\Contracts\TodoistGateway;
use App\Infrastructure\Todoist\HttpTodoistGateway;
use App\Infrastructure\Todoist\FakeTodoistGateway;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TodoistGateway::class, fn () => config('services.todoist.driver') === 'fake' ? new FakeTodoistGateway : new HttpTodoistGateway);
    }
}
