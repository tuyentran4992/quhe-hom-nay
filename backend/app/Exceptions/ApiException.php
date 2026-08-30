<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lỗi API có chủ đích — render đúng error envelope §0.3 (specs/1.mvp/03-api.md):
 * { "error": { "code": ..., "message": ..., "details": ... } }.
 * Message + code lấy nguyên văn từ bảng mã lỗi; details = hằng số nghiệp vụ, không PII.
 */
class ApiException extends RuntimeException
{
    /** @param array<string, mixed>|null $details */
    public function __construct(
        private readonly string $apiCode,
        string $message,
        private readonly int $status,
        private readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public static function drawLimitReached(?string $nextDrawAtUtc): self
    {
        return new self(
            'DRAW_LIMIT_REACHED',
            'Hôm nay bạn đã gieo quẻ rồi. Quay lại sau 0h.',
            Response::HTTP_CONFLICT,
            ['next_draw_at' => $nextDrawAtUtc],
        );
    }

    public static function notFound(): self
    {
        return new self('NOT_FOUND', 'Không tìm thấy tài nguyên.', Response::HTTP_NOT_FOUND);
    }

    public static function validationFailed(array $errors): self
    {
        return new self(
            'VALIDATION_FAILED',
            'Dữ liệu không hợp lệ.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['errors' => $errors],
        );
    }

    public function apiCode(): string
    {
        return $this->apiCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function toEnvelope(): array
    {
        $error = ['code' => $this->apiCode, 'message' => $this->getMessage()];
        if ($this->details !== null) {
            $error['details'] = $this->details;
        }

        return ['error' => $error];
    }
}
