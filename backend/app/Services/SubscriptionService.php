<?php
namespace App\Services;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class SubscriptionService
{
    public const FREE_LIMIT = 100;
    public function canRecord(User $user): bool { return $user->hasActivePremium() || $user->free_financial_message_count < self::FREE_LIMIT; }
    public function consume(User $user,string $messageId,int $transactionsRecorded=1): int
    {
        return DB::transaction(function () use($user,$messageId,$transactionsRecorded) {
            $locked=User::whereKey($user->jid)->lockForUpdate()->firstOrFail();
            if (UsageLog::where('message_id',$messageId)->exists()) return self::FREE_LIMIT-$locked->free_financial_message_count;
            if (!$locked->hasActivePremium()) {
                if ($locked->free_financial_message_count >= self::FREE_LIMIT) throw new RuntimeException('FREE_QUOTA_EXHAUSTED');
                $locked->increment('free_financial_message_count');
            }
            UsageLog::create(['user_jid'=>$locked->jid,'message_id'=>$messageId,'transactions_recorded'=>$transactionsRecorded]);
            $user->refresh(); return max(0,self::FREE_LIMIT-$locked->free_financial_message_count);
        });
    }
    public function status(User $user): array { $user->refresh(); return ['tier'=>$user->hasActivePremium()?'PREMIUM':'FREE','remaining'=>max(0,self::FREE_LIMIT-$user->free_financial_message_count),'expires_at'=>$user->subscription_expires_at]; }
}
