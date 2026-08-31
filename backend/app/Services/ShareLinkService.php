<?php

namespace App\Services;

use App\Domain\HookClip;
use App\Domain\ShareToken;
use App\Models\Device;
use App\Models\Draw;
use App\Models\HaoText;
use App\Models\Hexagram;
use App\Models\ShareLink;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * F7-BE — nghiệp vụ share link, 1 trách nhiệm (SPEC-THE §5, F7-CONTRACT §2-3):
 * findOrCreate idempotent per device+draw, buildCardPayload = NGUỒN DUY NHẤT
 * quyết định câu hook (TH1/TH2/E6), getPublic, đếm view V5 chống phình
 * (1/device/token/ngày), hook V7 referred-draw. KHÔNG luận giải, KHÔNG gọi AI,
 * KHÔNG render ảnh (ShareOgRenderer lo), KHÔNG đụng DrawService/roller.
 */
class ShareLinkService
{
    /** SPEC-THE §2: disclaimer cố định trên thẻ (04-ui). */
    public const DISCLAIMER = 'Giải trí · tham khảo văn hoá';

    /** F7-CONTRACT §3: token không hợp lệ hình dạng → 404 nhẹ, không query DB. */
    public const VIEW_EVENT = 'share_link_view';

    public const CTA_EVENT = 'share_link_cta_click';

    public const REFERRED_EVENT = 'share_referred_draw';

    public function __construct(private readonly TrackService $track)
    {
    }

    /**
     * Idempotency (UNIQUE draw_id+device_id): same device+draw → same token.
     * Race 2 request: INSERT va uq → đọc lại row thắng, KHÔNG sinh token thứ 2.
     * Va chạm token (1/62^10) → thử lại tối đa 3 lần.
     */
    public function findOrCreate(Device $device, Draw $draw): ShareLink
    {
        $existing = ShareLink::query()
            ->where('draw_id', $draw->id)
            ->where('device_id', $device->device_id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return ShareLink::query()->create([
                    'token' => ShareToken::generate(),
                    'draw_id' => $draw->id,
                    'device_id' => $device->device_id,
                    'created_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicate($e)) {
                    throw $e;
                }
                // race: request song song đã tạo trước → trả row của họ
                $row = ShareLink::query()
                    ->where('draw_id', $draw->id)
                    ->where('device_id', $device->device_id)
                    ->first();
                if ($row !== null) {
                    return $row;
                }
            }
        }

        throw new \RuntimeException('Không tạo được share link sau 3 lần thử');
    }

    /**
     * Payload CÔNG KHAI duy nhất cho GET /api + Blade + OG (bất biến chống lộ:
     * không free_content/han/quoc_am/quẻ biến/luận sâu — SPEC-THE §2).
     *
     * @return array{card:array<string,mixed>, sharer_label:string, views:int}
     */
    public function getPublic(string $token): ?array
    {
        if (! ShareToken::isValid($token)) {
            return null;
        }
        $link = ShareLink::query()->with('draw.hexagram')->where('token', $token)->first();
        if ($link === null || $link->draw === null) {
            return null;
        }

        return [
            'card' => $this->buildCardPayload($link->draw, $link->token),
            'sharer_label' => $this->sharerLabel($link->device_id),
            'views' => (int) $link->views,
        ];
    }

    /**
     * NGUỒN DUY NHẤT chọn câu hook (SPEC-THE §2): TH1 ≥1 hào động → `nghia` Việt
     * hào động NHỎ NHẤT (sơ→thượng); TH2 0 hào động → vế ĐẦU dai_ci trước "—"
     * hoặc ","; rỗng → E6 minimal. Kèm `text_clip80` (BUG-F7-QA3 t_b0cfc1b4):
     * bản hiển thị ≤80 code-point (HookClip, ≥ thuật toán FE) cho Blade + OG meta;
     * `text` NGUYÊN VĂN bất biến (canvas 9:16 cần bản đủ); không cắt nổi → ''.
     *
     * @return array<string, mixed>
     */
    public function buildCardPayload(Draw $draw, string $token): array
    {
        $hexagram = $draw->hexagram;

        return [
            'hexagram_id' => (int) $draw->hexagram_id,
            'symbol' => (string) $hexagram->symbol,
            'ten' => (string) $hexagram->ten,
            'drawn_date' => $draw->drawn_date->format('d/m'),
            'hook' => $this->pickHook($draw, $hexagram),
            'keywords' => array_values(array_map(
                'strval',
                array_slice($hexagram->keywords ?? [], 0, 4)
            )),
            'disclaimer' => self::DISCLAIMER,
            'qr_text' => '/s/'.$token,
        ];
    }

    /** @return array{text:string, text_clip80:string, source:string} */
    private function pickHook(Draw $draw, Hexagram $hexagram): array
    {
        $hook = $this->selectHook($draw, $hexagram);
        $hook['text_clip80'] = HookClip::clip($hook['text']) ?? '';

        return $hook;
    }

    /** @return array{text:string, source:string} */
    private function selectHook(Draw $draw, Hexagram $hexagram): array
    {
        $changing = array_values(array_filter(
            array_map(intval(...), $draw->changing_lines ?? []),
            static fn (int $v): bool => $v >= 1 && $v <= 6
        ));

        if ($changing !== []) {
            $hao = HaoText::query()
                ->where('hexagram_id', $draw->hexagram_id)
                ->whereIn('vi', $changing)
                ->orderBy('vi') // nhỏ nhất = hào động ĐẦU TIÊN (sơ→thượng)
                ->first();
            if ($hao !== null) {
                return ['text' => (string) $hao->nghia, 'source' => 'hao_dong'];
            }
        }

        $daiCi = trim((string) $hexagram->dai_ci);
        if ($daiCi === '') {
            return ['text' => '', 'source' => 'minimal']; // E6
        }

        return ['text' => $this->firstClause($daiCi), 'source' => 'dai_ci'];
    }

    /**
     * Vế đầu trước "—" (em dash U+2014) hoặc "," — delimiter nào xuất hiện TRƯỚC
     * thắng; regex unicode mb-safe, không cắt giữa đa-byte. Không có dấu nào →
     * nguyên văn; bản hiển thị ≤80 do HookClip lo (pickHook → text_clip80).
     */
    private function firstClause(string $text): string
    {
        $cut = preg_split('/(\x{2014}|,)/u', $text, 2);

        return trim((string) $cut[0]);
    }

    /**
     * V5 chống phình (F7-CONTRACT §3): `share_link_view` + `views` chỉ tăng
     * 1 LẦN/device/token/ngày VN — kiểm row events (name + device người xem +
     * props.token + created_at trong hôm nay VN) TRƯỚC; điều kiện bắn event và
     * tang views LÀ MỘT (không cộng mù). Chính chủ mở link mình: không đếm.
     */
    public function recordView(ShareLink $link, Device $viewer, bool $viewerIsNew = false, ?string $referrer = null): bool
    {
        if ($viewer->device_id === $link->device_id) {
            return false; // chủ thẻ xem lại không phải "lượt xem"
        }

        $startVnDay = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay()->utc();
        $already = \App\Models\Event::query()
            ->where('device_id', $viewer->device_id)
            ->where('name', self::VIEW_EVENT)
            ->where('created_at', '>=', $startVnDay)
            ->where('props->token', $link->token)
            ->exists();
        if ($already) {
            return false; // đã đếm hôm nay — 1/device/token/ngày
        }

        $this->track->track($viewer, self::VIEW_EVENT, [], [
            'token' => $link->token,
            'referrer_domain' => $this->referrerDomain($referrer),
            'device_is_new' => $viewerIsNew,
        ]);
        ShareLink::query()->where('token', $link->token)->increment('views');

        return true;
    }

    /**
     * V6 — CTA server-side (F7-CONTRACT §1): bắn vì người lạ, dedupe không có
     * trong contract (double-count chấp nhận được như F2 visit).
     */
    public function recordCtaClick(ShareLink $link, Device $viewer): void
    {
        $this->track->track($viewer, self::CTA_EVENT, [], [
            'token' => $link->token,
            'utm_medium' => 'share',
        ]);
    }

    /**
     * V7 (ADR-002 §3): moc sau 201 ở DrawController — device có first-touch
     * utm_medium=share gieo quẻ → 1 event `share_referred_draw`. Điều kiện là
     * CỘT devices.utm_medium (đã first-touch-khóa), không tin utm theo request.
     */
    public function maybeFireReferredDraw(Device $device, Draw $draw): void
    {
        if ($device->utm_medium !== 'share') {
            return;
        }
        $this->track->track($device, self::REFERRED_EVENT, [], [
            'draw_id' => (int) $draw->id,
        ]);
    }

    /** SPEC-THE §4: "Khách XXXX" — 4 ký tự cuối device, không lộ device_id đủ. */
    private function sharerLabel(string $deviceId): string
    {
        return 'Khách '.substr($deviceId, -4);
    }

    private function referrerDomain(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }
        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) ? substr($host, 0, 100) : null;
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
