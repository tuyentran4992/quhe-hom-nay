<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * F7-BE (ADR-002 §1 / F7-CONTRACT §1) — whitelist +7 tên event METRICS V1–V7
 * NGUYÊN VĂN, KHÔNG prefix qhn_.管线 F2: POST /api/track reject ngoài whitelist → 422.
 * Kèm: first-touch không đè devices.utm (E7 — người đến qua share giờ chia tiếp).
 */
class ShareWhitelistTest extends ApiTestCase
{
    /** 7 tên BẤT BIẾN theo METRICS §1 — sai 1 ký tự là gãy đường tra của growth-lead. */
    public const SEVEN = [
        'share_card_open',
        'share_card_created',
        'share_card_error',
        'share_card_done',
        'share_link_view',
        'share_link_cta_click',
        'share_referred_draw',
    ];

    /** 2 tên F8 donate-CTA — thêm CUỐI, donate_open giữ nguyên vị trí (C2). */
    public const DONATE_CTA = ['donate_cta_shown', 'donate_cta_click'];

    public function test_model_whitelist_contains_exactly_f2_plus_seven_share_names(): void
    {
        // F8-BE (t_03424b76 C2): danh sách đầy đủ 12 name = F2(2) + MKT-F6-fix(1)
        // + F7(7) + F8 donate_cta(2), đúng thứ tự thêm của Event::NAME_WHITELIST
        // — 1 nguồn sự thật; donate_open GIỮ vị trí cũ.
        $this->assertSame(
            ['landing_visit', 'cta_gieo_que', 'donate_open', ...self::SEVEN, ...self::DONATE_CTA],
            Event::NAME_WHITELIST,
            'whitelist = 3 tên F2/F6 + 7 tên F7 + 2 tên F8 nguyên văn, đúng thứ tự thêm'
        );
    }

    /** C5: 2 event donate_cta_* qua /api/track → 204 + row, props {topic} giữ nguyên. */
    public function test_donate_cta_events_trackable_via_api(): void
    {
        $dev = $this->deviceId();
        $this->asDevice($dev);

        foreach (self::DONATE_CTA as $name) {
            $this->postJson('/api/track', ['name' => $name, 'props' => ['topic' => 'congViec']])
                ->assertStatus(204, "event '{$name}' phải 204");
        }

        $rows = DB::table('events')->where('device_id', $dev)->whereIn('name', self::DONATE_CTA)->pluck('name')->all();
        sort($rows);
        $expect = self::DONATE_CTA;
        sort($expect);
        $this->assertSame($expect, $rows, 'đủ 2 row events tên thô');

        $props = json_decode((string) DB::table('events')->where('name', 'donate_cta_shown')->value('props'), true);
        $this->assertSame(['topic' => 'congViec'], $props, 'props {topic} lưu nguyên văn');
    }

    /** 7 events vào /api/track qua pipeline F2: 204 + row events name thô (không qhn_). */
    public function test_all_seven_share_events_trackable_via_api(): void
    {
        $dev = $this->deviceId();
        $this->asDevice($dev);

        foreach (self::SEVEN as $name) {
            $resp = $this->postJson('/api/track', ['name' => $name, 'props' => ['token' => 'abcd123456']]);
            $resp->assertStatus(204, "event '{$name}' phải 204");
        }

        $names = DB::table('events')->pluck('name')->all();
        sort($names);
        $expect = self::SEVEN;
        sort($expect);
        $this->assertSame($expect, $names);
        foreach ($names as $n) {
            $this->assertFalse(str_starts_with($n, 'qhn_'), 'tên event KHÔNG prefix (ADR-002)');
        }
    }

    /** Tên biến thể sai (prefix qhn_, hoa, gạch) vẫn 422 — khóa từng ký tự. */
    public function test_near_miss_names_still_rejected(): void
    {
        $dev = $this->deviceId();
        $this->asDevice($dev);

        foreach (['qhn_share_card_open', 'Share_Card_Open', 'share-card-open', 'share_link_views'] as $bad) {
            $this->postJson('/api/track', ['name' => $bad])->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }
    }

    /**
     * E7 + F7-CONTRACT: device từng đến qua share (first-touch utm_medium=share)
     * giờ chia tiếp — sự kiện share không được đè attribution.
     */
    public function test_share_events_do_not_overwrite_first_touch_utm(): void
    {
        // lượt 1: đến qua share
        $r1 = $this->asDevice(null)->postJson('/api/track', [
            'name' => 'landing_visit',
            'utm' => ['source' => 'app_card', 'medium' => 'share', 'campaign' => 'share_card_v1'],
        ]);
        $devId = $this->cookieDeviceId($r1);

        // lượt 2: thiết bị đó nhận utm khác qua sự kiện share — không được đè
        $this->asDevice($devId)->postJson('/api/track', [
            'name' => 'share_referred_draw',
            'utm' => ['medium' => 'zalo', 'campaign' => 'other'],
        ])->assertStatus(204);

        $device = Device::query()->findOrFail($devId);
        $this->assertSame('share', $device->utm_medium, 'first-touch bất biến');
        $this->assertSame('share_card_v1', $device->utm_campaign);
    }

    private function cookieDeviceId(\Illuminate\Testing\TestResponse $resp): ?string
    {
        $c = collect($resp->headers->getCookies())->first(fn ($x) => $x->getName() === 'qhn_device');

        return $c?->getValue();
    }
}
