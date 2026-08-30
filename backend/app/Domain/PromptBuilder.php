<?php

namespace App\Domain;

/**
 * BE-2 — dựng prompt luận sâu từ dữ liệu quẻ (03-api §6 yêu cầu markdown 200–400 từ).
 * Pure PHP: nhận mảng dữ liệu thuần, không import facade/HTTP (01 §2).
 * System prompt NHÚNG LUẬT WORDING 01 §1 (Wordguard::SYSTEM_PROMPT) — card BE-2 khóa.
 */
final class PromptBuilder
{
    /** @param array $hex 1 row bảng hexagrams (snake_case) */
    public static function userPrompt(array $hex, string $topic, array $changingLines): string
    {
        $topicLabel = match ($topic) {
            'duyen' => 'tình duyên',
            'tai_loc' => 'tài lộc',
            'xuat_hanh' => 'xuất hành',
            default => $topic,
        };

        $free = json_decode($hex['free_content'] ?? '{}', true) ?: [];
        $lines = implode(',', $changingLines ?: []);

        return implode("\n", [
            "Chủ đề luận sâu: {$topicLabel}.",
            'Quẻ gốc (Hán: '.($hex['han'] ?? '').', tên: '.($hex['ten'] ?? '').'): '.($hex['symbol'] ?? ''),
            'Đại ý: '.($hex['dai_ci'] ?? ''),
            'Từ khóa: '.implode(', ', (array) json_decode($hex['keywords'] ?? '[]', true) ?: []),
            'Luận hôm nay: '.($hex['luan_nay'] ?? ''),
            'Góc nhìn sẵn có về '.$topicLabel.': '.($free[static::freeKey($topic)] ?? '—'),
            $lines !== '' ? "Hào động (1-based từ dưới lên): {$lines} — ưu tiên luận theo tượng hào động." : 'Không có hào động — luận theo quẻ gốc.',
            'Viết bài luận sâu cho chủ đề trên, 200–400 từ, văn phong tham khảo văn hoá.',
        ]);
    }

    private static function freeKey(string $topic): string
    {
        return match ($topic) {
            'duyen' => 'tinhDuyen',
            'tai_loc' => 'taiLoc',
            'xuat_hanh' => 'congViec',
        };
    }

    private function __construct()
    {
    }
}
