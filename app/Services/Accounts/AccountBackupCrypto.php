<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use JsonException;
use RuntimeException;
use Throwable;

/** Versioned password-derived authenticated-encryption envelope. */
final class AccountBackupCrypto
{
    public const FORMAT = 'boardprep-encrypted-account-backup';
    public const SCHEMA_VERSION = 1;
    public const MAX_ARCHIVE_BYTES = 67_108_864;

    public static function available(): bool
    {
        return extension_loaded('sodium')
            && function_exists('sodium_crypto_pwhash')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')
            && defined('SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE')
            && defined('SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE')
            && defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')
            && defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')
            && defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES')
            && defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES')
            && defined('SODIUM_CRYPTO_PWHASH_SALTBYTES');
    }

    public static function encrypt(string $plaintext, string $passphrase): array
    {
        self::requireAvailable();
        self::validatePassphrase($passphrase);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        if (strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES
            || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new RuntimeException('BACKUP_RANDOMNESS_FAILED: cryptographic randomness returned an invalid length.');
        }

        $crypto = [
            'kdf' => 'argon2id13',
            'opslimit' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            'memlimit' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            'salt' => base64_encode($salt),
            'aead' => 'xchacha20poly1305-ietf',
            'nonce' => base64_encode($nonce),
        ];
        $header = [
            'format' => self::FORMAT,
            'schema_version' => self::SCHEMA_VERSION,
            'created_at' => gmdate(DATE_ATOM),
            'crypto' => $crypto,
        ];

        $key = '';
        try {
            $key = sodium_crypto_pwhash(
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
                $passphrase,
                $salt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
            $aad = self::encode($header, 'BACKUP_FORMAT_UNSUPPORTED');
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
            return $header + ['payload' => base64_encode($ciphertext)];
        } catch (Throwable $exception) {
            throw new RuntimeException('BACKUP_ENCRYPTION_FAILED: authenticated encryption could not be completed.', 0, $exception);
        } finally {
            if ($key !== '' && function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
        }
    }

    /** @return array<string,mixed> Decrypted payload; callers must validate its schema. */
    public static function decrypt(string $archive, string $passphrase): array
    {
        self::requireAvailable();
        self::validatePassphrase($passphrase);
        if (strlen($archive) === 0 || strlen($archive) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: encrypted archive size is invalid.');
        }
        try {
            $envelope = json_decode($archive, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: encrypted archive is malformed.', 0, $exception);
        }
        if (!is_array($envelope)
            || array_diff(['format', 'schema_version', 'created_at', 'crypto', 'payload'], array_keys($envelope)) !== []
            || count($envelope) !== 5
            || ($envelope['format'] ?? null) !== self::FORMAT
            || ($envelope['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || !is_string($envelope['created_at'] ?? null)
            || !is_array($envelope['crypto'] ?? null)
            || !is_string($envelope['payload'] ?? null)) {
            throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: encrypted archive header is unsupported.');
        }

        $crypto = $envelope['crypto'];
        $requiredCrypto = ['kdf', 'opslimit', 'memlimit', 'salt', 'aead', 'nonce'];
        if (array_diff($requiredCrypto, array_keys($crypto)) !== []
            || count($crypto) !== count($requiredCrypto)
            || ($crypto['kdf'] ?? null) !== 'argon2id13'
            || ($crypto['aead'] ?? null) !== 'xchacha20poly1305-ietf'
            || ($crypto['opslimit'] ?? null) !== SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE
            || ($crypto['memlimit'] ?? null) !== SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE) {
            throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: backup cryptographic parameters are unsupported.');
        }
        $salt = base64_decode((string) $crypto['salt'], true);
        $nonce = base64_decode((string) $crypto['nonce'], true);
        $ciphertext = base64_decode((string) $envelope['payload'], true);
        if (!is_string($salt) || strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES
            || !is_string($nonce) || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            || !is_string($ciphertext) || strlen($ciphertext) < SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: encrypted archive is truncated or malformed.');
        }

        $header = [
            'format' => self::FORMAT,
            'schema_version' => self::SCHEMA_VERSION,
            'created_at' => $envelope['created_at'],
            'crypto' => [
                'kdf' => 'argon2id13',
                'opslimit' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                'memlimit' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                'salt' => base64_encode($salt),
                'aead' => 'xchacha20poly1305-ietf',
                'nonce' => base64_encode($nonce),
            ],
        ];
        $key = '';
        $plaintext = '';
        try {
            $key = sodium_crypto_pwhash(
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
                $passphrase,
                $salt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                self::encode($header, 'BACKUP_FORMAT_UNSUPPORTED'),
                $nonce,
                $key
            );
            if (!is_string($plaintext)) {
                throw new RuntimeException('BACKUP_AUTHENTICATION_FAILED: passphrase or archive authentication is invalid.');
            }
            try {
                $payload = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('BACKUP_AUTHENTICATION_FAILED: authenticated archive payload is malformed.', 0, $exception);
            }
            if (!is_array($payload)) {
                throw new RuntimeException('BACKUP_AUTHENTICATION_FAILED: authenticated archive payload is malformed.');
            }
            return $payload;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('BACKUP_AUTHENTICATION_FAILED: passphrase or archive authentication is invalid.', 0, $exception);
        } finally {
            if ($key !== '' && function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
            if (is_string($plaintext) && $plaintext !== '' && function_exists('sodium_memzero')) {
                sodium_memzero($plaintext);
            }
        }
    }

    public static function validatePassphrase(string $passphrase): void
    {
        if (strlen($passphrase) < 16 || strlen($passphrase) > 1024) {
            throw new RuntimeException('BACKUP_SECRET_INVALID: supply a 16–1024 byte passphrase through a private file descriptor.');
        }
    }

    private static function requireAvailable(): void
    {
        if (!self::available()) {
            throw new RuntimeException('DEPENDENCY_MISSING: PHP ext-sodium with Argon2id and XChaCha20-Poly1305 is required.');
        }
    }

    private static function encode(array $value, string $category): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($category . ': backup metadata could not be encoded.', 0, $exception);
        }
    }
}
