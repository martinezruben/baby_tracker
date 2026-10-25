<?php

namespace App\Support\Activities;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class ActivityType
{
    /** Identificador en la URL (/registrar/{slug}). */
    abstract public function slug(): string;

    /** @return class-string<Model> */
    abstract public function model(): string;

    /** Columna con la fecha del registro (para ordenar y decir "hace cuánto"). */
    abstract public function timeColumn(): string;

    /** Página de Inertia con el formulario de este tipo. */
    public function page(): string
    {
        return 'Registrar/'.Str::studly($this->slug());
    }

    /** Registros de este tipo para un bebé (los tipos que comparten tabla lo restringen). */
    public function query(int $babyId): Builder
    {
        return ($this->model())::query()->where('baby_id', $babyId);
    }

    /** @return array<string, mixed> */
    abstract public function rules(ActivityContext $context): array;

    /** @param  array<string, mixed>  $data  datos ya validados */
    abstract public function create(ActivityContext $context, array $data): Model;

    /** Datos del último registro que la interfaz necesita para "Repetir lo último". */
    public function summary(Model $log): array
    {
        return [];
    }

    public function repeatable(): bool
    {
        return false;
    }

    public function repeat(ActivityContext $context, Model $last): Model
    {
        throw new \LogicException("{$this->slug()} no se puede repetir.");
    }

    /** Fecha escrita por la persona, en hora de la familia: obligatoria y no futura (5 min de margen). */
    protected function occurredAtRules(ActivityContext $context, string $field = 'occurred_at'): array
    {
        return [
            'required',
            'date_format:Y-m-d\TH:i',
            function (string $attribute, mixed $value, Closure $fail) use ($context) {
                // Si el formato es inválido ya falla `date_format`; aquí solo se compara con la hora actual.
                try {
                    $at = $context->clock->parse((string) $value);
                } catch (\Throwable) {
                    return;
                }

                if ($at->greaterThan(now()->addMinutes(5))) {
                    $fail(trans('ui.validation.not_future'));
                }
            },
        ];
    }
}
