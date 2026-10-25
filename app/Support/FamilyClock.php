<?php

namespace App\Support;

use App\Models\Family;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Hora de la familia. Todo se guarda en UTC; las fechas que escribe una persona,
 * los límites de "hoy" y las etiquetas se calculan en la zona horaria de la familia.
 */
class FamilyClock
{
    public const LOCAL_FORMAT = 'Y-m-d\TH:i';

    public function __construct(public readonly string $timezone) {}

    public static function for(Family $family): self
    {
        return new self($family->settings->timezone ?? config('app.timezone'));
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /** "2026-10-25T14:20" escrito en hora de la familia -> instante UTC. */
    public function parse(string $local): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::LOCAL_FORMAT, $local, $this->timezone)->utc();
    }

    public function toLocal(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->setTimezone($this->timezone)->format(self::LOCAL_FORMAT);
    }

    /**
     * Límites [inicio, fin) en UTC de un rango móvil: hoy, últimos 7 días o últimos 30.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(string $range): array
    {
        $today = $this->now()->startOfDay();
        $days = match ($range) {
            'semana' => 6,
            'mes' => 29,
            default => 0,
        };

        return [$today->subDays($days)->utc(), $today->addDay()->utc()];
    }
}
