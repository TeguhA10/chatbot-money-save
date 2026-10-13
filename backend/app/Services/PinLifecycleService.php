<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * PinLifecycleService
 *
 * Manages Master PIN setup, validation, and emergency recovery reset.
 */
class PinLifecycleService
{
    public function __construct(
        private readonly EncryptionService $encryption
    ) {}

    /**
     * Set up Master PIN for the user and generate a one-time emergency recovery code.
     *
     * @throws RuntimeException
     */
    public function setup(User $user, string $pin): string
    {
        if ($user->pin_status === 'ACTIVE') {
            throw new RuntimeException('PIN sudah aktif. Gunakan reset pin jika lupa.');
        }

        $credentials = $this->encryption->createCredentials($user, $pin);

        return $credentials['recovery_code'];
    }

    /**
     * Reset Master PIN using the emergency 16-character recovery code.
     *
     * @throws RuntimeException
     */
    public function reset(User $user, string $code, string $newPin): void
    {
        if (!$this->encryption->verifyRecovery($user, $code)) {
            throw new RuntimeException('Recovery Code tidak valid.');
        }

        $this->encryption->assertPin($newPin);

        DB::transaction(function () use ($user, $newPin) {
            $user->update([
                'pin_hash'              => Hash::make($newPin),
                'key_version'           => $user->key_version + 1,
                'last_pin_verified_at'  => now(),
            ]);
        });
    }
}
