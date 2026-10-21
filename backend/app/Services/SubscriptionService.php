<?php

namespace App\Services;

use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SubscriptionService
 *
 * Handles freemium quota checks, usage logging, and premium tier status.
 */
class SubscriptionService
{
    /**
     * Get dynamic free financial message limit for the given user.
     * Evaluates users.max_free_financial_messages first, then bot_settings.max_free_financial_messages.
     */
    public function getFreeLimit(User $user): int
    {
        return $user->getEffectiveFreeLimit();
    }

    /**
     * Check if user is eligible to record a financial transaction or ask a financial question.
     */
    public function canRecord(User $user): bool
    {
        $limit = $this->getFreeLimit($user);
        return $user->hasActivePremium() || (int) $user->free_financial_message_count < $limit;
    }

    /**
     * Consume one unit of free message quota if user is not on premium.
     *
     * @throws RuntimeException
     */
    public function consume(User $user, string $messageId, int $transactionsRecorded = 1): int
    {
        return DB::transaction(function () use ($user, $messageId, $transactionsRecorded) {
            /** @var User $locked */
            $locked = User::whereKey($user->jid)->lockForUpdate()->firstOrFail();
            $limit = $this->getFreeLimit($locked);

            if (UsageLog::where('message_id', $messageId)->exists()) {
                return max(0, $limit - (int) $locked->free_financial_message_count);
            }

            if (!$locked->hasActivePremium()) {
                if ((int) $locked->free_financial_message_count >= $limit) {
                    throw new RuntimeException('FREE_QUOTA_EXHAUSTED');
                }
                $locked->increment('free_financial_message_count');
            }

            UsageLog::create([
                'user_jid'              => $locked->jid,
                'message_id'            => $messageId,
                'transactions_recorded' => $transactionsRecorded,
            ]);

            $user->refresh();

            return max(0, $limit - (int) $locked->free_financial_message_count);
        });
    }

    /**
     * Get user's subscription tier and remaining message quota.
     *
     * @return array{tier: string, is_pro: bool, remaining: int, free_limit: int, used: int, expires_at: \Illuminate\Support\Carbon|null, plan_display: string}
     */
    public function status(User $user): array
    {
        $user->refresh();
        $isPro = $user->hasActivePremium();
        $limit = $this->getFreeLimit($user);
        $used = (int) $user->free_financial_message_count;

        return [
            'tier'         => $isPro ? 'PREMIUM' : 'FREE',
            'is_pro'       => $isPro,
            'remaining'    => max(0, $limit - $used),
            'free_limit'   => $limit,
            'used'         => $used,
            'expires_at'   => $user->subscription_expires_at,
            'plan_display' => $isPro ? 'HematWoy Pro' : 'Gratis (Freemium)',
        ];
    }
}
