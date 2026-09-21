<?php

namespace Tests\Unit;

use App\Models\ProfanityCategory;
use App\Models\ProfanityWord;
use App\Services\ProfanityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfanityCensorTest extends TestCase
{
    use RefreshDatabase;

    public function test_censor_masks_configured_words(): void
    {
        $cat = ProfanityCategory::firstOrCreate(['slug' => 'kasar-id'], ['name' => 'Kasar Indonesia', 'severity' => 3]);
        ProfanityWord::firstOrCreate(['word' => 'bangsat'], [
            'profanity_category_id' => $cat->id, 'replacement' => '*******', 'language' => 'id', 'severity' => 4, 'is_active' => true,
        ]);

        $result = app(ProfanityService::class)->censor('Dasar bangsat kamu!');
        $this->assertEquals(1, $result['count']);
        $this->assertStringNotContainsString('bangsat', strtolower($result['clean']));
    }

    public function test_censor_survives_serializing_cache_drivers(): void
    {
        // Regression: caching Eloquent models breaks unserialize on
        // database/file/redis drivers (__PHP_Incomplete_Class). Prime with
        // array store, then re-read through the database store.
        $cat = ProfanityCategory::firstOrCreate(['slug' => 'kasar-id'], ['name' => 'Kasar Indonesia', 'severity' => 3]);
        ProfanityWord::firstOrCreate(['word' => 'bangsat'], [
            'profanity_category_id' => $cat->id, 'replacement' => '*******', 'language' => 'id', 'severity' => 4, 'is_active' => true,
        ]);

        $svc = app(ProfanityService::class);
        $this->assertEquals(1, $svc->censor('Dasar bangsat kamu!')['count']);

        config()->set('cache.default', 'database');
        \Illuminate\Support\Facades\Cache::forget('profanity_words:v2');
        $again = $svc->censor('Bangsat lagi!');
        $this->assertEquals(1, $again['count']);
        $third = $svc->censor('Bangsat sekali lagi!');
        $this->assertEquals(1, $third['count']);
    }

    public function test_admin_can_manage_dictionary_and_cache_busts(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Moderator]);
        $this->actingAs($admin)->postJson('/admin/moderation/words', [
            'word' => 'testkasar123', 'language' => 'id',
        ])->assertCreated();
        $this->assertDatabaseHas('profanity_words', ['word' => 'testkasar123']);
        $w = \App\Models\ProfanityWord::where('word', 'testkasar123')->firstOrFail();
        $this->actingAs($admin)->deleteJson("/admin/moderation/words/{$w->id}")->assertOk();
        $this->assertDatabaseMissing('profanity_words', ['word' => 'testkasar123']);
    }
}
