<?php

namespace App\Domain;

use App\Models\Draw;
use App\Models\HaoText;
use App\Models\Hexagram;

/**
 * SPEC-3XU "luật luận" (01-overview §4bis): 0 hào động → chỉ ĐẠI Ý quẻ gốc;
 * ≥1 hào động → ĐẠI Ý + TỪ HÀO từng hào động (xếp sơ→thượng). Một trách nhiệm
 * duy nhất — không gọi AI, không tự tính quẻ biến (chống god class).
 *
 * CẤM: quẻ biến và nội dung của nó xuất hiện ở đây (§4bis + F10 QA).
 */
final class Luan
{
    /**
     * Mảng từ hào của MỘT draw theo luật §4bis (FE #10/S3 render; #5 prompt dùng
     * cùng nguồn để không lệch hai đường).
     *
     * @return array<int, array{vi:int,hao:string,han:string,quoc_am:string,nghia:string}>
     */
    public function haoTextsForDraw(Draw $draw): array
    {
        $changing = array_values(array_map(intval(...), $draw->changing_lines ?? []));
        if ($changing === []) {
            return [];
        }

        return HaoText::query()
            ->where('hexagram_id', $draw->hexagram_id)
            ->orderBy('vi')
            ->get()
            ->whereIn('vi', $changing)
            ->map(static fn (HaoText $h): array => [
                'vi' => (int) $h->vi,
                'hao' => (string) $h->hao,
                'han' => (string) $h->han,
                'quoc_am' => (string) $h->quoc_am,
                'nghia' => (string) $h->nghia,
            ])
            ->values()
            ->all();
    }

    /** Block nội dung luận (dòng cuối user prompt — 02-db §8, BẤT BIẾN định vị). */
    public function block(Hexagram $hexagram, array $haoTexts): string
    {
        $out = 'LUAN (' . $hexagram->id . ' ' . $hexagram->han . ' ' . $hexagram->ten . '): '
            . trim((string) $hexagram->dai_ci);
        foreach ($haoTexts as $h) {
            $out .= "\n- " . $h['hao'] . ': ' . trim($h['han'])
                . ' | ' . trim($h['quoc_am']) . ' | ' . trim($h['nghia']);
        }

        return $out;
    }

    /** Bản đồ hexagram_id → toàn bộ 6 từ hào (#4 history — tránh N+1; FE lọc theo
     * changing_lines từng draw, đúng tinh thần "FE render trực tiếp").
     *
     * @param int[] $hexagramIds
     * @return array<int, array<int, array{vi:int,hao:string,han:string,quoc_am:string,nghia:string}>>
     */
    public function mapForHexagrams(array $hexagramIds): array
    {
        $out = [];
        $rows = HaoText::query()
            ->whereIn('hexagram_id', $hexagramIds)
            ->orderBy('vi')
            ->get();

        foreach ($rows as $h) {
            $out[(int) $h->hexagram_id][] = [
                'vi' => (int) $h->vi,
                'hao' => (string) $h->hao,
                'han' => (string) $h->han,
                'quoc_am' => (string) $h->quoc_am,
                'nghia' => (string) $h->nghia,
            ];
        }

        return $out;
    }
}
