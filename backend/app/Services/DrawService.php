<?php

namespace App\Services;

use App\Domain\HexagramRoller;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Draw;
use App\Models\Hexagram;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * BE-1 — gieo quẻ hôm nay + đọc hiện tại (03-api #1 #3 #4 #10).
 * Trách nhiệm DUY NHẤT: vòng đời `draws`. Không gọi AI, không đụng tiền (chống god class).
 *
 * C-01 (1 quẻ/device/ngày dương lịch VN) do `uq_draws_device_date` enforce ở DB —
 * code chỉ đọc trước để trả idempotent 409 ĐẸP, còn race 2 request đồng thời vẫn bị
 * DB chặn (bắt QueryException duplicate → đọc lại row thắng → cùng 409).
 * "ngày" = Asia/Ho_Chi_Minh, chốt tại một chỗ qua $now (test fake Date, F3).
 */
class DrawService
{
    public const VN_TZ = 'Asia/Ho_Chi_Minh';

    public function __construct(private readonly HexagramRoller $roller = new HexagramRoller())
    {
    }

    /**
     * POST /api/draws (#3). Đã gieo hôm nay → 409 DRAW_LIMIT_REACHED (03-api §3 lỗi).
     * SPEC-3XU §4bis: quẻ biến TÍNH + LƯU nội bộ (`draws.bien_hexagram_id`),
     * KHÔNG trả qua API (controller không đưa vào payload).
     *
     * @return array{draw: Draw, hexagram: Hexagram}
     */
    public function drawFor(Device $device, CarbonInterface $now): array
    {
        $vnToday = $this->vnDate($now);

        // C-01 = project.php draw.free_per_day (CFG-BE): đếm quẻ đã gieo hôm nay
        // so với ngưỡng config. Mặc định 1 → đường 409 y hệt cũ; DB còn uq
        // (device,date) chặn race ở mức 1 — tăng config >1 cần bỏ unique index
        // kèm (chú thích trong project.php), code đã sẵn sàng đọc số.
        $freePerDay = (int) config('project.draw.free_per_day');
        $todayCount = Draw::query()->where('device_id', $device->device_id)
            ->where('drawn_date', $vnToday->format('Y-m-d'))->count();
        if ($todayCount >= max(1, $freePerDay)) {
            throw ApiException::drawLimitReached($this->nextMidnightUtc($now));
        }

        // Lặp tối đa 3 lần: random lại nếu (hy hữu) trúng race duplicate hoặc pattern chưa seed.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $lines = $this->roller->roll();
            $bitmask = $this->roller->toBitmask($lines);

            $hexagram = $this->findHexagramByLines($bitmask);
            if ($hexagram === null) {
                continue; // pattern không có trong DB (chỉ xảy ra nếu seed thiếu) — gieo lại
            }

            $changing = $this->roller->changingLines($lines);

            // §4bis: quẻ biến = quẻ gốc XOR các hào động → tra id, LƯU nội bộ
            // (null khi 0 hào động — không "biến" thành chính nó). Không lộ API.
            $bienId = null;
            if ($changing !== []) {
                $bien = $this->findHexagramByLines($this->roller->bienOf($bitmask, $changing));
                $bienId = $bien?->id;
            }

            try {
                $draw = Draw::query()->create([
                    'device_id' => $device->device_id,
                    'hexagram_id' => $hexagram->id,
                    'bien_hexagram_id' => $bienId,
                    'drawn_date' => $vnToday->format('Y-m-d'),
                    'lines_rolled' => $lines,
                    'changing_lines' => $changing === [] ? null : $changing, // 02-db §5: NULL nếu không có
                ]);
            } catch (QueryException $e) {
                if ($this->isDeviceDateDuplicate($e) && $this->todayDraw($device, $vnToday)) {
                    // race C-01: request song song đã ghi trước — trả đúng 409 theo spec
                    throw ApiException::drawLimitReached($this->nextMidnightUtc($now));
                }
                throw $e;
            }

            return ['draw' => $draw, 'hexagram' => $hexagram];
        }

        throw new \RuntimeException('Gieo quẻ thất bại sau 3 lần thử (seed hexagrams thiếu pattern?).');
    }

    /** GET /api/me + #10: draw hôm nay của device, null nếu chưa gieo. */
    public function todayDraw(Device $device, ?CarbonImmutable $vnToday = null): ?Draw
    {
        $date = ($vnToday ?? $this->vnDate(CarbonImmutable::now()))->format('Y-m-d');

        return Draw::query()
            ->where('device_id', $device->device_id)
            ->whereDate('drawn_date', $date)
            ->latest('id')
            ->first();
    }

    /** GET /api/draws/history (#4): mới nhất trước, giới hạn limit (validate ở controller). */
    public function history(Device $device, int $limit): \Illuminate\Support\Collection
    {
        return Draw::query()
            ->where('device_id', $device->device_id)
            ->latest('drawn_date')->latest('id')
            ->limit($limit)
            ->get();
    }

    /** MŨI TÊN ngày: YYYY-MM-DD theo Asia/Ho_Chi_Minh (03-api #1 server_date_vn). */
    public function serverDateVn(CarbonInterface $now): string
    {
        return $this->vnDate($now)->format('Y-m-d');
    }

    /** 0h VN kế tiếp, xuất RFC3339 UTC (details.next_draw_at — 03-api #3 lỗi). */
    public function nextMidnightUtc(CarbonInterface $now): string
    {
        return $now->timezone(self::VN_TZ)
            ->startOfDay()->addDay()
            ->setTimezone('UTC')
            ->format('Y-m-d\TH:i:s\Z');
    }

    private function vnDate(CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::parse($now->copy()->timezone(self::VN_TZ)->format('Y-m-d'));
    }

    /** @param int[] $bitmask tra khớp 1-1 cột `lines` (JSON array 0/1, dưới→trên) */
    private function findHexagramByLines(array $bitmask): ?Hexagram
    {
        // 64 pattern unique (SEED-01 verify) — so JSON đúng chuẩn để dùng được cache đơn giản.
        $json = json_encode($bitmask, JSON_THROW_ON_ERROR);
        $rows = $this->patternCache();

        return $rows[$json] ?? null;
    }

    /** @var array<string, Hexagram>|null pattern-JSON → hexagram (load 1 lần/request) */
    private ?array $patterns = null;

    /** @return array<string, Hexagram> */
    private function patternCache(): array
    {
        if ($this->patterns === null) {
            $this->patterns = [];
            // load đủ cột — model này được trả ra payload §2, subset columns = null fields
            foreach (Hexagram::query()->get() as $h) {
                $this->patterns[json_encode(array_map(intval(...), $h->lines), JSON_THROW_ON_ERROR)] = $h;
            }
        }

        return $this->patterns;
    }

    private function isDeviceDateDuplicate(QueryException $e): bool
    {
        // MariaDB 1062 duplicate-entry — đúng uq_draws_device_date (C-01)
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
