<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGroupPageTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGroup(User $owner, string $visibility = 'public'): Group
    {
        return Group::create([
            'owner_id' => $owner->id, 'name' => 'Grup Uji '.$visibility,
            'description' => 'Deskripsi', 'visibility' => $visibility,
        ]);
    }

    public function test_guest_sees_public_group_without_error(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 'public');

        $html = $this->get('/g/'.$group->slug)->assertOk()->content();
        $this->assertStringContainsString($group->name, $html);
    }

    public function test_guest_gets_404_for_non_public_group(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 'private');

        $this->get('/g/'.$group->slug)->assertNotFound();
    }

    public function test_member_can_join_and_leave_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->makeGroup($owner, 'public');

        $this->actingAs($member)->post("/groups/{$group->id}/join")->assertRedirect();
        $this->assertTrue($group->fresh()->hasMember($member->id));

        $this->actingAs($member)->delete("/groups/{$group->id}/leave")->assertRedirect();
        $this->assertFalse($group->fresh()->hasMember($member->id));
    }

    public function test_member_story_lifecycle(): void
    {
        $me = User::factory()->create();

        $created = $this->actingAs($me)->post('/stories', ['type' => 'text', 'body' => 'Story siklus hidup'])->assertRedirect();
        $story = Story::where('user_id', $me->id)->firstOrFail();
        $this->assertStringContainsString('Story siklus hidup', $this->actingAs($me)->get("/stories/{$story->id}")->assertOk()->content());

        $this->actingAs($me)->delete("/stories/{$story->id}")->assertRedirect();
        $this->assertNull(Story::find($story->id));
    }
}
