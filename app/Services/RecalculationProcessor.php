<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TodoistGateway;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

final readonly class RecalculationProcessor
{
    public function __construct(private TodoistGateway $gateway, private AuditWriter $audit) {}

    /** @return 'completed'|'retry'|'failed' */
    public function process(string $recalculationId): string
    {
        $recalculation = DB::table('recalculations')->where('id', $recalculationId)->first();
        if (! $recalculation || in_array($recalculation->state, ['completed', 'failed', 'cancelled'], true)) {
            return 'completed';
        }
        $project = DB::table('gantt_projects')->where('id', $recalculation->gantt_project_id)->first();
        $integration = $project ? DB::table('todoist_integrations')->where('user_id', $project->user_id)->where('status', 'active')->first() : null;
        if (! $project || ! $integration) {
            $this->finish($recalculation, 'failed', 'Integração Todoist indisponível.');

            return 'failed';
        }
        try {
            $remoteSnapshot = $this->gateway->projectSnapshot(decrypt($integration->access_token_encrypted), $project->todoist_project_id);
            $remoteTasks = $remoteSnapshot['tasks']['results'] ?? $remoteSnapshot['tasks'] ?? [];
            $remoteById = [];
            foreach ($remoteTasks as $remoteTask) {
                $remoteById[(string) ($remoteTask['id'] ?? '')] = $remoteTask;
            }
        } catch (\Throwable $exception) {
            $this->finish($recalculation, 'partial', $exception::class);

            return 'retry';
        }
        DB::table('recalculations')->where('id', $recalculationId)->update(['state' => 'applying', 'updated_at' => now()]);
        DB::table('sync_operations')->where('gantt_project_id', $project->id)->where('operation', 'recalculation.apply')->where('state', 'pending')->update(['state' => 'applying', 'updated_at' => now()]);
        foreach (DB::table('recalculation_items')->where('recalculation_id', $recalculationId)->whereIn('state', ['pending', 'pending_retry'])->orderBy('sequence')->get() as $item) {
            $before = json_decode($item->before_state, true, flags: JSON_THROW_ON_ERROR);
            $remoteTask = $remoteById[$item->todoist_task_id] ?? null;
            $remoteStart = $remoteTask['due']['date'] ?? null;
            $remoteFinish = $remoteTask['deadline_date'] ?? $remoteStart;
            if ($remoteTask === null || $remoteStart !== ($before['start'] ?? null) || $remoteFinish !== ($before['finish'] ?? null)) {
                DB::table('recalculation_items')->where('id', $item->id)->update(['state' => 'stale', 'error' => 'snapshot_changed', 'updated_at' => now()]);
                $this->finish($recalculation, 'conflict', 'snapshot_changed');

                return 'failed';
            }
            DB::table('recalculation_items')->where('id', $item->id)->update(['state' => 'applying', 'attempts' => $item->attempts + 1, 'updated_at' => now()]);
            try {
                $after = json_decode($item->after_state, true, flags: JSON_THROW_ON_ERROR);
                $this->gateway->updateTaskDates(decrypt($integration->access_token_encrypted), $item->todoist_task_id, $after['start'], $after['finish']);
                DB::table('recalculation_items')->where('id', $item->id)->update(['state' => 'applied', 'error' => null, 'updated_at' => now()]);
            } catch (\Throwable $exception) {
                $temporary = ! $exception instanceof RequestException || $exception->response?->status() === 429 || $exception->response?->serverError();
                $state = $temporary && $item->attempts + 1 < 3 ? 'pending_retry' : 'permanent_failure';
                DB::table('recalculation_items')->where('id', $item->id)->update(['state' => $state, 'error' => $exception::class, 'updated_at' => now()]);
                $this->finish($recalculation, $state === 'pending_retry' ? 'partial' : 'failed', $exception::class);

                return $state === 'pending_retry' ? 'retry' : 'failed';
            }
        }
        $this->finish($recalculation, 'completed', null);

        return 'completed';
    }

    private function finish(object $recalculation, string $state, ?string $error): void
    {
        DB::table('recalculations')->where('id', $recalculation->id)->update(['state' => $state, 'error' => $error, 'updated_at' => now()]);
        DB::table('sync_operations')->where('command_id', $recalculation->command_id)->update(['state' => $state === 'completed' ? 'applied' : $state, 'last_error' => $error, 'available_at' => $state === 'partial' ? now()->addMinute() : null, 'updated_at' => now()]);
        $project = DB::table('gantt_projects')->where('id', $recalculation->gantt_project_id)->first();
        $this->audit->record($project?->user_id, $recalculation->gantt_project_id, 'recalculation.'.$state, 'worker', 'recalculation', $recalculation->id, $recalculation->command_id, null, ['state' => $state, 'error' => $error]);
    }
}
