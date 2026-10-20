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
    public const FREE_LIMIT = 100;

    /**
     * Check if user is eligible to record a financial transaction.
     */
    public function canRecord(User $user): bool
    {
        return $user->hasActivePremium() || $user->free_financial_message_count < self::FREE_LIMIT;
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

            if (UsageLog::where('message_id', $messageId)->exists()) {
                return self::FREE_LIMIT - $locked->free_financial_message_count;
            }

            if (!$locked->hasActivePremium()) {
                if ($locked->free_financial_message_count >= self::FREE_LIMIT) {
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

            return max(0, self::FREE_LIMIT - $locked->free_financial_message_count);
        });
    }

    /**
     * Get user's subscription tier and remaining message quota.
     *
     * @return array{tier: string, remaining: int, expires_at: \Illuminate\Support\Carbon|null}
     */
    public function status(User $user): array
    {
        $user->refresh();
        $isPro = $user->hasActivePremium();

        return [
            'tier'         => $isPro ? 'PREMIUM' : 'FREE',
            'is_pro'       => $isPro,
            'remaining'    => max(0, self::FREE_LIMIT - (int) $user->free_financial_message_count),
            'free_limit'   => self::FREE_LIMIT,
            'used'         => (int) $user->free_financial_message_count,
            'expires_at'   => $user->subscription_expires_at,
            'plan_display' => $isPro ? 'HematWoy Pro' : 'Gratis (Freemium)',
        ];
    }
}
