<?php

namespace Tests\Feature;

use App\Enums\CreditTxnType;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditPurchaseUpdatesWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_credits_balance_and_no_negative_guard(): void
    {
        $user = User::factory()->create();
        $svc = app(CreditService::class);

        $this->assertEquals(0, $svc->balance($user));

        $svc->record($user, CreditTxnType::Purchase, 120, 'Credit purchase credits_120');
        $this->assertEquals(120, $svc->balance($user->fresh()));

        $svc->spend($user, 20, 'Send gift');
        $this->assertEquals(100, $svc->balance($user->fresh()));

        // No-negative guard: overspend must throw and leave balance intact.
        $this->expectException(\RuntimeException::class);
        try {
            $svc->spend($user, 500, 'Overspend attempt');
        } finally {
            $this->assertEquals(100, $svc->balance($user->fresh()));
        }
    }
}
