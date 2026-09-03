<?php

namespace App\Services;

use App\Domain\JudgePrompt;
use App\Domain\RouterPrompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * BE-2 — adapter AI-Box (spec 01 §2/§5). Chỉ RunAiBoxJob được instantiate class này;
 * controller/service khác gọi thẳng provider = vi phạm kiến trúc đã khóa.
 * Timeout 1 retry nằm ở tầng job (C-04, project.php ai.*) — client chỉ gọi ĐÚNG 1 lần.
 */
class AiBoxClient
{
    /**
     * LUAN-V3 (SPEC §5.2, ADR-V3-01) — bước ROUTER danh mục: call NHỎ chạy trước
     * bước luận trong RunAiBoxJob. Cùng client/base_url/key, model riêng
     * (aibox.router_model, rỗng → project.php ai.router_model non-reasoning),
     * temperature 0, max_tokens ai.router_max_tokens, timeout RIÊNG ai.router_timeout_seconds.
     * LỖI/mạng/JSON rác → trả NULL nội bộ — TUYỆT ĐỐI không
     * throw, không làm fail job luận (worker đi nhánh fallback T-D).
     * Nợ ghi nhận (§5.3): model router đổi về sau → replay lý thuyết lệch; MVP
     * không replay nên chấp nhận.
     *
     * BUG-V3-1 (card t_05d92158) — đường cũ `router_model ?: model luận` là hố
     * tử thần: model luận deploy = deepseek-v4-flash (reasoning) → lý lẽ ăn hết
     * 8 token, content='' → route null → 100% bài rơi T-D im lặng. Fallback mới
     * về project.php ai.router_model; CẤM fallback model luận khi router_model rỗng.
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
        $model = trim((string) config('aibox.router_model')) ?: (string) config('project.ai.router_model');

        try {
            $res = Http::timeout((int) config('project.ai.router_timeout_seconds'))
                ->withToken($key)
                ->post($base.'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => RouterPrompt::forQuestion($question)]],
                    'temperature' => 0,
                    'max_tokens' => (int) config('project.ai.router_max_tokens'),
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
     * QUOTA-N/Q3 (card t_1bb07a82) — bước PHÁN QUYẾT paraphrase: 1 goi nho
     * (transport y het router: model ai.router_model non-reasoning, temp 0,
     * max_tokens ai.router_max_tokens, timeout ai.router_timeout_seconds) xuat
     * DU_GIONG|KHAC|UNCLEAR. KHONG phai RouterPrompt — noi dung la
     * JudgePrompt::forPair (marker 'Hỏi lại' de test phan biet voi router).
     *
     * D4 fail-open: loi mang / HTTP lei / content ranh → NULL noi bo (KHONG
     * throw — giong routeTopic, judge hong khong duoc lam hong yeu cau);
     * LlmParaphraseJudge dich NULL → UNCLEAR → hoi that, tinh luot.
     * Key trong → NULL (chua cau hinh = khong phan quyet duoc).
     */
    public function judgeParaphrase(string $previousQuestion, string $newQuestion): ?string
    {
        $base = rtrim((string) config('aibox.base_url'), '/');
        $key = (string) config('aibox.api_key');
        if ($key === '') {
            return null;
        }
        $model = trim((string) config('aibox.router_model')) ?: (string) config('project.ai.router_model');

        try {
            $res = Http::timeout((int) config('project.ai.router_timeout_seconds'))
                ->withToken($key)
                ->post($base.'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => JudgePrompt::forPair($previousQuestion, $newQuestion)]],
                    'temperature' => 0,
                    'max_tokens' => (int) config('project.ai.router_max_tokens'),
                ]);
        } catch (\Throwable $e) {
            logger()->warning('aibox.judge.failed', ['err' => $e->getMessage()]);

            return null;
        }

        if (! $res->successful()) {
            logger()->warning('aibox.judge.failed', ['status' => $res->status()]);

            return null;
        }
        $text = (string) $res->json('choices.0.message.content', '');
        $verdict = trim($text) === '' ? 'UNCLEAR' : JudgePrompt::parse($text);

        // Dem duoc ca phan quyet lung (BUG-V3-3 hoc le): 1 dong = 1 call that;
        // finish=length voi model reasoning = tin hieu budget token khong du.
        // HAI CAU HOI khong bao gio vao log (PII — cung le §5.2 nhu router).
        logger()->info('aibox.judge.result', [
            'model' => $model,
            'verdict' => $verdict,
            'finish' => $res->json('choices.0.finish_reason'),
        ]);

        return trim($text) === '' ? null : $text;
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
            $res = Http::timeout((int) config('project.ai.timeout_seconds'))
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
