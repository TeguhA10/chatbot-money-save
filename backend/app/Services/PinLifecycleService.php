<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
class PinLifecycleService
{
    public function __construct(private readonly EncryptionService $encryption) {}
    public function setup(User $user,string $pin): string { if($user->pin_status === 'ACTIVE') throw new RuntimeException('PIN sudah aktif. Gunakan reset pin jika lupa.'); return $this->encryption->createCredentials($user,$pin)['recovery_code']; }
    public function reset(User $user,string $code,string $newPin): void
    {
        if(!$this->encryption->verifyRecovery($user,$code)) throw new RuntimeException('Recovery Code tidak valid.');
        $this->encryption->assertPin($newPin);
        DB::transaction(function () use($user,$newPin) { $user->update(['pin_hash'=>Hash::make($newPin),'key_version'=>$user->key_version+1,'last_pin_verified_at'=>now()]); });
    }
}
