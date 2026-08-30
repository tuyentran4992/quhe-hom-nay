<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Tests\ApiTestCase;

/**
 * F3 (05-testplan) — ca KHÓ: phiên gieo lúc 23:5x rồi 0:0x ngày mới.
 * Chốt 2 phía của đường ranh giới 0h VN = 17:00 UTC ngày hôm trước:
 * 16:59:59Z vẫn là ngày VN cũ, 17:00:00Z là ngày VN mới — drawn_date + C-01 phải theo đúng.
 */
class MidnightBoundaryTest extends ApiTestCase
{
    // ApiTestCase freeze 02:15:00 UTC (= 09:15 VN 30/08).
    private const FIXED_DATE = '2026-08-30';

    public function test_draw_at_2359_vn_uses_old_vn_day_and_blocks_same_day(): void
    {
        $id = $this->deviceId();

        Carbon::setTestNow('2026-08-30 16:59:59'); // 23:59:59 VN 30/08
        $this->asDevice($id)->postJson('/api/draws', [])->assertStatus(201);

        $me = $this->asDevice($id)->getJson('/api/me');
        $me->assertOk()->assertJsonPath('server_date_vn', self::FIXED_DATE);
        $me->assertJsonPath('today_draw.drawn_date', self::FIXED_DATE);

        // Vẫn C-01 trong cùng ngày VN (chưa qua 0h): 409, next_draw_at = đúng 17:00Z
        $resp = $this->asDevice($id)->postJson('/api/draws', [])->assertStatus(409);
        $resp->assertJsonPath('error.details.next_draw_at', '2026-08-30T17:00:00Z');
    }

    public function test_draw_at_0000_vn_opens_new_day_while_yesterday_draw_persists(): void
    {
        $id = $this->deviceId();

        Carbon::setTestNow('2026-08-30 16:59:59'); // 23:59:59 VN 30/08 — gieo ngày cũ
        $old = $this->asDevice($id)->postJson('/api/draws', [])->assertStatus(201);

        Carbon::setTestNow('2026-08-30 17:00:00'); // 00:00:00 VN 31/08 — GIÂY ranh giới
        $new = $this->asDevice($id)->postJson('/api/draws', [])->assertStatus(201);

        $this->assertNotSame(
            $old->json('data.draw.drawn_date'),
            $new->json('data.draw.drawn_date'),
            'đúng 0h VN phải sang ngày mới'
        );
        $this->assertSame('2026-08-31', $new->json('data.draw.drawn_date'));

        $me = $this->asDevice($id)->getJson('/api/me');
        $me->assertJsonPath('server_date_vn', '2026-08-31');
        $me->assertJsonPath('today_draw.id', $new->json('data.draw.id'));

        // today = ngày mới nhưng history vẫn đủ 2 ngày (0h KHÔNG xóa quẻ cũ)
        $hist = $this->asDevice($id)->getJson('/api/draws/history')->assertOk();
        $this->assertCount(2, $hist->json('data'));
        $this->assertSame('2026-08-31', $hist->json('data.0.drawn_date'), 'mới nhất trước');

        // Và ngày mới cũng bị C-01: 409 với next_draw_at = 17:00Z 31/08
        $again = $this->asDevice($id)->postJson('/api/draws', [])->assertStatus(409);
        $again->assertJsonPath('error.details.next_draw_at', '2026-08-31T17:00:00Z');
    }
}
