<?php

namespace App\Services;

use App\Http\ApiError;
use Illuminate\Http\JsonResponse;

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
        // CFG-BE: cả price_vnd lẫn con số trong message đều suy ra từ
        // project.php — boss đổi unlock_vnd là payload + message đổi theo, không sửa code.
        $price = (int) config('project.price.unlock_vnd');

        return new self(
            402, 'UNLOCK_REQUIRED',
            'Chủ đề này cần mở khóa '.number_format($price, 0, ',', '.').'đ.', // payload mẫu §5
            ['topic' => $topic, 'price_vnd' => $price,
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

    /**
     * QUOTA-N/Q2 (card t_1b5a0c23, D2.3) — quẻ này đã dùng hết N lượt luận sâu
     * THAT (đếm done !from_cache, QuotaService). 429 nhưng code KHÁC biệt với
     * AI_COOLDOWN/AI_GLOBAL_CAP — FE/QA map theo chuỗi 'quota_exceeded' nguyên
     * văn card chốt (viết thường, không phải enum 402 — paywall OFF).
     */
    public static function quotaExceeded(int $max, int $used): self
    {
        return new self(
            429, 'quota_exceeded',
            'Quẻ này đã dùng hết '.$max.' lượt luận sâu.',
            ['max_deep_reads_per_draw' => $max, 'used' => $used, 'remaining' => max(0, $max - $used)],
        );
    }

    public static function conflict(): self
    {
        return new self(
            409, 'IDEMPOTENCY_CONFLICT',
            'Cùng idempotency_key nhưng nội dung khác — client phải sinh key mới.',
        );
    }

    /**
     * REVIEW-LUAN (t_8aa93a01) — chủ đề này của quẻ này đã luận xong 1 lượt:
     * POST mới bị khóa ở giai đoạn này (boss GO 02/09). FE map code này
     * → chuyển sang chế độ "Xem lại" (GET /api/ai/interpretations/saved).
     */
    public static function alreadyDone(): self
    {
        return new self(
            409, 'AI_ALREADY_DONE',
            'Chủ đề này đã được luận cho quẻ hiện tại. Bạn bấm Xem lại để đọc lại bài đã lưu.',
        );
    }

    public function toResponse(): JsonResponse
    {
        return ApiError::json($this->status, $this->errorCode, $this->getMessage(), $this->details);
    }
}
