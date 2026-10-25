<?php

namespace App\Services\Notifications;

use App\Models\NotificationRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class QuietHours
{
    /**
     * Si $at cae dentro del horario silencioso de la regla, devuelve el instante
     * en que termina; si no, null. Admite ventanas que cruzan la medianoche.
     */
    public function endsAt(NotificationRule $rule, CarbonInterface $at, string $timezone): ?CarbonImmutable
    {
        if (! $rule->respect_quiet_hours || ! $rule->quiet_start || ! $rule->quiet_end) {
            return null;
        }

        $local = CarbonImmutable::instance($at)->setTimezone($timezone);
        $start = $local->setTimeFromTimeString($rule->quiet_start);
        $end = $local->setTimeFromTimeString($rule->quiet_end);

        if ($start->equalTo($end)) {
            return null;
        }

        if ($start->lessThan($end)) {
            return $local->betweenIncluded($start, $end) && ! $local->equalTo($end)
                ? $end->utc()
                : null;
        }

        // Cruza la medianoche: [start, 24:00) y [00:00, end)
        if ($local->greaterThanOrEqualTo($start)) {
            return $end->addDay()->utc();
        }

        return $local->lessThan($end) ? $end->utc() : null;
    }
}
