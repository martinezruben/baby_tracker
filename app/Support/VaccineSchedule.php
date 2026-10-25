<?php

namespace App\Support;

use App\Models\Baby;
use App\Models\Vaccination;
use App\Models\VaccineDose;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Estado de cada dosis del esquema de la familia para un bebé, a partir de su fecha de nacimiento:
 * aplicada, vencida (pasó su fecha y no se aplicó), próxima (en los siguientes 60 días) o pendiente.
 */
class VaccineSchedule
{
    public const SOON_DAYS = 60;

    public function __construct(private FamilyClock $clock) {}

    public static function for(Baby $baby): self
    {
        return new self(FamilyClock::for($baby->family));
    }

    /**
     * @return Collection<int, array{dose: VaccineDose, due_on: CarbonImmutable, status: string, vaccination: ?Vaccination, days_left: int}>
     */
    public function rows(Baby $baby): Collection
    {
        $today = $this->clock->now()->startOfDay();
        $birth = CarbonImmutable::parse($baby->birth_date->toDateString(), $this->clock->timezone)->startOfDay();

        $given = $baby->vaccinations()->whereNotNull('vaccine_dose_id')->get()->keyBy('vaccine_dose_id');

        return VaccineDose::where('family_id', $baby->family_id)
            ->orderBy('age_days')->orderBy('vaccine_name')->orderBy('id')->get()
            ->map(function (VaccineDose $dose) use ($birth, $today, $given) {
                $due = $birth->addDays($dose->age_days);
                $daysLeft = (int) $today->diffInDays($due, absolute: false);
                $vaccination = $given->get($dose->id);

                return [
                    'dose' => $dose,
                    'due_on' => $due,
                    'vaccination' => $vaccination,
                    'days_left' => $daysLeft,
                    'status' => match (true) {
                        $vaccination !== null => 'applied',
                        $daysLeft < 0 => 'overdue',
                        $daysLeft <= self::SOON_DAYS => 'soon',
                        default => 'later',
                    },
                ];
            })
            ->values();
    }

    /** Hora a la que se avisa el día de la dosis. */
    public function dueAt(CarbonImmutable $dueOn): CarbonImmutable
    {
        return $dueOn->setTime(9, 0)->utc();
    }
}
