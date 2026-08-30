<?php

namespace App\Services;

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
