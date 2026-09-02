<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Draw;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * VS1-L1 (SPEC-VS1-L1 §6) — attribution token `devices.referred_token`:
 * T1 schema cột mới (NULL được, backward compat), T3 first-touch-khóa +
 * chống self-referral, T4 2 bản query Q3 §4 chạy trên seed thật (growth
 * copy-paste — chứng minh không exception, nhóm NULL đúng bản chất pre-L1).
 *
 * T2 nằm trong ShareCtaReferredDrawRealChainTest (mở rộng assertion — chứng
 * nhân trace sweep t_fd6ddd7e, không viết lại). T5 = suite cũ xanh nguyên.
 */
class ShareAttributionTokenTest extends ApiTestCase
{
    /** Draw tay + share link cho device $sharer → token 10 ký tự. */
    private function tokenFor(string $sharer): string
    {
        $draw = Draw::query()->create([
            'device_id' => $sharer,
            'hexagram_id' => 11,
            'drawn_date' => self::VN_DATE,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => [2, 4],
        ]);

        return $this->asDevice($sharer)
            ->postJson('/api/share-links', ['draw_id' => $draw->id])
            ->json('token');
    }

    /** T1 — migration 000010: cột CHAR(10) NULL + index phục vụ GROUP BY Q3. */
    public function test_t1_devices_has_nullable_referred_token_column(): void
    {
        $col = DB::selectOne("SHOW COLUMNS FROM devices LIKE 'referred_token'");
        $this->assertNotNull($col, 'cột referred_token phải tồn tại sau migration');
        $this->assertSame('YES', $col->Null, 'phải NULL được — device không qua CTA không có token');
        $this->assertStringStartsWith('char(10)', strtolower((string) $col->Type), 'CHAR(10) khớp ShareToken::isValid');
        $this->assertSame('MUL', (string) $col->Key, 'có index idx_devices_referred_token');

        $key = DB::selectOne("SHOW INDEX FROM devices WHERE Key_name = 'idx_devices_referred_token'");
        $this->assertNotNull($key, 'index cho GROUP BY Q3 (bản cột) phải có');

        // backward compat schema: insert device KHÔNG cần token vẫn hợp lệ
        $dev = $this->deviceId();
        $this->assertNull(Device::query()->find($dev)->referred_token);
    }

    /**
     * T3a — first-touch-khóa: B bấm CTA thẻ A rồi thẻ B' → cột VẪN = token A.
     * Toàn bộ bằng chuột thật (API), không seed hộ cột.
     */
    public function test_t3_first_touch_token_locked_across_second_cta(): void
    {
        $tokenA = $this->tokenFor($this->deviceId());
        $tokenB = $this->tokenFor($this->deviceId());
        $this->assertNotSame($tokenA, $tokenB);

        $b = $this->deviceId();
        $this->asDevice($b)->get("/s/{$tokenA}/cta")->assertStatus(302);
        $this->assertSame($tokenA, Device::query()->find($b)->referred_token, 'cú CTA đầu ghi token A');

        $this->asDevice($b)->get("/s/{$tokenB}/cta")->assertStatus(302);
        $this->assertSame($tokenA, Device::query()->find($b)->referred_token, 'first-touch-khóa: thẻ B\' không đè được');

        // V7 vẫn mang token A (đọc cột, không đọc cú bấm cuối)
        $this->asDevice($b)->postJson('/api/draws', [])->assertStatus(201);
        $v7 = Event::query()->where('name', 'share_referred_draw')->where('device_id', $b)->sole();
        $this->assertSame($tokenA, $v7->props['token']);
    }

    /** T3b — self-click (viewer == owner): KHÔNG ghi cột (chủ thẻ không tự mời mình). */
    public function test_t3_self_click_does_not_capture_token(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);

        $this->asDevice($sharer)->get("/s/{$token}/cta")->assertStatus(302);

        $row = Device::query()->find($sharer);
        $this->assertNull($row->referred_token, 'chủ thẻ bấm lại link mình không được tính được mời từ thẻ của chính mình');
        // hành vi hiện hữu BUG-F7-QA1 giữ nguyên: utm VẪN khóa 'share' → V7 fire không token (nhóm NULL)
        $this->assertSame('share', $row->utm_medium);
        // sharer đã gieo sáng nay (tokenFor) → lùi sang ngày hôm sau để được gieo tiếp
        Carbon::setTestNow('2026-08-31 02:00:00');
        $this->asDevice($sharer)->postJson('/api/draws', [])->assertStatus(201);
        $v7 = Event::query()->where('name', 'share_referred_draw')->where('device_id', $sharer)->sole();
        $this->assertArrayNotHasKey('token', $v7->props, 'props V7 không có key khi cột NULL (format cũ)');
    }

    /**
     * T4 — 2 bản query Q3 §4 trên seed thật: 2 thẻ + 3 device referred
     * (1 device mô phỏng pre-L1: utm='share' tay + Event V7 cũ props {draw_id}).
     * Kết quả đúng {tokenA:1, tokenB:1} + nhóm NULL:1, không exception.
     */
    public function test_t4_growth_q3_runs_on_both_sources_without_exception(): void
    {
        $tokenA = $this->tokenFor($this->deviceId());
        $tokenB = $this->tokenFor($this->deviceId());

        $devA = $this->deviceId();
        $this->asDevice($devA)->get("/s/{$tokenA}/cta")->assertStatus(302);
        $this->asDevice($devA)->postJson('/api/draws', [])->assertStatus(201);
        $devB = $this->deviceId();
        $this->asDevice($devB)->get("/s/{$tokenB}/cta")->assertStatus(302);
        $this->asDevice($devB)->postJson('/api/draws', [])->assertStatus(201);

        // device pre-L1: không qua CTA sau L1 — utm='share' + V7 cũ chỉ {draw_id}
        $devOld = $this->deviceId();
        Device::query()->where('device_id', $devOld)->update(['utm_medium' => 'share']);
        Event::query()->create([
            'device_id' => $devOld,
            'name' => 'share_referred_draw',
            'props' => ['draw_id' => 999999],
            'created_at' => now(),
        ]);

        // bản CỘT (canonical)
        $byCol = DB::select(
            'SELECT referred_token, COUNT(*) AS referred_devices'.
            ' FROM devices WHERE referred_token IS NOT NULL'.
            ' GROUP BY referred_token ORDER BY referred_devices DESC'
        );
        $col = [];
        foreach ($byCol as $r) {
            $col[trim((string) $r->referred_token)] = (int) $r->referred_devices;
        }
        ksort($col); // ORDER BY count DESC không thứ tự xác định khi tie
        $expected = [$tokenA => 1, $tokenB => 1];
        ksort($expected);
        $this->assertSame($expected, $col, 'Q3 bản cột đếm đúng theo collect card gốc');

        // bản ĐỐI CHIẾU props V7 — dòng cũ props không token gộp vào nhóm NULL, không mất
        $byProps = DB::select(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(e.props,'\$.token')) AS token,".
            ' COUNT(DISTINCT e.device_id) AS referred_devices'.
            " FROM events e WHERE e.name='share_referred_draw'".
            ' GROUP BY token ORDER BY referred_devices DESC'
        );
        $props = [];
        foreach ($byProps as $r) {
            $key = $r->token === null ? 'NULL' : trim((string) $r->token);
            $props[$key] = (int) $r->referred_devices;
        }
        $this->assertArrayHasKey('NULL', $props, 'event đời trước L1 (props {draw_id}) phải về nhóm NULL, không crash');
        $this->assertSame(1, $props['NULL']);
        $this->assertSame(1, $props[$tokenA]);
        $this->assertSame(1, $props[$tokenB]);
    }
}
