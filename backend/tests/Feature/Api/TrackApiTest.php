<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * MKT-F2a — specs/1.mvp/06-mkt-tracking.md #11 POST /api/track (TDD).
 * Covers: §3 contract (204, envelope §0.3 422), §2 first-touch bất biến +
 * sanitize utm_* ≤100 ký tự charset khóa, §6 CVR query chạy trên dữ liệu test,
 * throttle 30/phút/IP không chặn luồng hợp lệ.
 */
class TrackApiTest extends ApiTestCase
{
    private const UTM = [
        'source' => 'fb_group',
        'medium' => 'social',
        'campaign' => 'w1_que_tinh',
    ];

    /**
     * Device mới qua cookie jar của ApiTestCase: POST track không cookie →
     * EnsureDeviceSession Set-Cookie, trả device_id để tra DB.
     */
    private function track(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/track', $payload);
    }

    private function cookieDeviceId(\Illuminate\Testing\TestResponse $resp): ?string
    {
        $c = collect($resp->headers->getCookies())->first(
            fn ($x) => $x->getName() === 'qhn_device'
        );

        return $c?->getValue();
    }

    // ---------- TEST 1: 204 + row events + devices.utm_* đúng ----------

    public function test_landing_visit_with_utm_returns_204_and_persists_event_and_attribution(): void
    {
        $resp = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => self::UTM]);

        $resp->assertStatus(204);
        $deviceId = $this->cookieDeviceId($resp);
        $this->assertNotNull($deviceId, 'EnsureDeviceSession phải Set-Cookie qhn_device');

        $event = Event::query()->where('device_id', $deviceId)->sole();
        $this->assertSame('landing_visit', $event->name);
        $this->assertNotNull($event->created_at);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertSame('fb_group', $device->utm_source);
        $this->assertSame('social', $device->utm_medium);
        $this->assertSame('w1_que_tinh', $device->utm_campaign);
    }

    public function test_event_recorded_even_without_utm_still_204(): void
    {
        $resp = $this->asDevice(null)->track(['name' => 'cta_gieo_que']);
        $resp->assertStatus(204);

        $deviceId = $this->cookieDeviceId($resp);
        $this->assertSame(1, Event::query()->where('device_id', $deviceId)->where('name', 'cta_gieo_que')->count());
        // không utm → cột vẫn NULL
        $this->assertNull(Device::query()->findOrFail($deviceId)->utm_campaign);
    }

    // ---------- TEST 2: first-touch không đè ----------

    public function test_first_touch_second_campaign_does_not_overwrite_and_second_event_row_kept(): void
    {
        $resp1 = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => self::UTM]);
        $resp1->assertStatus(204);
        $deviceId = $this->cookieDeviceId($resp1);

        $resp2 = $this->asDevice($deviceId)->track([
            'name' => 'landing_visit',
            'utm' => ['source' => 'tiktok', 'medium' => 'video', 'campaign' => 'w2_khac'],
        ]);
        $resp2->assertStatus(204);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertSame('fb_group', $device->utm_source, 'first-touch: cột đã có giá trị không được đè');
        $this->assertSame('social', $device->utm_medium);
        $this->assertSame('w1_que_tinh', $device->utm_campaign);

        $this->assertSame(2, Event::query()->where('device_id', $deviceId)->count(), 'events vẫn ghi đầy đủ 2 row');
    }

    public function test_first_touch_is_per_column(): void
    {
        $resp1 = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => ['campaign' => 'giu_nguyen']]);
        $deviceId = $this->cookieDeviceId($resp1);

        // lần 2 chỉ có source — campaign đã có thì giữ, source còn NULL thì được phép ghi
        $this->asDevice($deviceId)->track(['name' => 'landing_visit', 'utm' => ['campaign' => 'de_that_bai', 'source' => 'google']])
            ->assertStatus(204);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertSame('giu_nguyen', $device->utm_campaign);
        $this->assertSame('google', $device->utm_source);
        $this->assertNull($device->utm_medium, 'utm không gửi ở lần nào → vẫn NULL');
    }

    // ---------- TEST 3: name ngoài whitelist → 422 envelope §0.3 ----------

    public function test_name_outside_whitelist_returns_422_envelope(): void
    {
        $this->asDevice($this->deviceId())->track(['name' => 'hack_me', 'utm' => self::UTM])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Dữ liệu không hợp lệ.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);

        $this->assertSame(0, Event::query()->count(), '422 phải không ghi row nào');
    }

    public function test_missing_name_and_non_string_name_return_422(): void
    {
        $http = $this->asDevice($this->deviceId());
        $http->track([])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->asDevice($this->deviceId())->track(['name' => 12345])->assertStatus(422);
    }

    // ---------- TEST 4: utm quá 100 ký tự / ký tự rác → sanitize, không 500 ----------

    public function test_utm_over_100_chars_and_junk_is_truncated_and_sanitized_not_500(): void
    {
        $dirty = str_repeat('<script>alert("xss")</script>; DROP TABLE events; --', 8); // 320 ký tự rác
        $resp = $this->asDevice(null)->track([
            'name' => 'landing_visit',
            'utm' => ['campaign' => $dirty, 'source' => 'ok!@#$%source?&=name'],
        ]);
        $resp->assertStatus(204); // không được 500

        $device = Device::query()->findOrFail($this->cookieDeviceId($resp));
        $this->assertLessThanOrEqual(100, strlen((string) $device->utm_campaign));
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_\-.,:()\/ ]*$/',
            (string) $device->utm_campaign,
            'mọi ký tự ngoài charset khóa phải bị khử'
        );
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_\-.,:()\/ ]*$/', (string) $device->utm_source);
    }

    public function test_utm_exactly_at_limit_keeps_100_chars(): void
    {
        $long = str_repeat('a', 150);
        $resp = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => ['campaign' => $long]]);
        $resp->assertStatus(204);
        $device = Device::query()->findOrFail($this->cookieDeviceId($resp));
        $this->assertSame(100, strlen($device->utm_campaign), 'cắt gọn đúng 100 ký tự');
    }

    public function test_props_over_2kb_does_not_500_and_event_still_written(): void
    {
        $resp = $this->asDevice(null)->track([
            'name' => 'landing_visit',
            'props' => ['blob' => str_repeat('x', 3000), 'path' => '/'],
        ]);
        $resp->assertStatus(204);
        $this->assertSame(1, Event::query()->count());
    }

    public function test_props_are_stored_as_json(): void
    {
        $resp = $this->asDevice(null)->track([
            'name' => 'landing_visit',
            'props' => ['path' => '/', 'referrer' => 'https://facebook.com/abc'],
        ]);
        $resp->assertStatus(204);
        $event = Event::query()->sole();
        $this->assertSame('/', $event->props['path']);
        $this->assertSame('https://facebook.com/abc', $event->props['referrer']);
    }

    // ---------- TEST 5: throttle 30/phút/IP không chặn luồng hợp lệ ----------

    public function test_throttle_does_not_block_a_legitimate_burst(): void
    {
        $deviceId = $this->deviceId();
        $this->asDevice($deviceId);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/track', ['name' => $i % 2 ? 'cta_gieo_que' : 'landing_visit'])
                ->assertStatus(204);
        }
        $this->assertSame(10, Event::query()->count());
    }

    public function test_throttle_30_per_minute_is_actually_attached(): void
    {
        // §3: Throttle 30/phút/IP — gửi 31 request hợp lệ liên tiếp: 30 cái đầu
        // phải 204, cái thứ 31 phải 429 (chứng minh middleware có tồn tại).
        $statuses = [];
        for ($i = 0; $i < 31; $i++) {
            $statuses[] = $this->postJson('/api/track', ['name' => 'landing_visit'])->getStatusCode();
        }
        $this->assertSame(30, count(array_filter($statuses, fn ($s) => $s === 204)));
        $this->assertSame(429, end($statuses));
    }

    // ---------- TEST 6: CVR query §6 chạy được trên dữ liệu test ----------

    public function test_cvr_query_pivots_by_campaign_on_test_data(): void
    {
        // device A: campaign alpha, visit + click + draw
        $respA = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => ['campaign' => 'alpha']]);
        $devA = $this->cookieDeviceId($respA);
        $this->asDevice($devA)->track(['name' => 'cta_gieo_que'])->assertStatus(204);
        $this->asDevice($devA)->postJson('/api/draws', [])->assertStatus(201);

        // device B: campaign beta, chỉ visit (không draw)
        $respB = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => ['campaign' => 'beta']]);
        $this->cookieDeviceId($respB);

        // device C: không utm → không xuất hiện (WHERE utm_campaign IS NOT NULL)
        $this->asDevice(null)->postJson('/api/draws', [])->assertStatus(201);

        // SQL nguyên văn spec 06 §6
        $rows = DB::select(<<<'SQL'
SELECT d.utm_campaign,
       COUNT(DISTINCT CASE WHEN e.name='landing_visit' THEN d.device_id END) AS visits,
       COUNT(DISTINCT CASE WHEN e.name='cta_gieo_que'  THEN d.device_id END) AS clicks,
       COUNT(DISTINCT dr.device_id) AS draws
FROM devices d
LEFT JOIN events e ON e.device_id = d.device_id
LEFT JOIN draws  dr ON dr.device_id = d.device_id
WHERE d.utm_campaign IS NOT NULL
GROUP BY d.utm_campaign ORDER BY visits DESC
SQL);

        $pivot = [];
        foreach ($rows as $r) {
            $pivot[$r->utm_campaign] = ['visits' => (int) $r->visits, 'clicks' => (int) $r->clicks, 'draws' => (int) $r->draws];
        }
        // device A có 2 visit-event? không — 1 visit + 1 click; visits=1 clicks=1 draws=1 nhưng
        // draws COUNT(DISTINCT dr.device_id) nhân theo số row e → DISTINCT vẫn 1.
        $this->assertArrayHasKey('alpha', $pivot);
        $this->assertSame(1, $pivot['alpha']['visits']);
        $this->assertSame(1, $pivot['alpha']['clicks']);
        $this->assertSame(1, $pivot['alpha']['draws']);
        $this->assertSame(1, $pivot['beta']['visits']);
        $this->assertSame(0, $pivot['beta']['clicks']);
        $this->assertSame(0, $pivot['beta']['draws']);
        $this->assertCount(2, $pivot, 'device không utm bị WHERE loại');
    }

    // ---------- TEST 7: MKT-F6-fix — donate_open vào whitelist (spec §2/§3) ----------

    public function test_whitelist_constant_is_single_source_of_three_names(): void
    {
        // Merge F7 (t_a2ef281b): whitelist lớn dần theo feature (F7 thêm 7 event share) —
        // bất biến của lane này là 3 name F2/F6 phải đứng ĐẦU, đúng thứ tự, đủ bộ.
        $this->assertSame(
            ['landing_visit', 'cta_gieo_que', 'donate_open'],
            array_slice(Event::NAME_WHITELIST, 0, 3)
        );
        $this->assertGreaterThanOrEqual(3, count(Event::NAME_WHITELIST));
    }

    public function test_donate_open_with_topic_returns_204_and_persists_event_with_props(): void
    {
        // SPA mở /mo-khoa/duyen → POST donate_open, props{topic} (spec §3 payload).
        $resp = $this->asDevice(null)->track([
            'name' => 'donate_open',
            'props' => ['topic' => 'duyen'],
        ]);
        $resp->assertStatus(204);

        $deviceId = $this->cookieDeviceId($resp);
        $event = Event::query()->where('device_id', $deviceId)->sole();
        $this->assertSame('donate_open', $event->name);
        $this->assertSame('duyen', $event->props['topic']);
    }

    public function test_donate_open_after_landing_visit_keeps_first_touch_utm_intact(): void
    {
        // landing_visit campaign w1 → donate_open mang campaign khac:
        // events đủ 2 row, cot devices.utm_* GIU NGUYEN first-touch (spec §2 bất biến).
        $resp1 = $this->asDevice(null)->track(['name' => 'landing_visit', 'utm' => self::UTM]);
        $deviceId = $this->cookieDeviceId($resp1);

        $this->asDevice($deviceId)->track([
            'name' => 'donate_open',
            'utm' => ['source' => 'zalo', 'medium' => 'chat', 'campaign' => 'w2_khac'],
            'props' => ['topic' => 'duyen'],
        ])->assertStatus(204);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertSame('fb_group', $device->utm_source, 'first-touch: donate_open không được đè utm');
        $this->assertSame('social', $device->utm_medium);
        $this->assertSame('w1_que_tinh', $device->utm_campaign);

        $names = Event::query()->where('device_id', $deviceId)->orderBy('id')->pluck('name')->all();
        $this->assertSame(['landing_visit', 'donate_open'], $names);
    }

    public function test_donate_open_with_no_prior_utm_still_sets_attribution_for_null_columns(): void
    {
        // donate_open là event ĐẦU TIÊN của device (deep-link thẳng paywall):
        // vẫn ghi được utm vào cột đang NULL + props topic bảo toàn.
        $resp = $this->asDevice(null)->track([
            'name' => 'donate_open',
            'utm' => ['campaign' => 'zalo_oa'],
            'props' => ['topic' => 'tai_loc'],
        ]);
        $resp->assertStatus(204);

        $device = Device::query()->findOrFail($this->cookieDeviceId($resp));
        $this->assertSame('zalo_oa', $device->utm_campaign);
        $this->assertSame('tai_loc', Event::query()->sole()->props['topic']);
    }

    // ---------- phụ trợ: schema ----------

    public function test_events_table_shape_and_device_fk(): void
    {
        $cols = array_map(
            fn ($r) => $r->Field,
            DB::select('SHOW COLUMNS FROM events')
        );
        sort($cols);
        $this->assertSame(['created_at', 'device_id', 'id', 'name', 'props'], $cols);

        $utm = array_map(
            fn ($r) => $r->Field,
            DB::select('SHOW COLUMNS FROM devices')
        );
        $this->assertContains('utm_source', $utm);
        $this->assertContains('utm_medium', $utm);
        $this->assertContains('utm_campaign', $utm);
    }
}
