<?php

namespace Tests\Feature\Api;

use App\Domain\Hexagram;
use App\Domain\PromptBuilder;
use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Models\Draw;
use App\Services\AiBoxClient;
use Database\Seeders\HaoTextSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LUAN-V3 (card t_a97403d8, SPEC-LUAN-V3 amended §3/§5/§8) — 4 template router
 * T-A/B/C/D với wording 100% PromptBuilder V2 @ eaced06 (văn phong GIỮ NGUYÊN —
 * verdict anh Tuyền 01/09 16:0x) + hành vi router trong worker.
 *
 * T19–T21: thuần hàm PromptBuilder (tham số cuối optional $routedTopic, §6.5).
 * T23–T28: worker Http fake — router là call CÓ `max_tokens` (temp 0), bước luận
 * không có; đếm call, kiểm tra điều phối route.
 */
class RouterV3Test extends Be2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new HaoTextSeeder)->run();
    }

    /** Row quẻ thật (snake_case) từ DB seeder — cùng nguồn worker dùng. */
    private function hex(int $id): array
    {
        return (array) DB::table('hexagrams')->where('id', $id)->first();
    }

    /** Dựng prompt qua chữ ký V2 8 tham số + $routedTopic thứ 9 (SPEC §3). */
    private function prompt(string $topic, ?string $question, ?string $routed = null): string
    {
        return PromptBuilder::userPrompt(
            $this->hex(11), $topic, [2],
            [], // không hào dẫn — đủ cho các assertion danh mục/tail
            $question, null, null, null,
            $routed
        );
    }

    /** T19 — routed 'tai_loc' trên tab 'duyen': label + free đổi theo routed, tail V2 còn nguyên. */
    public function test_promptbuilder_routed_topic_doi_label_va_free(): void
    {
        $p = $this->prompt('duyen', 'khi nào tài chính đỡ hơn', 'tai_loc');
        $this->assertStringContainsString('Chủ đề luận sâu: tài lộc.', $p);
        $this->assertStringContainsString('Góc nhìn sẵn có về tài lộc:', $p);
        $this->assertStringNotContainsString('Chủ đề luận sâu: tình duyên', $p);
        // free taiLoc so với tinhDuyen của tab gốc
        $free = json_decode((string) $this->hex(11)['free_content'], true) ?: [];
        $this->assertNotSame($free['taiLoc'] ?? null, $free['tinhDuyen'] ?? null, 'cần 2 free khác nhau để phân biệt');
        if (($free['taiLoc'] ?? '') !== '') {
            $this->assertStringContainsString((string) $free['taiLoc'], $p);
        }
        $this->assertStringNotContainsString($free['tinhDuyen'] ?? '¤KHONG¤', $p);
        // tail V2 nguyên văn — không đổi độ dài/giọng
        $this->assertStringContainsString('Giữ 200–400 từ', $p);
        $this->assertStringContainsString('Bố cục BẮT BUỘC 3 phần', $p);
    }

    /** T20 — T-C (KHONG_THUOC_NAO): xóa 2 dòng danh mục, thay bằng Việc khách hỏi + Ràng buộc, vẫn đủ 3 khối. */
    public function test_prompt_khong_thuoc_nao_xoa_2_dong_danh_muc(): void
    {
        $p = $this->prompt('duyen', 'bệnh của em có khỏi không', 'KHONG_THUOC_NAO');
        $this->assertStringNotContainsString('Chủ đề luận sâu', $p);
        $this->assertStringNotContainsString('Góc nhìn sẵn có', $p);
        $this->assertStringContainsString('Việc khách hỏi: "bệnh của em có khỏi không"', $p);
        $this->assertStringContainsString('Ràng buộc: mọi điều khuyên phải bám đúng lời quẻ', $p);
        $this->assertStringContainsString('CẤM bịa hoặc đoán hoàn cảnh riêng của khách. Chỉ luận đúng điều khách hỏi, bám lời quẻ.', $p);
        // vẫn đúng 3 khối marker V2 + Luật Biện quẻ
        $this->assertStringContainsString('[Hoàn cảnh]', $p);
        $this->assertStringContainsString('[Vì sao khuyên vậy]', $p);
        $this->assertStringContainsString('[Việc nên làm cụ thể tuần này', $p);
        $this->assertStringContainsString('Bố cục BẮT BUỘC 3 phần', $p);
        $this->assertStringContainsString('Luật Biện quẻ', $p);
        // dòng [Hoàn cảnh] mất hậu tố chủ đề (không còn trục danh mục)
        $this->assertStringNotContainsString('[Hoàn cảnh] — khung tình huống quẻ chỉ ra cho chủ đề', $p);
        // question có → đuôi dòng cấm cũ (không-hỏi) phải biến mất, giữ 'CẤM bịa…' + đuôi mới
        $this->assertStringNotContainsString('Không có câu hỏi nào được nêu', $p);
    }

    /** T21 — 4 template giữ wording V2; chuỗi vùng HỦY phải vắng mặt; T-A 0-diff. */
    public function test_4_template_giu_writing_v2(): void
    {
        $v2Baseline = $this->prompt('duyen', null); // luồng V2 không question
        $ta = $this->prompt('duyen', 'bao giờ có người yêu', 'duyen'); // T-A: route khớp tab
        $tb = $this->prompt('duyen', 'khi nào có tiền', 'tai_loc'); // T-B cross-tab
        $tc = $this->prompt('duyen', 'em thi lại có đậu không', 'KHONG_THUOC_NAO'); // T-C
        $td = $this->prompt('duyen', 'bao giờ có người yêu'); // T-D: router lỗi → routedTopic null + question

        foreach (['v2' => $v2Baseline, 'ta' => $ta, 'tb' => $tb, 'tc' => $tc, 'td' => $td] as $name => $p) {
            $this->assertStringContainsString('Bố cục BẮT BUỘC 3 phần', $p, $name);
            $this->assertStringContainsString('Giữ 200–400 từ', $p, $name);
            // các chuỗi thuộc vùng [HỦY theo verdict] cấm hiện diện
            foreach (['[Thì]', '[Vị]', '[Biện]', '240–420'] as $dead) {
                $this->assertStringNotContainsString($dead, $p, "$name chứa chuỗi đã hủy $dead");
            }
        }

        // T-A = 0-diff vs prompt V2 cùng question (regression baseline §3):
        // router khớp tab → worker truyền routedTopic = chính topic tab → chữ ký cũ y nguyên
        $taV2 = PromptBuilder::userPrompt($this->hex(11), 'duyen', [2], [], 'bao giờ có người yêu', null, null, null, 'duyen');
        $this->assertSame($ta, $taV2, 'T-A phải nguyên văn V2, không thêm dòng nào');
        // chữ ký V2 8 tham số (router LỖI → không có routedTopic) = T-D: +1 dòng tự xử
        $tdLegacy = PromptBuilder::userPrompt($this->hex(11), 'duyen', [2], [], 'bao giờ có người yêu');
        $this->assertStringContainsString('Nếu câu hỏi của khách không thuộc chủ đề đã nêu', $tdLegacy);
        // T-B: dòng chỉ dẫn cross-tab đặc trưng
        $this->assertStringContainsString('Khách hỏi thẳng điều này — luận đúng chuyện khách hỏi, đừng lái về chủ đề khác.', $tb);
        // T-D: đúng 1 dòng mới cuối tail so với T-A
        $this->assertStringContainsString('Nếu câu hỏi của khách không thuộc chủ đề đã nêu, cứ thẳng thắn đáp đúng câu hỏi ấy theo lời quẻ; không cứng nhắc kéo về chủ đề.', $td);
        $this->assertStringNotContainsString('Nếu câu hỏi của khách không thuộc', $ta);
        // T-C không có dòng danh mục
        $this->assertStringNotContainsString('Chủ đề luận sâu', $tc);
    }

    // ─── Feature: hành vi router trong worker (T23–T28) ───────────────────────

    private function drawWith(int $hexId, array $changing, ?int $bienId, Device $d): Draw
    {
        return Draw::query()->create([
            'device_id' => $d->device_id,
            'hexagram_id' => $hexId,
            'bien_hexagram_id' => $bienId,
            'drawn_date' => now()->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d'),
            'lines_rolled' => [7, 7, 7, 7, 7, 7],
            'changing_lines' => $changing,
        ]);
    }

    /** POST #5 (kèm question nếu có) rồi chạy worker inline; trả about job + captured calls. */
    private function runWorker(string $topic, ?string $question): AiJob
    {
        $d = $this->device();
        $this->payUnlock($d, $topic);
        $draw = $this->drawWith(11, [2], 20, $d);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => $topic, 'idempotency_key' => 'r-'.Str::random(16),
            ...($question !== null ? ['question' => $question] : []),
        ])->assertStatus(202)->json('data');
        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));

        return $job->refresh();
    }

    /** @return list<array{model:string,body:array}> các call chat/completions đã gửi */
    private function sentCalls(): array
    {
        $calls = [];
        Http::recorded(function (Request $req) use (&$calls) {
            if (! str_contains($req->url(), 'chat/completions')) {
                return;
            }
            $body = json_decode((string) $req->body(), true) ?: [];
            $calls[] = ['model' => (string) ($body['model'] ?? ''), 'body' => $body];
        });

        return $calls;
    }

    private function userPromptOf(array $call): string
    {
        foreach ($call['body']['messages'] ?? [] as $m) {
            if (($m['role'] ?? '') === 'user') {
                return (string) $m['content'];
            }
        }

        return '';
    }

    /** T23 — có question: router chạy TRƯỚC (temp 0 + max_tokens + đúng question), rồi bước luận. */
    public function test_worker_co_question_goi_router_truoc_luan(): void
    {
        $job = $this->runWorker('duyen', 'bao giờ em có người yêu');
        $this->assertSame(AiJob::ST_DONE, $job->status, 'job phải done, không fail vì router');

        $calls = $this->sentCalls();
        $this->assertCount(2, $calls, 'router + luận = đúng 2 call');
        [$router, $luan] = $calls;

        $this->assertSame(0, $router['body']['temperature'], 'router temperature 0');
        $this->assertArrayHasKey('max_tokens', $router['body']);
        $this->assertStringContainsString('bao giờ em có người yêu', $this->userPromptOf($router));
        // model router: config aibox.router_model fallback model luận — kiểm qua config đã khai báo
        $this->assertNotEmpty($router['model']);
        $this->assertSame(0.7, $luan['body']['temperature'], 'bước luận giữ 0.7');
        $this->assertArrayNotHasKey('max_tokens', $luan['body']);
        // hash D1 không đổi: 3 thành phần sha256(draw|topic|question)
        $this->assertSame(
            hash('sha256', $job->draw_id.'|duyen|bao giờ em có người yêu'),
            $job->result_key_hash
        );
    }

    /** T24 — không question: ĐÚNG 1 call (luồng V2 nguyên trạng). */
    public function test_worker_khong_question_khong_goi_router(): void
    {
        $job = $this->runWorker('duyen', null);
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $calls = $this->sentCalls();
        $this->assertCount(1, $calls);
        $this->assertArrayNotHasKey('max_tokens', $calls[0]['body']);
        $this->assertStringContainsString('CẤM bịa hoặc đoán hoàn cảnh riêng', $this->userPromptOf($calls[0]));
    }

    /** T25 — cross-tab: router trả tai_loc khi tab duyen → prompt T-B, DB ai_jobs.topic VẪN duyen. */
    public function test_worker_cross_tab_luan_dung_muc_khong_doi_job_topic(): void
    {
        $this->routerQueue[] = 'tai_loc';
        $job = $this->runWorker('duyen', 'khi nào tài chính đỡ hơn');
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $this->assertSame('duyen', $job->topic, 'router chỉ đổi prompt content, không đổi tab đã trả tiền');

        $calls = $this->sentCalls();
        $luanPrompt = $this->userPromptOf($calls[1]);
        $this->assertStringContainsString('Chủ đề luận sâu: tài lộc.', $luanPrompt);
        $this->assertStringContainsString('Khách hỏi thẳng điều này', $luanPrompt);
        $this->assertStringContainsString('Khách đang vướng: "khi nào tài chính đỡ hơn"', $luanPrompt);
    }

    /** T26 — router LỖI (mạng) → job vẫn done với prompt T-D, attempts không tăng thêm. */
    public function test_worker_router_loi_khong_bao_gio_lam_fail_job(): void
    {
        $this->routerQueue[] = new \Illuminate\Http\Client\ConnectionException('connection refused');
        $job = $this->runWorker('duyen', 'bao giờ em có người yêu');

        $this->assertSame(AiJob::ST_DONE, $job->status, 'router lỗi tuyệt đối không fail job luận');
        $this->assertSame(1, (int) $job->attempts, 'không đếm thêm attempts vì router');
        $calls = $this->sentCalls();
        // 1 call fail + 1 call luận thành công (Http::fake đếm cả request ném exception)
        $luan = end($calls);
        $p = $this->userPromptOf($luan);
        $this->assertStringContainsString('Khách đang vướng: "bao giờ em có người yêu"', $p);
        $this->assertStringContainsString('Nếu câu hỏi của khách không thuộc chủ đề đã nêu', $p, 'prompt phải là T-D fallback, không im lặng');
    }

    /** T27 — UNCLEAR → về luồng cũ: question coi như null trong prompt (CẤM bịa), vẫn giữ dòng… không có Khách đang vướng. */
    public function test_worker_unclear_ve_luong_cu_cam_bia(): void
    {
        $this->routerQueue[] = 'unclear';
        $job = $this->runWorker('duyen', '??? abc');
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $calls = $this->sentCalls();
        $p = $this->userPromptOf($calls[1] ?? $calls[0]);
        $this->assertStringContainsString('CẤM bịa hoặc đoán hoàn cảnh riêng', $p);
        $this->assertStringNotContainsString('Khách đang vướng', $p);
        $this->assertStringNotContainsString('Nếu câu hỏi của khách không thuộc', $p, 'UNCLEAR không phải T-D');
        // câu hỏi vẫn lưu DB (FE còn hiển thị "Bạn hỏi:")
        $this->assertSame('??? abc', $job->question);
    }

    /** T28 — cache key không đổi dù có router: 2 request cùng draw+topic+question → cùng hash. */
    public function test_cache_key_khong_doi_du_co_router(): void
    {
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawWith(11, [2], 20, $d);
        $hashes = [];
        foreach (['router-k1', 'router-k2'] as $i => $k) {
            if ($i === 1) {
                AiJob::query()->where('device_id', $d->device_id)->update(['requested_at' => now()->subMinutes(10)]);
            }
            $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
                'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => $k,
                'question' => 'bao giờ có người yêu',
            ])->assertStatus(202)->json('data');
            $hashes[] = AiJob::query()->where('job_uuid', $res['job_uuid'])->value('result_key_hash');
        }
        $this->assertSame($hashes[0], $hashes[1]);
        $this->assertSame(hash('sha256', $draw->id.'|duyen|bao giờ có người yêu'), $hashes[0]);
    }

    /** Router model: config mới khai báo, default rỗng → fallback model luận (§5.2). */
    public function test_config_router_model_fallback(): void
    {
        $this->assertArrayHasKey('router_model', config('aibox'));
        config(['aibox.router_model' => '']);
        $this->routerQueue[] = 'duyen';
        $job = $this->runWorker('duyen', 'abc duyen');
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $calls = $this->sentCalls();
        $routerModel = $calls[0]['model'];
        $luanModel = $calls[1]['model'];
        $this->assertNotSame('', $routerModel);
        $this->assertSame($luanModel, $routerModel, 'router_model rỗng → fallback đúng AIBOX_MODEL');
        config(['aibox.router_model' => 'router-small']);
        $this->assertSame('router-small', (string) config('aibox.router_model'));
    }
}
