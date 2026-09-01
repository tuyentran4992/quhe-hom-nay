<?php

namespace App\Services;

use App\Domain\RouterPrompt;
use App\Domain\Rules;
use Illuminate\Support\Facades\Http;

/**
 * BE-2 — adapter AI-Box (spec 01 §2/§5). Chỉ RunAiBoxJob được instantiate class này;
 * controller/service khác gọi thẳng provider = vi phạm kiến trúc đã khóa.
 * Timeout 1 retry nằm ở tầng job (Rules::C-04) — client chỉ gọi ĐÚNG 1 lần/request.
 */
class AiBoxClient
{
    /**
     * LUAN-V3 (SPEC §5.2, ADR-V3-01) — bước ROUTER danh mục: call NHỎ chạy trước
     * bước luận trong RunAiBoxJob. Cùng client/base_url/key, model riêng
     * (aibox.router_model, rỗng → fallback model luận), temperature 0, max_tokens 8,
     * timeout RIÊNG 10s. LỖI/mạng/JSON rác → trả NULL nội bộ — TUYỆT ĐỐI không
     * throw, không làm fail job luận (worker đi nhánh fallback T-D).
     * Nợ ghi nhận (§5.3): model router đổi về sau → replay lý thuyết lệch; MVP
     * không replay nên chấp nhận.
     */
    public function routeTopic(string $question): ?string
    {
        $base = rtrim((string) config('aibox.base_url'), '/');
        $key = (string) config('aibox.api_key');
        if ($key === '') {
            return null; // chưa cấu hình = router lỗi → fallback im lặng CÓ chủ đích (T-D)
        }
        $model = (string) (config('aibox.router_model') ?: config('aibox.model'));

        try {
            $res = Http::timeout(Rules::AI_ROUTER_TIMEOUT_SECONDS)
                ->withToken($key)
                ->post($base.'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => RouterPrompt::forQuestion($question)]],
                    'temperature' => 0,
                    'max_tokens' => 8,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('aibox.router.failed', ['err' => $e->getMessage()]);

            return null;
        }

        if (! $res->successful()) {
            logger()->warning('aibox.router.failed', ['status' => $res->status()]);

            return null;
        }
        $text = (string) $res->json('choices.0.message.content', '');

        // log ĐẾM ĐƯỢC cho AC-1 (§5.2): phân biệt với aibox.request.sent của bước luận.
        logger()->info('aibox.router.sent', ['model' => $model]);

        return trim($text) === '' ? null : RouterPrompt::parse($text);
    }

    /**
     * @param  array{role:string,content:string}  $messages
     * @return string văn bản trả về
     * @throws AiBoxException AI_TIMEOUT | AI_UPSTREAM
     */
    public function complete(array $messages): string
    {
        $base = rtrim((string) config('aibox.base_url'), '/');
        $key = (string) config('aibox.api_key');
        if ($key === '') {
            throw new AiBoxException('AI_UPSTREAM', 'AIBOX_API_KEY chưa cấu hình (env).');
        }

        try {
            $res = Http::timeout(Rules::AI_TIMEOUT_SECONDS)
                ->withToken($key)
                ->post($base.'/chat/completions', [
                    'model' => config('aibox.model'),
                    'messages' => $messages,
                    'temperature' => 0.7,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // cURL 28 = "Operation timed out" — message khác nhau theo driver, bắt cả hai
            $m = strtolower($e->getMessage());
            $code = (str_contains($m, 'timeout') || str_contains($m, 'timed out')) ? 'AI_TIMEOUT' : 'AI_UPSTREAM';
            throw new AiBoxException($code, 'AI-Box không phản hồi: '.$e->getMessage());
        }

        if (! $res->successful()) {
            throw new AiBoxException('AI_UPSTREAM', 'AI-Box HTTP '.$res->status());
        }

        $text = (string) $res->json('choices.0.message.content', '');
        if (trim($text) === '') {
            throw new AiBoxException('AI_UPSTREAM', 'AI-Box trả rỗng (không có choices[0].message.content).');
        }

        // log ĐẾM ĐƯỢC cho AC-1/AC-2: mỗi dòng này = 1 lần gọi provider thật.
        logger()->info('aibox.request.sent', ['model' => config('aibox.model')]);

        return trim($text);
    }
}
