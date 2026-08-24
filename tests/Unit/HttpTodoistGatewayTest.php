<?php

namespace Tests\Unit;

use App\Infrastructure\Todoist\HttpTodoistGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class HttpTodoistGatewayTest extends TestCase
{
    public function test_project_snapshot_uses_the_expected_todoist_endpoints(): void
    {
        Http::fake([
            '*/projects' => Http::response(['results' => [['id' => 'p1']]]),
            '*/sections*' => Http::response(['results' => [['id' => 's1']]]),
            '*/tasks*' => Http::response(['results' => [['id' => 't1']]]),
            '*/collaborators*' => Http::response(['results' => [['id' => 'u1']]]),
        ]);

        $snapshot = (new HttpTodoistGateway)->projectSnapshot('token', 'p1');

        self::assertSame('t1', $snapshot['tasks']['results'][0]['id']);
        self::assertSame('u1', $snapshot['collaborators']['results'][0]['id']);
        Http::assertSentCount(3);
    }

    public function test_project_snapshot_follows_task_cursors_without_truncating_the_project(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/sections')) {
                return Http::response(['results' => [], 'next_cursor' => null]);
            }
            if (str_contains($request->url(), '/collaborators')) {
                return Http::response(['results' => [], 'next_cursor' => null]);
            }
            if (($request->data()['cursor'] ?? null) === 'next-page') {
                return Http::response(['results' => [['id' => 't2']], 'next_cursor' => null]);
            }

            return Http::response(['results' => [['id' => 't1']], 'next_cursor' => 'next-page']);
        });

        $snapshot = (new HttpTodoistGateway)->projectSnapshot('token', 'p1');

        self::assertSame(['t1', 't2'], array_column($snapshot['tasks']['results'], 'id'));
        Http::assertSentCount(4);
    }

    public function test_writes_use_the_provider_contract_and_bearer_token(): void
    {
        Http::fake(['*' => Http::response(['id' => 't1'])]);

        $gateway = new HttpTodoistGateway;
        $gateway->updateTaskDates('token', 't1', '2026-08-20', '2026-08-22');
        $gateway->updateTask('token', 't1', ['content' => 'Renomeada']);
        $gateway->setTaskCompletion('token', 't1', true);
        $gateway->createTask('token', ['content' => 'Nova', 'project_id' => 'p1']);
        $gateway->deleteTask('token', 't1');
        $gateway->comments('token', 't1');
        $gateway->createComment('token', 't1', 'Novo comentário');
        $gateway->updateComment('token', 'c1', 'Comentário editado');

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.todoist.com/api/v1/tasks/t1'
                && $request->hasHeader('Authorization', 'Bearer token')
                && ($data['due_date'] ?? null) === '2026-08-20'
                && ($data['deadline_date'] ?? null) === '2026-08-22';
        });
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.todoist.com/api/v1/tasks/t1/close');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.todoist.com/api/v1/tasks' && ($request->data()['content'] ?? null) === 'Nova');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'https://api.todoist.com/api/v1/tasks/t1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/comments') && ($request->data()['task_id'] ?? null) === 't1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.todoist.com/api/v1/comments' && ($request->data()['content'] ?? null) === 'Novo comentário');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.todoist.com/api/v1/comments/c1' && ($request->data()['content'] ?? null) === 'Comentário editado');
    }
}
