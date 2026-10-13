<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * EncryptionService
 *
 * Implements authenticated cryptography:
 * - Server-mediated storage encryption using AES-256-GCM.
 * - Zero-knowledge client encryption key derivation using PBKDF2 (210,000 iterations).
 * - Master PIN hashing and emergency recovery code management.
 */
class EncryptionService
{
    /**
     * Server-mediated protection for normal bot operations.
     * Protects raw database dumps while server remains a trusted boundary.
     */
    public function encryptForStorage(int $value): string
    {
        return $this->encryptWithKey($value, $this->storageKey());
    }

    /**
     * Decrypt integer financial value from storage payload.
     */
    public function decryptFromStorage(string $payload): int
    {
        return $this->decryptWithKey($payload, $this->storageKey());
    }

    /**
     * Set up user encryption credentials (salt, pin hash, and recovery code hash).
     *
     * @return array{recovery_code: string}
     */
    public function createCredentials(User $user, string $pin): array
    {
        $this->assertPin($pin);

        $recovery = strtoupper(bin2hex(random_bytes(8)));

        $user->forceFill([
            'pin_hash'           => Hash::make($pin),
            'recovery_code_hash' => Hash::make($recovery),
            'encryption_salt'    => base64_encode(random_bytes(16)),
            'pin_status'         => 'ACTIVE',
            'key_version'        => 1,
        ])->save();

        return ['recovery_code' => $recovery];
    }

    /**
     * Verify whether provided Master PIN matches user's hashed PIN.
     */
    public function verifyPin(User $user, string $pin): bool
    {
        $this->assertPin($pin);

        $valid = $user->pin_hash && Hash::check($pin, $user->pin_hash);
        if ($valid) {
            $user->forceFill(['last_pin_verified_at' => now()])->save();
        }

        return (bool) $valid;
    }

    /**
     * Verify emergency recovery code against one-way hash in database.
     */
    public function verifyRecovery(User $user, string $code): bool
    {
        return $user->recovery_code_hash && Hash::check(strtoupper($code), $user->recovery_code_hash);
    }

    /**
     * Encrypt an integer value using the user's PIN-derived encryption key.
     */
    public function encryptInt(User $user, string $pin, int $value): string
    {
        return $this->encryptWithKey($value, $this->key($user, $pin), $user->key_version);
    }

    /**
     * Decrypt an integer value using the user's PIN-derived encryption key.
     */
    public function decryptInt(User $user, string $pin, string $payload): int
    {
        return $this->decryptWithKey($payload, $this->key($user, $pin));
    }

    /**
     * Validate PIN/Passphrase length requirements (6 to 64 chars).
     *
     * @throws RuntimeException
     */
    public function assertPin(string $pin): void
    {
        if (strlen($pin) < 6 || strlen($pin) > 64) {
            throw new RuntimeException('PIN / Passphrase harus memiliki panjang antara 6 sampai 64 karakter.');
        }
    }

    /**
     * Derive AES-256 encryption key from user PIN and random salt via PBKDF2.
     */
    private function key(User $user, string $pin): string
    {
        if (!$user->encryption_salt) {
            throw new RuntimeException('Encryption credentials are not configured.');
        }

        return hash_pbkdf2(
            'sha256',
            $pin,
            base64_decode($user->encryption_salt, true),
            210000,
            32,
            true
        );
    }

    /**
     * Resolve the server storage encryption key from configuration/environment.
     */
    private function storageKey(): string
    {
        $configured = (string) config('app.finance_encryption_key', env('FINANCE_ENCRYPTION_KEY'));
        if ($configured === '') {
            throw new RuntimeException('FINANCE_ENCRYPTION_KEY must be configured.');
        }

        $decoded = base64_decode($configured, true);

        return $decoded !== false && strlen($decoded) === 32 ? $decoded : hash('sha256', $configured, true);
    }

    /**
     * Encrypt an integer value with AES-256-GCM authenticated cipher.
     */
    private function encryptWithKey(int $value, string $key, int $version = 1): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt((string) $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return json_encode([
            'v'          => $version,
            'iv'         => base64_encode($iv),
            'tag'        => base64_encode($tag),
            'ciphertext' => base64_encode($cipher),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Decrypt and verify payload with AES-256-GCM.
     */
    private function decryptWithKey(string $payload, string $key): int
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        foreach (['iv', 'tag', 'ciphertext'] as $field) {
            if (!isset($data[$field])) {
                throw new RuntimeException('Invalid encrypted payload.');
            }
        }

        $plain = openssl_decrypt(
            base64_decode($data['ciphertext'], true),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($data['iv'], true),
            base64_decode($data['tag'], true)
        );

        if ($plain === false || !preg_match('/^-?\d+$/', $plain)) {
            throw new RuntimeException('Unable to decrypt financial value.');
        }

        return (int) $plain;
    }
}
