<?php

namespace App\Services;

/**
 * BE-2 — lỗi nghiệp vụ #5 kèm đúng mã HTTP + error.code 03-api §0.3.
 * Controller chỉ việc ->toResponse(); không tự map ở từng endpoint (chống lệch envelope).
 */
class InterpretationException extends \RuntimeException
{
    /** Exception gốc đã có $message — không redeclare; chỉ giữ status/code/details. */
    private function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
        private readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public static function validation(array $errors): self
    {
        return new self(422, 'VALIDATION_FAILED', 'Dữ liệu không hợp lệ.', ['errors' => $errors]);
    }

    public static function notFound(string $msg = 'Không tìm thấy dữ liệu.'): self
    {
        return new self(404, 'NOT_FOUND', $msg);
    }

    public static function unlockRequired(string $topic): self
    {
        return new self(
            402, 'UNLOCK_REQUIRED',
            'Chủ đề này cần mở khóa 29.000đ.', // payload mẫu §5
            ['topic' => $topic, 'price_vnd' => \App\Domain\Rules::PRICE_UNLOCK_VND,
                'payment_create_url' => '/api/payments/create'],
        );
    }

    public static function cooldown(int $retryAfterSeconds): self
    {
        return new self(
            429, 'AI_COOLDOWN',
            'Bạn vừa xin luận giải, nghỉ tay 90 giây đã.', // payload mẫu §5
            ['retry_after_seconds' => $retryAfterSeconds],
        );
    }

    public static function globalCap(): self
    {
        return new self(
            429, 'AI_GLOBAL_CAP',
            'Hàng chờ luận giải đang dày. Vui lòng thử lại sau ít phút.',
        );
    }

    public static function conflict(): self
    {
        return new self(
            409, 'IDEMPOTENCY_CONFLICT',
            'Cùng idempotency_key nhưng nội dung khác — client phải sinh key mới.',
        );
    }

    public function toResponse(): \Illuminate\Http\JsonResponse
    {
        return \App\Http\ApiError::json($this->status, $this->errorCode, $this->getMessage(), $this->details);
    }
}
