<?php

namespace App\Support\Activities;

use App\Models\Baby;
use App\Models\Caretaker;
use App\Support\FamilyClock;

final readonly class ActivityContext
{
    public function __construct(
        public Baby $baby,
        public Caretaker $caretaker,
        public FamilyClock $clock,
    ) {}

    /** @return array{family_id: int, baby_id: int, caretaker_id: int} */
    public function owner(): array
    {
        return ['family_id' => $this->baby->family_id, 'baby_id' => $this->baby->id, 'caretaker_id' => $this->caretaker->id];
    }
}
