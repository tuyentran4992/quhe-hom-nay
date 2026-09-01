<?php

namespace App\Domain;

/**
 * LUAN-V2 (SPEC-LUAN-V2 §3) — luật Biện quẻ Chu Hy, 7 case (0..6 hào động).
 * Pure PHP: không DB/facade/HTTP (01 §2) — chỉ đọc file dữ liệu seed tĩnh
 * (hexagrams.json) để suy quẻ biến + lời dụng, theo tiền lệ HexagramRoller::findPattern.
 *
 * Hai nguyên tắc suy ra mọi case (§3.1):
 *  (1) động ÍT theo động, động NHIỀU theo tĩnh;
 *  (2) đồng hạng: TRÊN làm chủ khi đếm hào ĐỘNG, DƯỚI làm chủ khi đếm hào TĨNH.
 *
 * D2 (CEO chốt, anh Tuyền duyệt): quẻ biến vào prompt CHỈ case 3 + 6
 * (can_loi_bien=true); case 0/1/2/4/5 CẤM tuyệt đối — test chống leak giữ nguyên.
 */
final class BianRule
{
    /** @var array<string, array{id:int,ten:string,dungHao:?array{han:string,am:string,nghia:string}}>|null pattern "0,1,..." => row */
    private static ?array $patterns = null;

    /**
     * @param  int[]  $changingLines  vị trí 1-based (1..6), duy nhất (không cần sort)
     * @param  int|null  $hexagramId  quẻ gốc — cần cho case 6 (biệt lệ Càn/Khôn + tên quẻ biến)
     * @return array{
     *     n_dong: int,
     *     chu_tich: ?int,
     *     chu_tich_vi_tri: string,
     *     can_quese_goc: bool,
     *     can_loi_bien: bool,
     *     loi_luan: string
     * }
     */
    public static function quiTrinh(array $changingLines, ?int $hexagramId = null): array
    {
        $changing = array_values(array_map(intval(...), $changingLines));
        foreach ($changing as $pos) {
            if ($pos < 1 || $pos > 6) {
                throw new \InvalidArgumentException("vị trí hào động ngoài 1..6: ".var_export($pos, true));
            }
        }
        if (count($changing) !== count(array_unique($changing))) {
            throw new \InvalidArgumentException('vị trí hào động trùng nhau: '.implode(',', $changing));
        }
        sort($changing);
        $n = count($changing);

        return match ($n) {
            0 => [
                'n_dong' => 0, 'chu_tich' => null, 'chu_tich_vi_tri' => '',
                'can_quese_goc' => true, 'can_loi_bien' => false,
                'loi_luan' => 'Luận theo quẻ từ quẻ gốc. Không có hào động — không dẫn lời hào nào.',
            ],
            1 => [
                'n_dong' => 1, 'chu_tich' => $changing[0], 'chu_tich_vi_tri' => 'dong',
                'can_quese_goc' => false, 'can_loi_bien' => false,
                'loi_luan' => "Chỉ luận theo hào từ của hào động duy nhất (hào {$changing[0]}). KHÔNG dùng quẻ từ, KHÔNG dẫn lời quẻ biến.",
            ],
            2 => [
                'n_dong' => 2, 'chu_tich' => $changing[1], 'chu_tich_vi_tri' => 'trên',
                'can_quese_goc' => false, 'can_loi_bien' => false,
                'loi_luan' => "Luận theo hào từ của CẢ HAI hào động ({$changing[0]}, {$changing[1]}); hào TRÊN (hào {$changing[1]}) làm chủ — nặng ký hơn.",
            ],
            3 => [
                'n_dong' => 3, 'chu_tich' => null, 'chu_tich_vi_tri' => '',
                'can_quese_goc' => true, 'can_loi_bien' => true,
                'loi_luan' => 'Luận theo quẻ từ cả GỐC và BIẾN: gốc làm chủ (việc đang hỏi), biến làm ứng (chiều hướng kết cục — không phải định sẵn).',
            ],
            4 => self::theoTinh($changing, 'dưới', static fn (array $tinh) =>
                "4 hào động — theo luật động nhiều theo tĩnh: luận theo hào từ 2 hào TĨNH ({$tinh[0]}, {$tinh[1]}); hào DƯỚI (hào {$tinh[0]}) làm chủ. KHÔNG dẫn lời quẻ biến."),
            5 => self::theoTinh($changing, 'tinh', static fn (array $tinh) =>
                "5 hào động — luận theo hào từ của hào TĨNH duy nhất (hào {$tinh[0]}). KHÔNG dẫn lời quẻ biến."),
            6 => self::sauDong($hexagramId),
        };
    }

    /** Case 4/5 — động nhiều theo tĩnh: luận theo các hào TĨNH còn lại. */
    private static function theoTinh(array $changing, string $viTri, callable $loi): array
    {
        $tinh = array_values(array_diff([1, 2, 3, 4, 5, 6], $changing));

        return [
            'n_dong' => count($changing),
            'chu_tich' => $tinh[0], // đếm hào TĨNH → DƯỚI làm chủ (phần tử nhỏ nhất)
            'chu_tich_vi_tri' => $viTri,
            'can_quese_goc' => false, 'can_loi_bien' => false,
            'loi_luan' => $loi($tinh),
        ];
    }

    /** Case 6 — Càn/Khôn: lời DỤNG hào; quẻ thường: quẻ từ QUẺ BIẾN (D2 mở). */
    private static function sauDong(?int $hexagramId): array
    {
        $row = $hexagramId !== null ? static::rowById($hexagramId) : null;
        $dung = $row['dungHao'] ?? null;

        if ($dung !== null) {
            // Chỉ id1 (用九) / id2 (用六) có dungHao trong ban_goc — §3.3.
            return [
                'n_dong' => 6, 'chu_tich' => null, 'chu_tich_vi_tri' => '',
                'can_quese_goc' => false, 'can_loi_bien' => true,
                'loi_luan' => "Sáu hào đều động — dùng lời DỤNG của quẻ: {$dung['han']} / {$dung['am']} / {$dung['nghia']}. Đây là lời luận chính, không dùng quẻ từ gốc.",
            ];
        }

        $tenBien = '';
        if ($row !== null) {
            $bien = static::rowByBitmask(array_map(static fn (int $b) => $b ^ 1, static::bitmaskOf($row['id'])));
            $tenBien = isset($bien['ten']) ? ' ('.$bien['ten'].')' : '';
        }

        return [
            'n_dong' => 6, 'chu_tich' => null, 'chu_tich_vi_tri' => '',
            'can_quese_goc' => true, // quẻ từ vào prompt — nhưng là của BIẾN, không phải gốc (§3.2)
            'can_loi_bien' => true,
            'loi_luan' => "Sáu hào đều động — luận theo quẻ từ QUẺ BIẾN{$tenBien}. Quẻ gốc chỉ nêu tên, không luận.",
        ];
    }

    /** @return array<int, int> bitmask 6 hào (dưới→trên) của 1 quẻ id */
    private static function bitmaskOf(int $id): array
    {
        static $byId = null;
        if ($byId === null) {
            $byId = [];
            foreach (static::patterns() as $pat => $row) {
                $byId[$row['id']] = array_map(intval(...), explode(',', $pat));
            }
        }

        return $byId[$id] ?? array_fill(0, 6, 0);
    }

    /** @return array{id:int,ten:string,dungHao:?array}|null */
    private static function rowById(int $id): ?array
    {
        foreach (static::patterns() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /** @param  int[]  $bitmask  6 hào (dưới→trên) @return array{id:int,ten:string,dungHao:?array}|null */
    private static function rowByBitmask(array $bitmask): ?array
    {
        return static::patterns()[implode(',', $bitmask)] ?? null;
    }

    /** Lazy-load pattern "b,d,e,f,g,h" => {id,ten,dungHao} từ file seed tĩnh. */
    private static function patterns(): array
    {
        if (self::$patterns === null) {
            $rows = json_decode(
                (string) file_get_contents(__DIR__.'/../../database/data/hexagrams.json'),
                true
            ) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $pat = implode(',', array_map(intval(...), $r['lines'] ?? []));
                $banGoc = $r['banGoc'] ?? [];
                $out[$pat] = [
                    'id' => (int) $r['id'],
                    'ten' => (string) ($r['ten'] ?? ''),
                    'dungHao' => isset($banGoc['dungHao']['han']) ? [
                        'han' => (string) $banGoc['dungHao']['han'],
                        'am' => (string) ($banGoc['dungHao']['am'] ?? ''),
                        'nghia' => (string) ($banGoc['dungHao']['nghia'] ?? ''),
                    ] : null,
                ];
            }
            self::$patterns = $out;
        }

        return self::$patterns;
    }

    /** Test helper — reset cache file-read giữa các process data khác nhau. */
    public static function resetCacheForTest(): void
    {
        self::$patterns = null;
    }

    private function __construct()
    {
    }
}
