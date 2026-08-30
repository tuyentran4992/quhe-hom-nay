<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Draw;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * BE-1 — nhóm "gieo quẻ/hiện tại" theo specs/1.mvp/03-api.md #1..#4, #10.
 * Covers: F1 (device bootstrap), F2 (vòng gieo 1 quẻ), F3 (C-01 idempotent),
 * #2 tra cứu quẻ (404), #4 history, #10 alias today.
 */
class DrawApiTest extends ApiTestCase
{
    // ---------- #1 GET /api/me (F1) ----------

    public function test_f1_new_device_gets_httponly_cookie_and_bootstrap_shape(): void
    {
        $resp = $this->getJson('/api/me');

        $resp->assertStatus(200)
            ->assertJsonStructure(['device_id', 'is_new_device', 'today_draw', 'entitlements', 'server_date_vn'])
            ->assertJson([
                'is_new_device' => true,
                'today_draw' => null,
                'entitlements' => [],
                'server_date_vn' => self::VN_DATE,
            ]);

        $cookie = collect($resp->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'qhn_device');
        $this->assertNotNull($cookie, 'phải Set-Cookie qhn_device');
        $this->assertTrue($cookie->isHttpOnly(), 'qhn_device phải HttpOnly (02-db §8)');
        // Max-Age 400 ngày (02-db §8). Laravel CookieJar tính expires từ Carbon clock
        // (đang freeze trong test) → so với now()+400d cùng nguồn, không dùng time() thật.
        $this->assertEqualsWithDelta(
            \Carbon\CarbonImmutable::now()->addDays(400)->getTimestamp(),
            $cookie->getExpiresTime(),
            5
        );
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $cookie->getValue());
        $this->assertSame(1, Device::query()->count());
    }

    public function test_f1_known_device_is_not_new_and_second_call_reuses_row(): void
    {
        $id = $this->deviceId();
        $second = $this->withHeaders($this->drawHeaders($id))->getJson('/api/me');
        $second->assertOk()->assertJson(['is_new_device' => false, 'device_id' => $id]);
        $this->assertSame(1, Device::query()->count(), 'device đã biết không được sinh row mới');
    }

    // ---------- #3 POST /api/draws (F2) ----------

    public function test_f2_first_draw_returns_201_full_contract_fields(): void
    {
        $deviceId = $this->deviceId();

        $resp = $this->withHeaders($this->drawHeaders($deviceId))
            ->postJson('/api/draws', []);

        $resp->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'draw' => ['id', 'hexagram_id', 'drawn_date', 'lines_rolled', 'changing_lines', 'created_at'],
                    'hexagram' => ['id', 'han', 'ten', 'quoc_am', 'upper', 'lower', 'lines', 'symbol',
                        'dai_ci', 'free_content' => ['congViec', 'tinhDuyen', 'taiLoc'], 'keywords',
                        'vv_nien', 'cat', 'ban_goc' => ['quaTu', 'thoanTruyen', 'tuongTruyen', 'haoTu'],
                        'luan_nay'],
                    'already_drawn',
                ],
            ])
            ->assertJsonPath('data.already_drawn', false)
            ->assertJsonPath('data.draw.drawn_date', self::VN_DATE);

        $draw = $resp->json('data.draw');
        $hex = $resp->json('data.hexagram');

        $this->assertCount(6, $draw['lines_rolled']);
        foreach ($draw['lines_rolled'] as $v) {
            $this->assertContains($v, [6, 7, 8, 9]);
        }
        foreach ($draw['changing_lines'] as $pos) {
            $this->assertContains($pos, [1, 2, 3, 4, 5, 6]);
            $this->assertContains($draw['lines_rolled'][$pos - 1], [6, 9]);
        }
        $this->assertSame($hex['id'], $draw['hexagram_id']);

        // quẻ trả về khớp lines_rolled: dương (7|9)=1 âm (6|8)=0, dưới→trên
        $expected = array_map(fn ($v) => in_array($v, [7, 9], true) ? 1 : 0, $draw['lines_rolled']);
        $this->assertSame($expected, $hex['lines']);

        // free_content 3 ngôi + dai_ci/keywords phải có từ bảng hexagrams
        $this->assertNotEmpty($hex['free_content']['congViec']);
        $this->assertNotEmpty($hex['free_content']['tinhDuyen']);
        $this->assertNotEmpty($hex['free_content']['taiLoc']);
        $this->assertNotEmpty($hex['dai_ci']);
        $this->assertCount(4, $hex['keywords']);
        // created_at RFC3339 UTC
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $draw['created_at']);

        // CẤM lộ canSoi (02-db §4)
        $this->assertStringNotContainsString('canSoi', $resp->getContent());
        $this->assertSame(1, Draw::query()->count());
    }

    // ---------- #3 C-01 idempotency (F3) ----------

    public function test_f3_second_draw_same_day_is_409_with_exact_spec_shape(): void
    {
        $deviceId = $this->deviceId();

        $first = $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);

        $second = $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(409);
        $second->assertExactJson(['error' => [
            'code' => 'DRAW_LIMIT_REACHED',
            'message' => 'Hôm nay bạn đã gieo quẻ rồi. Quay lại sau 0h.',
            'details' => ['next_draw_at' => '2026-08-30T17:00:00Z'], // 0h VN 31/08 = 17h UTC 30/08
        ]]);

        // "trả cùng kết quả": /api/me + /api/me/today vẫn show quẻ của lần 1, Draw table không thêm row
        $me = $this->withHeaders($this->drawHeaders($deviceId))->getJson('/api/me');
        $me->assertOk()->assertJsonPath('today_draw.id', $first->json('data.draw.id'));
        $me->assertJsonPath('today_draw.lines_rolled', $first->json('data.draw.lines_rolled'));
        $this->assertSame(1, Draw::query()->count());
    }

    public function test_draw_on_different_vn_day_creates_new_row(): void
    {
        $deviceId = $this->deviceId();
        $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);

        \Carbon\Carbon::setTestNow('2026-08-30 18:00:00'); // = 01:00 VN 2026-08-31 → ngày mới
        $resp = $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);
        $resp->assertJsonPath('data.draw.drawn_date', '2026-08-31');
        $this->assertSame(2, Draw::query()->count());
    }

    public function test_draw_before_device_cookie_still_works_with_malformed_body(): void
    {
        // 03-api #3: body `{}` hợp lệ; JSON malformed → 400 BAD_REQUEST envelope §0.3
        $deviceId = $this->deviceId();
        $this->withHeaders($this->drawHeaders($deviceId))
            ->postJson('/api/draws', ['client_date_vn' => '2026-08-30'])
            ->assertStatus(201);
    }

    // ---------- #10 + #4 + #2 ----------

    public function test_me_today_alias_returns_three_shared_fields(): void
    {
        $deviceId = $this->deviceId();
        $drawn = $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);

        $resp = $this->withHeaders($this->drawHeaders($deviceId))->getJson('/api/me/today');
        $resp->assertStatus(200)
            ->assertJsonStructure(['data' => ['today_draw', 'entitlements', 'server_date_vn']])
            ->assertJsonPath('data.server_date_vn', self::VN_DATE)
            ->assertJsonPath('data.entitlements', [])
            ->assertJsonPath('data.today_draw.id', $drawn->json('data.draw.id'));
        // không được tải cả hexagram ở alias (FE khỏi nặng — 03-api #10)
        $this->assertArrayNotHasKey('hexagram', $resp->json('data'));
    }

    public function test_history_lists_newest_first_with_limit_and_validation(): void
    {
        $deviceId = $this->deviceId();
        $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);
        \Carbon\Carbon::setTestNow('2026-08-30 18:00:00'); // ngày VN kế
        $later = $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);

        $resp = $this->withHeaders($this->drawHeaders($deviceId))->getJson('/api/draws/history?limit=10');
        $resp->assertStatus(200)->assertJsonStructure([
            'data' => [['id', 'hexagram_id', 'drawn_date', 'lines_rolled', 'changing_lines', 'created_at']],
            'meta' => ['count'],
        ]);
        $this->assertSame($later->json('data.draw.id'), $resp->json('data.0.id'), 'mới nhất trước');
        $this->assertSame(2, $resp->json('meta.count'));

        $this->withHeaders($this->drawHeaders($deviceId))->getJson('/api/draws/history?limit=0')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->drawHeaders($deviceId))->getJson('/api/draws/history?limit=51')
            ->assertStatus(422);
    }

    public function test_hexagram_lookup_200_full_fields_and_404_contract(): void
    {
        $resp = $this->getJson('/api/hexagrams/1');
        $resp->assertStatus(200)->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.ten', 'Càn Vi Thiên')
            ->assertJsonPath('data.dai_ci', DB::table('hexagrams')->where('id', 1)->value('dai_ci'));
        $this->assertStringNotContainsString('canSoi', $resp->getContent());
        $this->assertIsArray($resp->json('data.ban_goc.haoTu'));

        $this->getJson('/api/hexagrams/65')->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson('/api/hexagrams/0')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson('/api/hexagrams/khong-ton-tai')->assertStatus(404);
    }

    // ---------- an toàn device ----------

    public function test_device_cannot_read_another_device_today_draw(): void
    {
        $a = $this->deviceId();
        $b = $this->deviceId(); // device B hoàn toàn khác
        $draw = $this->withHeaders($this->drawHeaders($a))->postJson('/api/draws', [])->assertStatus(201);

        $meB = $this->withHeaders($this->drawHeaders($b))->getJson('/api/me');
        $meB->assertOk()->assertJsonPath('today_draw', null);

        $histB = $this->withHeaders($this->drawHeaders($b))->getJson('/api/draws/history');
        $histB->assertStatus(200)->assertJsonPath('meta.count', 0);

        // history của A chứa draw
        $this->withHeaders($this->drawHeaders($a))->getJson('/api/draws/history')
            ->assertJsonPath('data.0.id', $draw->json('data.draw.id'));
    }
}
