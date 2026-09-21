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
}
