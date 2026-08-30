<?php

namespace App\Services;

/**
 * BE-2 — 03-api §5 bước (e): CHỖ DUY NHẤT được đụng provider (queue worker).
 * HTTP OpenAI-compatible /chat/completions qua config aibox (key từ env — cấm commit).
 * Lỗi provider → AiBoxException(code ∈ AI_TIMEOUT|AI_UPSTREAM) cho worker map.
 */
class AiBoxException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
