<?php

namespace App\Services;

use App\Domain\RouterPrompt;
use App\Domain\Rules;
use Illuminate\Http\Client\ConnectionException;
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
     * (aibox.router_model, rỗng → Rules::AI_ROUTER_MODEL non-reasoning),
     * temperature 0, max_tokens Rules::AI_ROUTER_MAX_TOKENS, timeout RIÊNG 10s.
     * LỖI/mạng/JSON rác → trả NULL nội bộ — TUYỆT ĐỐI không
     * throw, không làm fail job luận (worker đi nhánh fallback T-D).
     * Nợ ghi nhận (§5.3): model router đổi về sau → replay lý thuyết lệch; MVP
     * không replay nên chấp nhận.
     *
     * BUG-V3-1 (card t_05d92158) — đường cũ `router_model ?: model luận` là hố
     * tử thần: model luận deploy = deepseek-v4-flash (reasoning) → lý lẽ ăn hết
     * 8 token, content='' → route null → 100% bài rơi T-D im lặng. Fallback mới
     * về Rules::AI_ROUTER_MODEL; CẤM fallback model luận khi router_model rỗng.
     * BUG-V3-3 — log aibox.router.sent đếm-mù (ghi trước parse, request 200 kể
     * cả khi nhãn bị cắt) thay bằng aibox.router.result SAU parse: route = giá
     * trị thật (label|null|UNCLEAR) + finish_reason để giám sát không mù.
     */
    public function routeTopic(string $question): ?string
    {
        $base = rtrim((string) config('aibox.base_url'), '/');
        $key = (string) config('aibox.api_key');
        if ($key === '') {
            return null; // chưa cấu hình = router lỗi → fallback im lặng CÓ chủ đích (T-D)
        }
        $model = trim((string) config('aibox.router_model')) ?: Rules::AI_ROUTER_MODEL;

        try {
            $res = Http::timeout(Rules::AI_ROUTER_TIMEOUT_SECONDS)
                ->withToken($key)
                ->post($base.'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => RouterPrompt::forQuestion($question)]],
                    'temperature' => 0,
                    'max_tokens' => Rules::AI_ROUTER_MAX_TOKENS,
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
        $route = trim($text) === '' ? null : RouterPrompt::parse($text);

        // ĐẾM ĐƯỢC cả THÀNH CÔNG lẫn GIÁ TRỊ (AC-1 + BUG-V3-3): 1 dòng = 1 call
        // THẬT có parse xong; route=null + finish=length = đúng chữ bệnh BUG-V3-1.
        // question KHÔNG bao giờ vào log (§5.2).
        logger()->info('aibox.router.result', [
            'model' => $model,
            'route' => $route,
            'finish' => $res->json('choices.0.finish_reason'),
        ]);

        return $route;
    }

    /**
     * @param  array{role:string,content:string}  $messages
     * @return string văn bản trả về
     *
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
        } catch (ConnectionException $e) {
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
