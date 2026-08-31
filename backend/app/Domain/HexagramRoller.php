<?php

namespace App\Domain;

/**
 * Roller — C-09 (03-api §3.1, boss chốt 31/08 quay về 3 xu): mỗi hào = 3 đồng
 * xu độc lập sấp=2/ngửa=3 → tổng ∈ {6,7,8,9} (12.5/37.5/37.5/12.5). Cài đặt
 * gieo ở App\Domain\CoinFlip (1 trách nhiệm); class này giữ API đối chiếu
 * changingLines/toBitmask + replay/quẻ biến §4bis. CSPRNG, không facade/HTTP.
 */
class HexagramRoller
{
    public const STATIC_YANG = 7;
    public const STATIC_YIN = 8;
    public const MOVING_YANG = 9;
    public const MOVING_YIN = 6;

    /** @return int[] độ dài 6, giá trị ∈ {6,7,8,9}, chỉ số 0 = hào dưới cùng */
    public function roll(): array
    {
        // 05 E7/QA-0: preview deterministic — QA_MOCK_LINES = JSON 6 số ∈{6,7,8,9}.
        // CHỈ bật khi app.env != production; input hỏng → gieo thật (bỏ qua mock),
        // không phải code path production.
        $mocked = self::mockLines();
        if ($mocked !== null) {
            return $mocked;
        }

        $lines = [];
        for ($i = 0; $i < 6; $i++) {
            $lines[] = $this->rollOneLine();
        }

        return $lines;
    }

    /** Gieo 1 hào (3 xu độc lập, 18 lần random_int/quẻ tổng). */
    public function rollOneLine(): int
    {
        return CoinFlip::flipLine();
    }

    /**
     * Hook QA-0/preview (05 E7): QA_MOCK_LINES = JSON mảng 6 số ∈ {6,7,8,9}.
     * Trả null (gieo thật) khi env production, biến trống, hoặc JSON sai shape.
     */
    public static function mockLines(): ?array
    {
        // Guard không phụ thuộc container (pure-unit gọi được): chỉ tắt khi APP_ENV
        // thật sự production (phpunit.xml set testing; .env production trên server).
        $env = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')));
        if ($env === 'production') {
            return null;
        }
        $raw = (string) (getenv('QA_MOCK_LINES') ?: ($_ENV['QA_MOCK_LINES'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || count($decoded) !== 6) {
            return null;
        }
        foreach ($decoded as $v) {
            if (!is_int($v) || $v < 6 || $v > 9) {
                return null;
            }
        }

        return array_values($decoded);
    }

    /** @param array $lines @return int[] vị trí 1-based mang giá trị 6 hoặc 9 */
    public function changingLines(array $lines): array
    {
        $changing = [];
        foreach ($lines as $idx => $v) {
            if ($v === self::MOVING_YIN || $v === self::MOVING_YANG) {
                $changing[] = $idx + 1;
            }
        }

        return $changing;
    }

    /**
     * Quẻ gốc: hào dương (7|9)=1, âm (6|8)=0 → bitmask 6 bit dưới→trên,
     * tra `hexagrams.lines` khớp 1-1 (64 pattern unique — SEED-01 đã verify).
     *
     * @param int[6] $lines @return int[6]
     */
    public function toBitmask(array $lines): array
    {
        return array_map(
            fn (int $v) => ($v === self::STATIC_YANG || $v === self::MOVING_YANG) ? 1 : 0,
            array_values($lines)
        );
    }

    /**
     * Replay deterministic (02-db §4b): từ lines_rolled ĐÃ LƯU, dựng lại
     * bitmask quẻ gốc + danh sách hào động — KHÔNG random lại, kết quả
     * bất biến theo thời gian (bảo đảm tái lập 409/idempotency + luật luận §4bis).
     *
     * @param int[6] $lines giá trị ∈ {6,7,8,9}, chỉ số 0 = hào dưới cùng
     * @return array{lines: int[6], changing: int[], bitmask: int[6]}
     */
    public function rollFrom(array $lines): array
    {
        $lines = array_values($lines);
        if (count($lines) !== 6) {
            throw new \RuntimeException('lines_rolled phải đúng 6 hào.');
        }
        foreach ($lines as $v) {
            if (!in_array($v, [self::MOVING_YIN, self::STATIC_YANG, self::STATIC_YIN, self::MOVING_YANG], true)) {
                throw new \RuntimeException("giá trị gieo không hợp lệ: $v");
            }
        }

        return [
            'lines' => $lines,
            'changing' => $this->changingLines($lines),
            'bitmask' => $this->toBitmask($lines),
        ];
    }

    /**
     * Quẻ biến (§4bis): bitmask quẻ gốc XOR đúng các vị trí hào động (1-based,
     * sơ→thượng). 0 hào động → trả lại chính quẻ gốc (hào từ vẫn tính, chỉ
     * không có biến — CEO chốt: quẻ biến tính + lưu DB, không vào prompt/UI).
     *
     * @param int[6] $bitmask @param int[] $changingPositions
     * @return int[6]
     */
    public function bienOf(array $bitmask, array $changingPositions): array
    {
        $bitmask = array_values($bitmask);
        if (count($bitmask) !== 6) {
            throw new \RuntimeException('bitmask phải đúng 6 hào.');
        }
        foreach ($changingPositions as $pos) {
            if (!is_int($pos) || $pos < 1 || $pos > 6) {
                throw new \RuntimeException("vị trí hào động ngoài 1..6: " . var_export($pos, true));
            }
            $bitmask[$pos - 1] ^= 1;
        }

        return $bitmask;
    }

    /**
     * Tra pattern 6 bit (dưới→trên) trong 64 quẻ — nguồn đọc DB (`hexagrams.lines`),
     * fallback file data cho pure-unit. Trả null nếu pattern không seed (không xảy ra
     * với 64 pattern chuẩn).
     *
     * @param int[6] $binary
     * @return array<string, mixed>|null
     */
    public function findPattern(array $binary, ?iterable $rows = null): ?array
    {
        $needle = implode(',', array_map(intval(...), array_values($binary)));
        $rows ??= json_decode(
            (string) file_get_contents(__DIR__ . '/../../database/data/hexagrams.json'),
            true
        );
        foreach ($rows as $r) {
            if (implode(',', array_map(intval(...), $r['lines'])) === $needle) {
                return $r;
            }
        }

        return null;
    }
}
