<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Models\Draw;
use App\Services\AiBoxClient;
use Database\Seeders\HaoTextSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LUAN-V2 (card t_c86f3954, SPEC-LUAN-V2 §6/§9 T14–T18) — prompt 4 khối:
 * (a) dòng hoàn cảnh khi có question, (b) khối chỉ dẫn chọn lời từ BianRule
 * (case 3/6 được MỞ nội dung biến — D2; case 0/1/2/4/5 vẫn CẤM, đối chứng leak),
 * (c) lệnh bố cục BẮT BUỘC 3 marker, (d) điều CẤM bịa hoàn cảnh khi question trống.
 *
 * Capture prompt bằng Http::assertSent trên body chat/completions — mock provider
 * là ranh giới contract thật của worker (RunAiBoxJob đi qua queue database).
 */
class PromptLuanV2Test extends Be2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new HaoTextSeeder)->run();
    }

    /** Draw mới (device mới → không dính cooldown) đổi cả 3 topic, changing tùy ý. */
    private function drawWith(int $hexId, array $changing, ?int $bienId): Draw
    {
        $d = $this->device();
        foreach (['duyen', 'tai_loc', 'xuat_hanh'] as $t) {
            $this->payUnlock($d, $t);
        }

        return Draw::query()->create([
            'device_id' => $d->device_id,
            'hexagram_id' => $hexId,
            'bien_hexagram_id' => $bienId,
            'drawn_date' => now()->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d'),
            'lines_rolled' => [7, 7, 7, 7, 7, 7],
            'changing_lines' => $changing,
        ]);
    }

    /** #5 + worker inline, trả user prompt đã gửi lên provider (topic đổi được để tránh cache-hit giữa các bước). */
    private function userPromptOf(Draw $draw, ?string $question, string $topic = 'duyen'): string
    {
        $d = Device::findOrFail($draw->device_id);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => $topic, 'idempotency_key' => 'p-'.Str::random(16),
            ...($question !== null ? ['question' => $question] : []),
        ])->assertStatus(202)->json('data');
        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));

        $captured = '';
        Http::assertSent(function ($request) use (&$captured) {
            if (str_contains($request->url(), 'chat/completions')) {
                $body = json_decode($request->body(), true);
                foreach ($body['messages'] ?? [] as $m) {
                    if (($m['role'] ?? '') === 'user') {
                        $captured = $m['content'];
                    }
                }
            }

            return true;
        });
        $this->assertNotSame('', $captured, 'worker phải gọi provider với user prompt');

        return $captured;
    }

    /** T14 — 3 marker bố cục BẮT BUỘC có mặt trong prompt gửi đi. */
    public function test_prompt_co_dong_3_phan_marker(): void
    {
        $this->fakeAi($this->cleanMd);
        $prompt = $this->userPromptOf($this->drawWith(11, [2], 20), 'có nên đổi việc không');
        $this->assertStringContainsString('[Hoàn cảnh]', $prompt);
        $this->assertStringContainsString('[Vì sao khuyên vậy]', $prompt);
        $this->assertStringContainsString('[Việc nên làm cụ thể tuần này', $prompt);
        $this->assertStringContainsString('Bố cục BẮT BUỘC 3 phần', $prompt);
    }

    /** T15 — question null → CẤM bịa hoàn cảnh; có question → dòng "Khách đang vướng" (bản trim). */
    public function test_prompt_cam_doan_khi_question_trong(): void
    {
        $this->fakeAi($this->cleanMd);
        $noQ = $this->userPromptOf($this->drawWith(11, [2], 20), null);
        $this->assertStringContainsString('CẤM bịa hoặc đoán hoàn cảnh riêng', $noQ);
        $this->assertStringNotContainsString('Khách đang vướng', $noQ);

        $this->fakeAi($this->cleanMd);
        // REVIEW-LUAN (t_8aa93a01): hex 11+duyen đã done ở bước trên → lần 2 dùng
        // quẻ khác (khóa 1 lượt per (hexagram,topic)), prompt vẫn là template duyen.
        $withQ = $this->userPromptOf($this->drawWith(12, [2], 20), 'bao giờ có người yêu');
        $this->assertStringContainsString('Khách đang vướng: "bao giờ có người yêu"', $withQ);
        $this->assertStringNotContainsString('CẤM bịa hoặc đoán hoàn cảnh riêng', $withQ);
    }

    /** T16 — câu cũ biến mất hẳn, thay bằng khối Luật Biện quẻ có số hào động. */
    public function test_prompt_khong_con_cau_uu_tien_luan_theo_hao_dong(): void
    {
        $this->fakeAi($this->cleanMd);
        $prompt = $this->userPromptOf($this->drawWith(11, [2, 4], 11), 'abc');
        $this->assertStringNotContainsString('ưu tiên luận theo tượng hào động', $prompt);
        $this->assertStringContainsString('Luật Biện quẻ (số hào động: 2)', $prompt);
    }

    /** T17 — case 3 + 6 MỞ biến (D2); case 1 ĐỐI CHỨNG vẫn cấm. */
    public function test_prompt_case_3_va_6_duoc_quese_bien(): void
    {
        // Càn id1 hào 1,2,3 động → biến id12 Thiên Địa Bĩ: quẻ từ BIẾN vào prompt (duyen).
        $this->fakeAi($this->cleanMd);
        $p3 = $this->userPromptOf($this->drawWith(1, [1, 2, 3], 12), null, 'duyen');
        $this->assertStringContainsString('Quẻ biến', $p3);
        $this->assertStringContainsString('Thiên Địa Bĩ', $p3);
        $bi = \DB::table('hexagrams')->where('id', 12)->value('dai_ci');
        $this->assertStringContainsString(trim((string) $bi), $p3, 'đại ý id12 phải vào prompt');

        // Càn 6 động → biến id2 Khôn: lời DỤNG (Dụng cửu / quần long) vào prompt.
        $this->fakeAi($this->cleanMd);
        $p6 = $this->userPromptOf($this->drawWith(1, [1, 2, 3, 4, 5, 6], 2), null, 'tai_loc');
        $this->assertStringContainsString('Dụng cửu', $p6);
        $this->assertStringContainsString('quần long vô thủ', $p6);

        // Đối chứng case 1 (Càn hào 2 đơn động → biến id13): KHÔNG leak Đồng Nhân/䷌/Quẻ biến.
        $this->fakeAi($this->cleanMd);
        $p1 = $this->userPromptOf($this->drawWith(1, [2], 13), null, 'xuat_hanh');
        $this->assertStringNotContainsString('Đồng Nhân', $p1);
        $this->assertStringNotContainsString('䷌', $p1);
        $this->assertStringNotContainsString('Quẻ biến', $p1);
    }

    /** T18 — case 4: luật chọn hào TĨNH 5,6 — block hào dẫn đúng 2 hào tĩnh, không dẫn hào động. */
    public function test_prompt_case_4_5_dan_hao_tinh(): void
    {
        $this->fakeAi($this->cleanMd);
        $p = $this->userPromptOf($this->drawWith(1, [1, 2, 3, 4], 20), null);
        $this->assertStringContainsString('Hào động vi5', $p);
        $this->assertStringContainsString('Hào động vi6', $p);
        foreach ([1, 2, 3, 4] as $vi) {
            $this->assertStringNotContainsString("Hào động vi{$vi} (", $p, "case 4 cấm dẫn lời hào vi{$vi}");
        }
        // biến id20 Phong Địa Quan CẤM vào prompt (case 4 giữ nguyên §4bis, D2)
        $this->assertStringNotContainsString('Phong Địa Quan', $p);
        $this->assertStringNotContainsString('Quẻ biến', $p);
    }
}
