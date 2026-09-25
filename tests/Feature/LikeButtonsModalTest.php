<?php

namespace Tests\Feature;

use App\Livewire\LikeButtons;
use App\Models\User;
use App\Services\LikeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LikeButtonsModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutual_like_shows_match_modal_with_real_ctas(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        app(LikeService::class)->like($me, $other);

        Livewire::actingAs($other)
            ->test(LikeButtons::class, ['userId' => $me->id])
            ->call('like')
            ->assertSet('matchedUserId', $me->id)
            ->assertSee("It's a Match!", false)
            ->assertSee('/matches', false)
            ->assertSee('/profile/'.$me->id, false);
    }

    public function test_single_like_shows_no_modal(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($me)
            ->test(LikeButtons::class, ['userId' => $other->id])
            ->call('like')
            ->assertSet('matchedUserId', null)
            ->assertSee('Like terkirim');
    }

    public function test_web_rewind_undoes_last_action_with_redirect(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        app(LikeService::class)->like($me, $other);
        $this->assertDatabaseHas('likes', ['liker_id' => $me->id, 'liked_id' => $other->id]);

        $this->actingAs($me)->post('/rewind')->assertRedirect();
        $this->assertDatabaseMissing('likes', ['liker_id' => $me->id, 'liked_id' => $other->id]);
    }

    public function test_web_rewind_without_history_redirects_with_status(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/rewind')->assertRedirect();
    }
}
