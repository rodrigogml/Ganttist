<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

final class ProjectCalendar
{
    /**
     * @param  object|array<string, mixed>  $settings
     * @param  iterable<object|array<string, mixed>>  $exceptions
     */
    public static function fromSettings(object|array $settings, iterable $exceptions = []): WorkCalendar
    {
        $get = static fn (object|array $row, string $key, mixed $default = null): mixed => is_array($row)
            ? ($row[$key] ?? $default)
            : ($row->{$key} ?? $default);

        $weekdays = [
            1 => (bool) $get($settings, 'monday', true),
            2 => (bool) $get($settings, 'tuesday', true),
            3 => (bool) $get($settings, 'wednesday', true),
            4 => (bool) $get($settings, 'thursday', true),
            5 => (bool) $get($settings, 'friday', true),
            6 => (bool) $get($settings, 'saturday', false),
            7 => (bool) $get($settings, 'sunday', false),
        ];
        $mapped = [];
        foreach ($exceptions as $exception) {
            $date = (string) $get($exception, 'date');
            $type = (string) $get($exception, 'type');
            if ($date !== '' && in_array($type, ['WORKING', 'NON_WORKING'], true)) {
                $mapped[$date] = $type;
            }
        }

        return new WorkCalendar($weekdays, $mapped);
    }
}
