<?php

namespace App\Services;

/**
 * t_a0d9ee0f — 03-api §8: verify `X-PayOS-Signature` = HMAC SHA256 hex của RAW body
 * với PAYOS_WEBHOOK_SECRET (config/payos.webhook_secret). 1 trách nhiệm: so chữ ký.
 * fail-closed: secret chưa cấu hình (rỗng) → MỌI webhook 401, kể cả chữ ký "khớp"
 * với secret rỗng — PAY-01 đổ key thật là bật đường, không đổi code.
 */
final class PayOsSignatureVerifier
{
    public function verify(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('payos.webhook_secret', '');
        if ($secret === '' || $signature === null) {
            return false;
        }
        $given = strtolower(trim($signature));
        if ($given === '' || ! preg_match('/^[0-9a-f]{64}$/', $given)) {
            return false; // bản thật luôn 64 hex; chuỗi lạ (QA gửi 'sai') khỏi tốn hash
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $given);
    }
}
