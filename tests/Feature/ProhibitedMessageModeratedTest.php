<?php

namespace Tests\Feature;

use App\Models\ProfanityCategory;
use App\Models\ProfanityWord;
use App\Models\User;
use App\Services\MessageModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProhibitedMessageModeratedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = ProfanityCategory::firstOrCreate(['slug' => 'kasar-id'], ['name' => 'Kasar Indonesia', 'severity' => 3]);
        ProfanityWord::firstOrCreate(['word' => 'anjing'], [
            'profanity_category_id' => $cat->id, 'replacement' => '*****', 'language' => 'id', 'severity' => 3, 'is_active' => true,
        ]);
    }

    public function test_profanity_is_masked(): void
    {
        $user = User::factory()->create();
        $mod = app(MessageModerationService::class)->moderate('Kamu anjing banget ya', $user);

        $this->assertContains($mod['decision'], ['mask', 'warning', 'flag', 'block']);
        $this->assertNotEquals('Kamu anjing banget ya', $mod['clean']);
        $this->assertStringNotContainsString('anjing', strtolower($mod['clean']));
        $this->assertNotEmpty($mod['flags']);
    }

    public function test_phone_number_and_offplatform_lure_is_flagged(): void
    {
        $user = User::factory()->create();
        $mod = app(MessageModerationService::class)->moderate(
            'Hubungi aku di WA 0812-3456-7890 ya, transfer uang cepat + bonus 50%',
            $user
        );

        $flat = implode('|', $mod['flags']);
        $this->assertStringContainsString('phone_number', $flat);
        $this->assertGreaterThan(0, $mod['risk']);
        $this->assertContains($mod['decision'], ['mask', 'warning', 'flag', 'block']);
    }
}
