<?php

namespace Tests\Feature;

use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\Family;
use App\Models\FeedLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelosBaseTest extends TestCase
{
    use RefreshDatabase;

    private function baby(): Baby
    {
        $family = Family::create(['slug' => 'ruiz', 'name' => 'Familia Ruiz']);

        return $family->babies()->create(['first_name' => 'Sofía', 'birth_date' => '2026-06-01']);
    }

    public function test_family_owns_babies_and_logs_cast_enums(): void
    {
        $baby = $this->baby();

        $log = FeedLog::create([
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'occurred_at' => now(),
            'type' => FeedType::Bottle,
            'amount' => 90,
            'unit' => 'ml',
        ]);

        $this->assertSame(FeedType::Bottle, $log->fresh()->type);
        $this->assertTrue($baby->feedLogs->contains($log));
        $this->assertSame($baby->family_id, $log->family->id);
    }

    public function test_activity_logs_are_soft_deleted(): void
    {
        $baby = $this->baby();
        $log = DiaperLog::create([
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'occurred_at' => now(),
            'type' => DiaperType::Both,
        ]);

        $log->delete();

        $this->assertSoftDeleted($log);
        $this->assertCount(0, $baby->diaperLogs);
    }

    public function test_caretaker_pin_is_hashed_and_hidden(): void
    {
        $baby = $this->baby();
        $caretaker = Caretaker::create([
            'family_id' => $baby->family_id,
            'login_id' => 'mama',
            'name' => 'Mamá',
            'pin' => '123456',
        ]);

        $this->assertNotSame('123456', $caretaker->getRawOriginal('pin'));
        $this->assertArrayNotHasKey('pin', $caretaker->toArray());
        $this->assertTrue(password_verify('123456', $caretaker->getRawOriginal('pin')));
    }

    public function test_deleting_a_baby_cascades_to_logs(): void
    {
        $baby = $this->baby();
        DiaperLog::create([
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'occurred_at' => now(),
            'type' => DiaperType::Wet,
        ]);

        $baby->forceDelete();

        $this->assertDatabaseCount('diaper_logs', 0);
    }
}
