<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function conv(User $a, User $b)
    {
        return app(ChatService::class)->findOrCreateDirect($a, $b);
    }

    protected function imageFile(): UploadedFile
    {
        $img = imagecreatetruecolor(400, 300);
        $path = tempnam(sys_get_temp_dir(), 'att').'.jpg';
        imagejpeg($img, $path, 85);
        imagedestroy($img);

        return new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);
    }

    public function test_image_upload_creates_typed_message_with_attachment(): void
    {
        Storage::fake('public');
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conv = $this->conv($a, $b);

        $res = $this->actingAs($a, 'sanctum')->postJson(
            "/api/v1/conversations/{$conv->id}/attachments",
            ['file' => $this->imageFile(), 'body' => 'Lihat ini', 'client_message_id' => 'att-1']
        )->assertCreated();

        $this->assertEquals('image', $res->json('type'));
        $msgId = $res->json('id');
        $this->assertDatabaseHas('message_attachments', ['message_id' => $msgId]);
        Storage::disk('public')->assertExists(
            MessageAttachment::where('message_id', $msgId)->firstOrFail()->file_path
        );

        // Idempotent retry: same client_message_id returns same message.
        $retry = $this->actingAs($a, 'sanctum')->postJson(
            "/api/v1/conversations/{$conv->id}/attachments",
            ['file' => $this->imageFile(), 'client_message_id' => 'att-1']
        )->assertCreated();
        $this->assertEquals($msgId, $retry->json('id'));
        $this->assertEquals(1, Message::where('conversation_id', $conv->id)->count());
    }

    public function test_executable_spoof_rejected_and_stranger_forbidden(): void
    {
        Storage::fake('public');
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $conv = $this->conv($a, $b);

        $path = tempnam(sys_get_temp_dir(), 'evil').'.jpg';
        file_put_contents($path, '<?php echo 1;');
        $evil = new UploadedFile($path, 'shell.jpg', 'image/jpeg', null, true);

        $this->actingAs($a, 'sanctum')->postJson(
            "/api/v1/conversations/{$conv->id}/attachments", ['file' => $evil]
        )->assertStatus(422);

        $this->actingAs($c, 'sanctum')->postJson(
            "/api/v1/conversations/{$conv->id}/attachments", ['file' => $this->imageFile()]
        )->assertForbidden();
    }
}
