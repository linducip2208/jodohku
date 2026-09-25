<?php

namespace Tests\Feature;

use App\Livewire\SwipeDeck;
use App\Models\Interest;
use App\Models\Profile;
use App\Models\SavedFilter;
use App\Models\User;
use App\Services\LikeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DatingUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_swipe_deck_renders_and_likes(): void
    {
        $me = User::factory()->create(['city' => 'Jakarta']);
        $other = User::factory()->create(['city' => 'Jakarta']);

        Livewire::actingAs($me)
            ->test(SwipeDeck::class)
            ->assertSee($other->displayName())
            ->call('like')
            ->assertOk();
        $this->assertDatabaseHas('likes', ['liker_id' => $me->id, 'liked_id' => $other->id]);
    }

    public function test_swipe_pass_and_rewind(): void
    {
        $me = User::factory()->create(['city' => 'Jakarta']);
        $other = User::factory()->create(['city' => 'Jakarta']);

        $t = Livewire::actingAs($me)->test(SwipeDeck::class);
        $first = $t->get('stack')[0] ?? null;
        $this->assertNotNull($first);
        $t->call('pass');
        $this->assertNotContains($first, $t->get('stack'));
        $t->call('rewind');
        $this->assertContains($first, $t->get('stack'));
    }

    public function test_saved_filters_crud(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/filter-tersimpan', [
            'name' => 'Bandung serius',
            'filters' => ['city' => 'Bandung', 'bogus' => 'x', 'min_age' => 25],
        ])->assertRedirect();
        $saved = SavedFilter::where('user_id', $me->id)->firstOrFail();
        $this->assertEquals(['city' => 'Bandung', 'min_age' => 25], $saved->filters);

        $this->actingAs($me)->get('/discover?city=Bandung')->assertOk();
        $this->actingAs($me)->delete("/filter-tersimpan/{$saved->id}")->assertRedirect();
        $this->assertDatabaseMissing('saved_filters', ['id' => $saved->id]);
    }

    public function test_onboarding_flow_stores_and_advances(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->get('/onboarding')->assertOk()->assertSee('Langkah 1');
        $this->actingAs($me)->get('/onboarding/ngaco')->assertNotFound();
        $this->actingAs($me)->post('/onboarding/dasar', [
            'display_name' => 'Budi', 'date_of_birth' => now()->subYears(25)->toDateString(),
            'gender' => 'male', 'city' => 'Jakarta',
        ])->assertRedirect('/onboarding/tujuan');
        $this->assertEquals('Jakarta', $me->fresh()->city);

        $interest = Interest::factory()->create();
        $this->actingAs($me)->post('/onboarding/tujuan', [
            'relationship_goal' => 'marriage', 'interests' => [$interest->id],
        ])->assertRedirect('/onboarding/foto');
        $this->assertTrue($me->fresh()->interests()->where('interests.id', $interest->id)->exists());

        $this->actingAs($me)->get('/onboarding/foto')->assertOk();
        $this->actingAs($me)->post('/onboarding/preferensi', [
            'gender_preference' => 'female', 'min_age' => 20, 'max_age' => 30, 'max_distance_km' => 50,
        ])->assertRedirect('/discover');
        $this->assertEquals(50, $me->fresh()->partnerPreference->max_distance_km);
    }

    public function test_match_icebreaker_send_opens_chat(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $svc = app(LikeService::class);
        $svc->like($me, $other);
        $svc->like($other, $me);

        $res = $this->actingAs($me)->post("/matches/{$other->id}/icebreaker-send", ['text' => 'Hai, salam kenal!']);
        $res->assertRedirect();
        $this->assertStringContainsString('/chat/', $res->headers->get('Location'));
        $this->assertDatabaseHas('messages', ['sender_id' => $me->id, 'body' => 'Hai, salam kenal!']);
    }

    public function test_profile_prompts_save_and_show(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->post('/settings/profile', ['prompt_0' => 'Kopi susu tiap pagi']);
        $prompts = $other->fresh()->profile->prompts;
        $this->assertEquals('Kopi susu tiap pagi', $prompts[Profile::PROMPT_QUESTIONS[0]]);

        $html = $this->actingAs($me)->get('/profile/'.$other->id)->assertOk()->getContent();
        $this->assertStringContainsString('Cerita dia', $html);
        $this->assertStringContainsString('Kopi susu tiap pagi', $html);
    }

    public function test_discover_filter_sheet_has_new_fields(): void
    {
        $me = User::factory()->create();
        SavedFilter::create(['user_id' => $me->id, 'name' => 'Tes', 'filters' => ['city' => 'Jakarta']]);
        $html = $this->actingAs($me)->get('/discover')->assertOk()->getContent();
        $this->assertStringContainsString('Pekerjaan', $html);
        $this->assertStringContainsString('Tujuan hubungan', $html);
        $this->assertStringContainsString('Filter tersimpan', $html);
    }

    public function test_mode_toggle_preserves_active_filters(): void
    {
        $me = User::factory()->create();
        $html = $this->actingAs($me)->get('/discover?tab=orang&mode=grid&city=Bandung&min_age=20')->assertOk()->getContent();
        $this->assertStringContainsString('city=Bandung', $html);
        $this->assertStringContainsString('min_age=20', $html);
        $this->assertStringContainsString('mode=swipe', $html);
    }
}
