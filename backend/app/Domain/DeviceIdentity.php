<?php

namespace App\Domain;

/**
 * BE-2 — 02-db §8: device_id = 26 ký tự base32 CSPRNG, server sinh lần đầu.
 * Pure PHP (01 §2 kiến trúc hexagonal): không import facade/HTTP.
 */
final class DeviceIdentity
{
    /** Alphabet RFC4648 base32 (A-Z, 2-7) — đủ 32 ký tự cho 5 bit/char. */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const LENGTH = 26;

    public static function generate(): string
    {
        // CSPRNG: random_bytes → unpack 5-bit, base32, cắt 26 ký tự.
        $bytes = random_bytes(20); // 160 bit >= 26*5=130
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $id = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $id .= self::ALPHABET[bindec(substr($bits, $i * 5, 5))];
        }

        return $id;
    }

    public static function isValid(?string $id): bool
    {
        return is_string($id) && preg_match('/^[A-Z2-7]{26}$/', $id) === 1;
    }

    private function __construct()
    {
    }
}
