<?php

namespace App\Domain;

/**
 * F7-BE (ADR-002 §3) — token chia sẻ base62(10) CSPRNG, thuần PHP, pattern
 * DeviceIdentity (final class, không facade/HTTP).
 * E4 SPEC-THE: 62^10 ≈ 8.4e17 — không brute-force; token KHÔNG suy ra draw_id.
 */
final class ShareToken
{
    public const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public const LENGTH = 10;

    public static function generate(): string
    {
        // CSPRNG: 64 bytes → modulo-free rejection (256 % 62 = 8 → bỏ 8 giá trị
        // cuối mỗi block để phân bố ĐỀU tuyệt đối, không chệch chữ số đầu).
        $out = '';
        while (strlen($out) < self::LENGTH) {
            foreach (str_split(random_bytes(64)) as $byte) {
                $v = ord($byte);
                if ($v >= 248) {
                    continue; // rejection sampling: 62*4 = 248
                }
                $out .= self::ALPHABET[$v % 62];
                if (strlen($out) === self::LENGTH) {
                    break;
                }
            }
        }

        return $out;
    }

    public static function isValid(?string $token): bool
    {
        return is_string($token) && preg_match('/^[0-9A-Za-z]{10}$/', $token) === 1;
    }

    private function __construct()
    {
    }
}
