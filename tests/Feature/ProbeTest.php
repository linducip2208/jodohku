<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $chat = app(ChatService::class);
        $conv = $chat->findOrCreateDirect($a, $b);
        foreach (['satu', 'dua', 'tiga'] as $i => $body) {
            try {
                $m = $chat->sendMessage($conv->fresh(), $i % 2 ? $b->fresh() : $a->fresh(), ['body' => $body]);
                fwrite(STDERR, "\nSENT id={$m->id} status={$m->status->value}\n");
            } catch (\Throwable $e) {
                fwrite(STDERR, "\nFAIL[$body]: ".get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n");
            }
        }
        $this->assertTrue(true);
    }
}
