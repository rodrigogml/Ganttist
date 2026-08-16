<?php

namespace App\Providers;

use App\Contracts\TodoistGateway;
use App\Infrastructure\Todoist\HttpTodoistGateway;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TodoistGateway::class, HttpTodoistGateway::class);
    }
}
