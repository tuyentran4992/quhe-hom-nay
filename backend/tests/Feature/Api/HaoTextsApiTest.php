<?php

namespace Tests\Feature\Api;

use Database\Seeders\HaoTextSeeder;
use Tests\ApiTestCase;

/**
 * BE-3XU F9 (05-testplan §F9 + 03-api §2b/§3): #3 trả data.hao_texts theo luật
 * ≥1 hào động; #2b tra 6 từ hào của 1 quẻ. Roller mock qua QA_MOCK_LINES
 * (05 E7: "BE-1 chừa sẵn: đọc JSON 6 số từ env, chỉ bật khi APP_ENV!=production")
 * — không mong chờ ngẫu nhiên, không phá final của domain.
 */
class HaoTextsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new HaoTextSeeder())->run();
    }

    protected function tearDown(): void
    {
        putenv('QA_MOCK_LINES');
        unset($_ENV['QA_MOCK_LINES']);
        parent::tearDown();
    }

    private function mockLines(array $lines): void
    {
        putenv('QA_MOCK_LINES='.json_encode($lines));
    }

    public function test_f9_draw_with_moving_lines_returns_hao_texts_ordered_so_to_thuong(): void
    {
        // hào 2 âm động (6) + hào 6 dương động (9)
        $this->mockLines([7, 6, 7, 7, 7, 9]);

        $resp = $this->asDevice($this->deviceId())->postJson('/api/draws', []);
        $resp->assertStatus(201)->assertJsonPath('data.draw.changing_lines', [2, 6]);

        $texts = $resp->json('data.hao_texts');
        $this->assertIsArray($texts);
        $this->assertCount(2, $texts, 'số phần tử phải đúng k hào động');
        $this->assertSame([2, 6], array_column($texts, 'vi'), 'vi tăng dần sơ→thượng');
        foreach ($texts as $t) {
            $this->assertSame(['vi', 'hao', 'han', 'quoc_am', 'nghia'], array_keys($t));
            $this->assertNotSame('', trim($t['han']));
            $this->assertNotSame('', trim($t['quoc_am']));
            $this->assertNotSame('', trim($t['nghia']));
        }
        // đúng hàng của quẻ gốc đã tra (không phải quẻ biến!)
        $hexId = (int) $resp->json('data.hexagram.id');
        $expected = \DB::table('hexagram_hao_texts')->where('hexagram_id', $hexId)
            ->whereIn('vi', [2, 6])->orderBy('vi')->get()
            ->map(fn ($r) => [
                'vi' => (int) $r->vi, 'hao' => $r->hao, 'han' => $r->han,
                'quoc_am' => $r->quoc_am, 'nghia' => $r->nghia,
            ])->all();
        $this->assertSame($expected, $texts);
    }

    public function test_f9_zero_moving_lines_returns_empty_array_not_null(): void
    {
        $this->mockLines([7, 8, 7, 8, 7, 8]);

        $resp = $this->asDevice($this->deviceId())->postJson('/api/draws', []);
        $resp->assertStatus(201)->assertJsonPath('data.hao_texts', []);
    }

    public function test_f9_draw_payload_has_no_bien_hexagram_leak(): void
    {
        // §4bis: quẻ biến tính + lưu nội bộ, KHÔNG ra API/MVP response
        $this->mockLines([9, 8, 7, 8, 7, 8]);

        $resp = $this->asDevice($this->deviceId())->postJson('/api/draws', []);
        $resp->assertStatus(201);

        $this->assertSame(
            ['id', 'hexagram_id', 'drawn_date', 'lines_rolled', 'changing_lines', 'created_at'],
            array_keys($resp->json('data.draw')),
            'shape draw §3.2 bất biến — không leak field quẻ biến'
        );
        $this->assertSame(
            ['draw', 'hexagram', 'hao_texts', 'already_drawn'],
            array_keys($resp->json('data')),
            'data #3 = đúng 4 key hợp đồng §3 (thêm hao_texts, không gì khác)'
        );
    }

    public function test_f9_endpoint_2b_returns_six_texts_for_valid_id(): void
    {
        $resp = $this->getJson('/api/hexagrams/11/hao-texts');
        $resp->assertStatus(200)
            ->assertJsonPath('data.hexagram_id', 11)
            ->assertJsonStructure(['data' => ['hexagram_id', 'hao' => [
                ['vi', 'hao', 'han', 'quoc_am', 'nghia'],
                ['vi', 'hao', 'han', 'quoc_am', 'nghia'],
                ['vi', 'hao', 'han', 'quoc_am', 'nghia'],
                ['vi', 'hao', 'han', 'quoc_am', 'nghia'],
                ['vi', 'hao', 'han', 'quoc_am', 'nghia'],
                ['vi', 'hao', 'han', 'quoc_am', 'nghia'],
            ]]]);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($resp->json('data.hao'), 'vi'));
        // khớp DB
        $this->assertSame(
            \DB::table('hexagram_hao_texts')->where('hexagram_id', 11)->orderBy('vi')->count(),
            count($resp->json('data.hao'))
        );
    }

    public function test_f9_endpoint_2b_out_of_range_is_404_not_found(): void
    {
        $this->getJson('/api/hexagrams/65/hao-texts')->assertStatus(404);
        $this->getJson('/api/hexagrams/0/hao-texts')->assertStatus(404);
    }
}
