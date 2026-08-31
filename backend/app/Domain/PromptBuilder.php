<?php

namespace App\Domain;

/**
 * BE-2 — dựng prompt luận sâu từ dữ liệu quẻ (03-api §6 yêu cầu markdown 200–400 từ).
 * Pure PHP: nhận mảng dữ liệu thuần, không import facade/HTTP (01 §2).
 * System prompt NHÚNG LUẬT WORDING 01 §1 (Wordguard::SYSTEM_PROMPT) — card BE-2 khóa.
 */
final class PromptBuilder
{
    /**
     * @param array $hex 1 row bảng hexagrams (snake_case)
     * @param int[] $changingLines vị trí 1-based (hào động)
     * @param array[] $haoTexts SPEC-3XU §4bis: từ hào của các hào động
     *        ({vi,hao,han,quoc_am,nghia}) — RỖNG khi 0 hào động → chỉ đại ý quẻ gốc.
     *        CẤM truyền nội dung quẻ biến (§4bis — quẻ biến lưu DB, không vào prompt).
     */
    public static function userPrompt(array $hex, string $topic, array $changingLines, array $haoTexts = []): string
    {
        $topicLabel = match ($topic) {
            'duyen' => 'tình duyên',
            'tai_loc' => 'tài lộc',
            'xuat_hanh' => 'xuất hành',
            default => $topic,
        };

        // QA MERGE SHIM (t_5cd31bb9): BE-1 model cast JSON column thanh array,
        // BE-2 PromptBuilder doi string. Chap nhan ca hai — dev-lead ghe 1 kieu khi merge main.
        $free = is_array($hex['free_content'] ?? null) ? $hex['free_content'] : (json_decode((string) ($hex['free_content'] ?? '{}'), true) ?: []);
        $kw = is_array($hex['keywords'] ?? null) ? $hex['keywords'] : (json_decode((string) ($hex['keywords'] ?? '[]'), true) ?: []);
        $lines = implode(',', $changingLines ?: []);

        // SPEC-3XU §4bis: ≥1 hào động → ĐẠI Ý quẻ gốc + TỪ HÀO từng hào động
        // (han + quốc âm + nghĩa), xếp sơ→thượng. 0 hào động → chỉ đại ý.
        // $haoTexts đã được Luan lọc theo hào động TRƯỚC khi vào đây — PromptBuilder
        // không tự tra DB (1 trách nhiệm), giữ pure.
        $yaoBlock = [];
        foreach ($haoTexts as $t) {
            $yaoBlock[] = sprintf(
                'Hào động vi%d (%s) — Hán: %s | Quốc âm: %s | Nghĩa: %s',
                (int) ($t['vi'] ?? 0),
                (string) ($t['hao'] ?? ''),
                trim((string) ($t['han'] ?? '')),
                trim((string) ($t['quoc_am'] ?? '')),
                trim((string) ($t['nghia'] ?? '')),
            );
        }

        return implode("\n", array_merge([
            "Chủ đề luận sâu: {$topicLabel}.",
            'Quẻ gốc (Hán: '.($hex['han'] ?? '').', tên: '.($hex['ten'] ?? '').'): '.($hex['symbol'] ?? ''),
            'Đại ý: '.($hex['dai_ci'] ?? ''),
            'Từ khóa: '.implode(', ', (array) $kw),
            'Luận hôm nay: '.($hex['luan_nay'] ?? ''),
            'Góc nhìn sẵn có về '.$topicLabel.': '.($free[static::freeKey($topic)] ?? '—'),
            $lines !== '' ? "Hào động (1-based từ dưới lên): {$lines} — ưu tiên luận theo tượng hào động." : 'Không có hào động — luận theo quẻ gốc.',
        ], $yaoBlock, [
            'Viết bài luận sâu cho chủ đề trên, 200–400 từ, văn phong tham khảo văn hoá.',
        ]));
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
