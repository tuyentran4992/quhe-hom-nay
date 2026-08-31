<?php

namespace Tests\Feature\Api;

use App\Domain\Luan;
use App\Domain\PromptBuilder;
use Database\Seeders\HaoTextSeeder;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * BE-3XU — luật luận §4bis phần LƯU + PROMPT (card: "quẻ biến tính + lưu DB,
 * không vào prompt/KHÔNG ra UI"; testplan F10 QA):
 *  - bien_hexagram_id = quẻ gốc XOR hào động, NULL khi 0 hào động
 *  - payload #3/#4/#1/#10 KHÔNG chứa bất kỳ field nào nhắc quẻ biến
 *  - user prompt AI: 0 động → chỉ đại ý; ≥1 động → đủ từ hào theo thứ tự sơ→thượng.
 */
final class BienLuuVaPromptTest extends ApiTestCase
{
    private const DRAW_SECRET = 'bien-luu-draw-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HaoTextSeeder::class);
    }

    protected function tearDown(): void
    {
        putenv('QA_MOCK_LINES');
        parent::tearDown();
    }

    /** @param int[6] $lines @return array JSON payload 201 của #3 */
    private function drawWith(array $lines, string $key): array
    {
        putenv('QA_MOCK_LINES=' . json_encode($lines));
        $res = $this->json('POST', '/api/draws', [], $this->drawHeaders($key));
        $res->assertStatus(201);

        return $res->json();
    }

    public function test_que_bien_duoc_luu_dung_va_khong_lo_api(): void
    {
        // Càn [7,7,7,7,7,7] hào 2 động → bitmask 101111 → tra id13 (Thiên Hoả Đồng
        // Nhân) — case kinh điển §4bis, đã verify đối chiếu hexagrams.json.
        $payload = $this->drawWith([7, 9, 7, 7, 7, 7], self::DRAW_SECRET . '-a');

        $draw = DB::table('draws')->orderByDesc('id')->first();
        $this->assertSame(13, (int) $draw->bien_hexagram_id, 'biến của Càn hào 2 động = id13');
        $this->assertSame([2], array_map(intval(...), json_decode((string) $draw->changing_lines, true)));

        // 03-api §3 BẤT BIẾN: payload cũ + hao_texts, KHÔNG có field biến nào.
        $this->assertSame(['draw', 'hexagram', 'hao_texts', 'already_drawn'], array_keys($payload['data']));
        $this->assertStringNotContainsStringIgnoringCase('bien', json_encode($payload, JSON_PARTIAL_OUTPUT_ON_ERROR));
        $this->assertStringNotContainsStringIgnoringCase('28', json_encode($payload['data']['draw']));
    }

    public function test_khong_hao_dong_bien_null(): void
    {
        $this->drawWith([7, 8, 7, 8, 7, 8], self::DRAW_SECRET . '-b'); // tĩnh hết, không pattern đổi

        $draw = DB::table('draws')->orderByDesc('id')->first();
        $this->assertNull($draw->bien_hexagram_id);
    }

    public function test_prompt_khong_hao_dong_chi_dai_y(): void
    {
        $this->drawWith([7, 8, 7, 8, 7, 8], self::DRAW_SECRET . '-c');
        $draw = \App\Models\Draw::query()->orderByDesc('id')->first();

        $prompt = PromptBuilder::userPrompt(
            $draw->hexagram->toArray(), 'duyen', $draw->changing_lines ?? [],
            (new Luan())->haoTextsForDraw($draw)
        );

        $this->assertStringContainsString('Đại ý: ' . $draw->hexagram->dai_ci, $prompt);
        $this->assertStringNotContainsString('Hào động vi', $prompt);
        // quẻ biến không được len vào prompt dưới bất kỳ hình thức nào
        $bien = DB::table('hexagrams')->where('id', 28)->value('ten');
        $this->assertStringNotContainsString((string) $bien, $prompt);
    }

    public function test_prompt_hao_dong_theu_thu_tu_so_thuong(): void
    {
        // id1 Càn, hào 2 + 6 động (9)
        $this->drawWith([7, 9, 7, 7, 7, 9], self::DRAW_SECRET . '-d');
        $draw = \App\Models\Draw::query()->orderByDesc('id')->first();

        $luan = (new Luan())->haoTextsForDraw($draw);
        $this->assertCount(2, $luan);
        $prompt = PromptBuilder::userPrompt($draw->hexagram->toArray(), 'duyen', [2, 6], $luan);

        $posHao2 = strpos($prompt, 'Hào động vi2');
        $posHao6 = strpos($prompt, 'Hào động vi6');
        $this->assertNotFalse($posHao2);
        $this->assertNotFalse($posHao6);
        $this->assertLessThan($posHao6, $posHao2, 'phải xếp sơ→thượng');
        // đủ cả 3 lớp: Hán + quốc âm + nghĩa của TỪNG hào động (id1 vi2 = 見在… )
        $this->assertStringContainsString('Hán:', $prompt);
        $this->assertStringContainsString('Quốc âm:', $prompt);
        $this->assertStringContainsString('Nghĩa:', $prompt);
    }

    /** #4 (gate): draw[] format cũ, THÊM `hao_texts` cạnh đổi — không phá FE cũ. */
    public function test_history_giu_format_cu_them_hao_texts(): void
    {
        $this->drawWith([7, 9, 7, 7, 7, 9], self::DRAW_SECRET . '-e');
        $devId = DB::table("draws")->orderByDesc("id")->value("device_id");
        $res = $this->asDevice($devId)->getJson("/api/draws/history?limit=5")->assertOk();
        $row = $res->json('data.0');
        $this->assertSame(
            ['id', 'hexagram_id', 'drawn_date', 'lines_rolled', 'changing_lines', 'created_at', 'hao_texts'],
            array_keys($row),
            '#4 draw[] phải giữ đúng 6 field cũ + 1 field mới cuối'
        );
        $this->assertCount(2, $row['hao_texts']);
        $this->assertSame([2, 6], array_column($row['hao_texts'], 'vi'));
        foreach ($row['hao_texts'] as $t) {
            $this->assertSame(['vi', 'hao', 'han', 'quoc_am', 'nghia'], array_keys($t));
        }
    }
}
