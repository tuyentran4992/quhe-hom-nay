<?php

namespace App\Services;

use App\Http\ApiError;

/**
 * BE-2 — lỗi nghiệp vụ #7/#9 theo envelope 03-api §0.3. Tách riêng khỏi
 * InterpretationException: mỗi service 1 trách nhiệm, mã lỗi không lẫn nhau.
 */
class PaymentException extends \RuntimeException
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

    public static function conflict(): self
    {
        return new self(409, 'IDEMPOTENCY_CONFLICT',
            'Cùng idempotency_key nhưng nội dung khác — client phải sinh key mới.');
    }

    public static function notFound(string $msg = 'Không tìm thấy đơn.'): self
    {
        return new self(404, 'NOT_FOUND', $msg);
    }

    public function toResponse(): \Illuminate\Http\JsonResponse
    {
        return ApiError::json($this->status, $this->errorCode, $this->getMessage(), $this->details);
    }
}
