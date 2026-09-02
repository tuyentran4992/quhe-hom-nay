<?php

namespace Tests\Feature\Api;

use App\Models\Draw;
use App\Models\HaoText;
use App\Models\Hexagram;
use App\Models\ShareLink;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * F7-BE — SPEC-THE §5 + F7-CONTRACT §2: POST /api/share-links +
 * GET /api/share-links/{token}. TDD: idempotency per device+draw, payload
 * BẤT BIẾN chống lộ luận giải, hook TH1/TH2/E6, 404 nhẹ E3, throttle 10/phút.
 */
class ShareLinksApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // hook TH1 cần hexagram_hao_texts (ApiTestCase chỉ seed 64 quẻ)
        (new \Database\Seeders\HaoTextSeeder())->run();
    }
    /** Draw của device chỉ định, hào động tùy chọn. */
    private function makeDraw(string $deviceId, int $hexagramId = 11, array $changing = [2, 4]): Draw
    {
        return Draw::query()->create([
            'device_id' => $deviceId,
            'hexagram_id' => $hexagramId,
            'drawn_date' => self::VN_DATE,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => $changing,
        ]);
    }

    // ---------- migration: đúng 1 bảng, không sửa draws ----------

    public function test_share_links_table_shape(): void
    {
        $cols = [];
        foreach (DB::select('SHOW COLUMNS FROM share_links') as $r) {
            $cols[$r->Field] = $r;
        }
        $this->assertSame(
            ['id', 'token', 'draw_id', 'device_id', 'created_at', 'views'],
            array_keys($cols),
            'đúng 6 cột theo SPEC-THE §5'
        );
        $this->assertStringContainsString('char(10)', strtolower($cols['token']->Type));
        $this->assertSame('NO', $cols['token']->Null, 'token UNIQUE NOT NULL');

        $indexes = [];
        foreach (DB::select('SHOW INDEX FROM share_links') as $r) {
            $indexes[$r->Key_name][] = $r->Column_name;
        }
        $this->assertArrayHasKey('uq_share_links_token', $indexes);
        $this->assertArrayHasKey('uq_share_links_draw_device', $indexes);
        $this->assertSame(['draw_id', 'device_id'], $indexes['uq_share_links_draw_device']);
    }

    // ---------- POST 201 + idempotency ----------

    public function test_post_returns_201_token_url(): void
    {
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev);

        $resp = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id]);
        $resp->assertStatus(201)
            ->assertJsonStructure(['token', 'url']);

        $token = $resp->json('token');
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{10}$/', $token, 'token base62(10)');
        $this->assertSame(config('app.url')."/s/{$token}", $resp->json('url'));
        $this->assertSame(1, ShareLink::query()->count());
    }

    public function test_same_device_same_draw_returns_same_token(): void
    {
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev);

        $t1 = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');
        $t2 = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $this->assertSame($t1, $t2, 'idempotent per device+draw');
        $this->assertSame(1, ShareLink::query()->count());
    }

    public function test_two_devices_own_draws_get_different_tokens(): void
    {
        $devA = $this->deviceId();
        $drawA = $this->makeDraw($devA);

        $devB = $this->deviceId();
        $drawB = $this->makeDraw($devB); // draw của B (unique C-01 theo device — OK)

        $ta = $this->asDevice($devA)->postJson('/api/share-links', ['draw_id' => $drawA->id])->json('token');
        $tb = $this->asDevice($devB)->postJson('/api/share-links', ['draw_id' => $drawB->id])->json('token');

        $this->assertNotSame($ta, $tb, 'token không suy ra từ draw_id tuần tự (E4)');
        $this->assertSame(2, ShareLink::query()->count());
    }

    // ---------- lỗi ranh giới ----------

    public function test_draw_of_another_device_returns_404_not_found(): void
    {
        $owner = $this->deviceId();
        $draw = $this->makeDraw($owner);

        $intruder = $this->deviceId();
        $resp = $this->asDevice($intruder)->postJson('/api/share-links', ['draw_id' => $draw->id]);
        $resp->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertSame(0, ShareLink::query()->count());
    }

    public function test_missing_or_malformed_draw_id_returns_422_envelope(): void
    {
        $dev = $this->deviceId();
        $this->asDevice($dev)->postJson('/api/share-links', [])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => 'abc'])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_throttle_10_per_minute_ip_attached(): void
    {
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev);
        $this->asDevice($dev);

        $statuses = [];
        for ($i = 0; $i < 11; $i++) {
            $statuses[] = $this->postJson('/api/share-links', ['draw_id' => $draw->id])->getStatusCode();
        }
        $this->assertSame(10, count(array_filter($statuses, fn ($s) => $s === 201)));
        $this->assertSame(429, end($statuses), 'throttle 10/phút/IP phải chặn request 11');
    }

    // ---------- GET payload công khai: shape + bất biến chống lộ ----------

    public function test_get_returns_exact_public_shape(): void
    {
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev);
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        // device lạ đọc (không cookie sharer)
        $resp = $this->asDevice(null)->getJson("/api/share-links/{$token}");
        $resp->assertOk();

        $json = $resp->json();
        $this->assertSame(['card', 'sharer_label', 'views'], array_keys($json), 'đúng 3 key top-level');
        $this->assertSame(
            // VS3-S3 t_3b27cbde: +2 khóa dẫn xuất is_today/drawn_date_full → 10 key
            ['hexagram_id', 'symbol', 'ten', 'drawn_date', 'is_today', 'drawn_date_full', 'hook', 'keywords', 'disclaimer', 'qr_text'],
            array_keys($json['card']),
            'card đúng 10 key, không hơn'
        );
        $this->assertSame(['text', 'source', 'text_clip80'], array_keys($json['card']['hook']), 'BUG-F7-QA3 t_b0cfc1b4: hook 3 key (text NGUYÊN VĂN + source + bản hiển ≤80)');
        $this->assertSame(11, $json['card']['hexagram_id']);
        $this->assertSame('dd/MM', 'dd/MM'); // format check bên dưới
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}$#', $json['card']['drawn_date']);
        $this->assertSame('30/08', $json['card']['drawn_date'], 'dd/MM của '.self::VN_DATE);
        $this->assertSame('Giải trí · tham khảo văn hoá', $json['card']['disclaimer']);
        $this->assertSame("/s/{$token}", $json['card']['qr_text']);
        $this->assertCount(4, $json['card']['keywords']);
        $this->assertIsString($json['card']['keywords'][3]);
        $this->assertSame(0, $json['views']);
        // sharer_label: 4 ký tự cuối device, KHÔNG lộ device_id đầy đủ
        $this->assertSame(substr($dev, -4), substr($json['sharer_label'], -4));
        $this->assertStringNotContainsString($dev, $json['sharer_label']);
    }

    public function test_payload_never_contains_interpretation_keys(): void
    {
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev);
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $raw = $this->asDevice(null)->getJson("/api/share-links/{$token}")->getContent();

        foreach (['free_content', 'han', 'quoc_am', 'vv_nien', 'cat', 'ban_goc', 'luan_nay', 'bien_hexagram_id'] as $forbidden) {
            $this->assertStringNotContainsString('"' . $forbidden . '"', $raw, "payload chứa key cấm '{$forbidden}'");
        }
        // không lộ danh tính nội khu
        $this->assertStringNotContainsString('draw_id', $raw);
        $this->assertStringNotContainsString('device_id', $raw);
        $this->assertStringNotContainsString($dev, $raw);
    }

    // ---------- hook logic SPEC-THE §2 ----------

    public function test_hook_th1_uses_smallest_changing_line_nghia(): void
    {
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev, 11, [4, 2, 6]); // thứ tự lộn — phải lấy vi NHỎ NHẤT = 2
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $expect = HaoText::query()->where('hexagram_id', 11)->where('vi', 2)->sole()->nghia;

        $this->assertSame('hao_dong', $hook['source']);
        $this->assertSame($expect, $hook['text'], 'BE trả nguyên văn nghĩa Việt, không cắt giữa từ');
        $this->assertStringNotContainsString('䷁', $hook['text'], 'hook phải thuần tiếng Việt');
    }

    public function test_hook_th2_zero_changing_uses_dai_ci_first_clause(): void
    {
        $dev = $this->deviceId();
        // id 1: "Sáu hào đều dương — trời chạy mãi..." → vế đầu trước "—"
        $draw = $this->makeDraw($dev, 1, []);
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $this->assertSame('dai_ci', $hook['source']);
        $this->assertSame('Sáu hào đều dương', trim($hook['text']));
    }

    public function test_hook_th2_splits_on_comma_when_no_emdash(): void
    {
        Hexagram::query()->where('id', 2)->update(['dai_ci' => 'Đất đỡ muôn vật, mà không nói một lời.']);
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev, 2, []);
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $this->assertSame('dai_ci', $hook['source']);
        $this->assertSame('Đất đỡ muôn vật', $hook['text']);
    }

    public function test_hook_e6_empty_dai_ci_is_minimal(): void
    {
        Hexagram::query()->where('id', 2)->update(['dai_ci' => '']);
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev, 2, []);
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $this->assertSame('', $hook['text']);
        $this->assertSame('minimal', $hook['source']);
    }

    public function test_long_first_clause_not_clipped_by_be(): void
    {
        $long = str_repeat('chữ ', 30); // 180 ký tự, không dấu câu
        Hexagram::query()->where('id', 3)->update(['dai_ci' => $long]);
        $dev = $this->deviceId();
        $draw = $this->makeDraw($dev, 3, []);
        $token = $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');

        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $this->assertGreaterThan(80, mb_strlen(trim($hook['text'])), 'BE không cắt 80 — FE clip80 tại ranh giới từ');
    }

    // ---------- GET token lạ: 404 nhẹ E3 ----------

    public function test_get_unknown_token_returns_light_404(): void
    {
        $resp = $this->getJson('/api/share-links/zzzzzzzzzz');
        $resp->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $body = $resp->getContent();
        $this->assertStringNotContainsString('draw', strtolower($body));
        $this->assertStringNotContainsString('device', strtolower($body));
    }

    public function test_get_malformed_token_returns_404_not_500(): void
    {
        foreach (['short', 'has-dash1x', 'ALLUPPER!!', '12345678901234'] as $bad) {
            $this->getJson("/api/share-links/{$bad}")->assertStatus(404);
        }
    }
}
