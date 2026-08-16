<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class WorkspaceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'project' => ['id' => 'demo-product-launch', 'name' => 'Lançamento do Ganttist', 'source' => 'Todoist', 'sync_status' => 'synced', 'updated_at' => now()->toIso8601String()],
            'calendar' => ['timezone' => 'America/Sao_Paulo', 'working_days' => [1, 2, 3, 4, 5], 'exceptions' => [['date' => '2026-08-21', 'type' => 'NON_WORKING', 'description' => 'Feriado local']]],
            'tasks' => $this->tasks(),
            'dependencies' => [
                ['id' => 'd1', 'from' => 't1', 'to' => 't2', 'type' => 'FS', 'critical' => true], ['id' => 'd2', 'from' => 't2', 'to' => 't3', 'type' => 'FS', 'critical' => true],
                ['id' => 'd3', 'from' => 't3', 'to' => 't5', 'type' => 'FS', 'critical' => true], ['id' => 'd4', 'from' => 't4', 'to' => 't5', 'type' => 'FF', 'critical' => false],
                ['id' => 'd5', 'from' => 't5', 'to' => 't7', 'type' => 'FS', 'critical' => true], ['id' => 'd6', 'from' => 't6', 'to' => 't7', 'type' => 'SS', 'critical' => false],
            ],
            'stats' => ['progress' => 38, 'completed' => 3, 'total' => 12, 'critical' => 5, 'unscheduled' => 1],
        ], 'meta' => ['demo' => true, 'message' => 'Fixture local; conecte o OAuth Todoist para usar dados reais.']]);
    }

    private function tasks(): array
    {
        return [
            ['id' => 'g1', 'title' => 'Estratégia & Descoberta', 'kind' => 'group', 'level' => 0, 'start' => '2026-08-17', 'finish' => '2026-08-20', 'progress' => 100, 'status' => 'completed', 'critical' => true],
            ['id' => 't1', 'title' => 'Mapear jornada principal', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-17', 'finish' => '2026-08-18', 'progress' => 100, 'status' => 'completed', 'critical' => true, 'priority' => 4, 'assignee' => 'MC'],
            ['id' => 't2', 'title' => 'Validar arquitetura técnica', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-19', 'finish' => '2026-08-20', 'progress' => 100, 'status' => 'completed', 'critical' => true, 'priority' => 3, 'assignee' => 'RL'],
            ['id' => 'g2', 'title' => 'Experiência do produto', 'kind' => 'group', 'level' => 0, 'start' => '2026-08-24', 'finish' => '2026-09-03', 'progress' => 36, 'status' => 'running', 'critical' => true],
            ['id' => 't3', 'title' => 'Design system e fundações', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-24', 'finish' => '2026-08-27', 'progress' => 70, 'status' => 'running', 'critical' => true, 'priority' => 4, 'assignee' => 'AS'],
            ['id' => 't4', 'title' => 'Prototipar navegação mobile', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-25', 'finish' => '2026-08-28', 'progress' => 45, 'status' => 'running', 'critical' => false, 'priority' => 2, 'assignee' => 'CB'],
            ['id' => 't5', 'title' => 'Renderizador Gantt próprio', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-28', 'finish' => '2026-09-03', 'progress' => 18, 'status' => 'running', 'critical' => true, 'priority' => 4, 'assignee' => 'RL'],
            ['id' => 'g3', 'title' => 'Integrações & Qualidade', 'kind' => 'group', 'level' => 0, 'start' => '2026-09-04', 'finish' => '2026-09-14', 'progress' => 0, 'status' => 'not_started', 'critical' => true],
            ['id' => 't6', 'title' => 'OAuth e sincronização Todoist', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-04', 'finish' => '2026-09-08', 'progress' => 0, 'status' => 'not_started', 'critical' => false, 'priority' => 3, 'assignee' => 'MC'],
            ['id' => 't7', 'title' => 'Golden tests do scheduling', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-04', 'finish' => '2026-09-09', 'progress' => 0, 'status' => 'not_started', 'critical' => true, 'priority' => 4, 'assignee' => 'RL'],
            ['id' => 't8', 'title' => 'Homologação do fluxo completo', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-10', 'finish' => '2026-09-14', 'progress' => 0, 'status' => 'not_started', 'critical' => true, 'priority' => 3, 'assignee' => 'AS'],
            ['id' => 't9', 'title' => 'Definir campanha de lançamento', 'kind' => 'task', 'level' => 0, 'start' => null, 'finish' => null, 'progress' => 0, 'status' => 'unscheduled', 'critical' => false, 'priority' => 1, 'assignee' => 'CB'],
        ];
    }
}
