<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SubscriptionQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_tier_consumes_one_unit_per_successful_message_and_blocks_the_101st(): void
    {
        $user=User::create(['jid'=>'628222@s.whatsapp.net']);
        $service=app(SubscriptionService::class);
        for($i=1;$i<=100;$i++) $remaining=$service->consume($user,'quota-'.$i,2);
        $this->assertSame(0,$remaining);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FREE_QUOTA_EXHAUSTED');
        $service->consume($user,'quota-101');
    }
}
