<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;

final readonly class TaskPlanningNormalizer
{
    public function __construct(private WorkCalendar $calendar) {}

    public function normalize(TaskPlanningState $persisted, TaskPlanningPatch $patch, ?PlanningDriver $driver = null): TaskPlanningState
    {
        $this->validateDriver($patch, $driver);

        $start = $patch->hasStart ? $patch->start : $persisted->start;
        $finish = $patch->hasFinish ? $patch->finish : $persisted->finish;
        $duration = $patch->hasDurationWorkdays ? $patch->durationWorkdays : $persisted->durationWorkdays;

        $this->validateDuration($duration);
        if ($patch->isEmpty() || $patch->clearsAnyValue()) {
            if ($start !== null && $finish !== null && $finish < $start) {
                throw new InvalidArgumentException('A data final não pode ser anterior à data inicial.');
            }

            return new TaskPlanningState($start, $finish, $duration);
        }

        $effectiveDriver = $driver ?? $this->inferDriver($patch);
        if ($effectiveDriver === PlanningDriver::Duration && $start !== null && $duration !== null) {
            $finish = $this->calendar->addWorkDays($start, $duration - 1);
        }

        if ($effectiveDriver === PlanningDriver::Start && $start !== null) {
            if ($duration !== null) {
                $finish = $this->calendar->addWorkDays($start, $duration - 1);
            } elseif ($finish !== null) {
                $duration = $this->calendar->countWorkDays($start, $finish);
            }
        }

        if ($effectiveDriver === PlanningDriver::Finish && $start !== null && $finish !== null) {
            $duration = $this->calendar->countWorkDays($start, $finish);
        }

        if ($start !== null && $finish !== null && $finish < $start) {
            throw new InvalidArgumentException('A data final não pode ser anterior à data inicial.');
        }

        return new TaskPlanningState($start, $finish, $duration);
    }

    private function validateDriver(TaskPlanningPatch $patch, ?PlanningDriver $driver): void
    {
        if ($driver === null && $patch->hasDurationWorkdays && $patch->durationWorkdays !== null && ($patch->hasStart || $patch->hasFinish)) {
            throw new InvalidArgumentException('Informe o campo causador ao alterar duração junto de início ou fim.');
        }
        if ($driver === null) {
            return;
        }
        if ($patch->isEmpty()) {
            throw new InvalidArgumentException('O campo causador exige uma alteração de planejamento.');
        }
        if ($driver === PlanningDriver::Start && ! $patch->hasStart) {
            throw new InvalidArgumentException('O campo causador início exige uma alteração de início.');
        }
        if ($driver === PlanningDriver::Finish && ! $patch->hasFinish) {
            throw new InvalidArgumentException('O campo causador fim exige uma alteração de fim.');
        }
        if ($driver === PlanningDriver::Duration && ! $patch->hasDurationWorkdays) {
            throw new InvalidArgumentException('O campo causador duração exige uma alteração de duração.');
        }
    }

    private function validateDuration(?int $duration): void
    {
        if ($duration !== null && ($duration < 1 || $duration > 3650)) {
            throw new InvalidArgumentException('A duração planejada deve estar entre 1 e 3650 dias úteis.');
        }
    }

    private function inferDriver(TaskPlanningPatch $patch): ?PlanningDriver
    {
        return match (true) {
            $patch->hasDurationWorkdays => PlanningDriver::Duration,
            $patch->hasFinish => PlanningDriver::Finish,
            $patch->hasStart => PlanningDriver::Start,
            default => null,
        };
    }
}
