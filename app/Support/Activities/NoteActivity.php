<?php

namespace App\Support\Activities;

use App\Models\Note;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;

class NoteActivity extends ActivityType
{
    public function slug(): string
    {
        return 'nota';
    }

    public function model(): string
    {
        return Note::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'content' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        return [
            'occurred_at' => $context->clock->parse($data['occurred_at']),
            'content' => $data['content'],
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return ['occurred_at' => $clock->toLocal($log->occurred_at), 'content' => $log->content];
    }
}
