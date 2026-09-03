<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\AiBoxClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * QUOTA-N/Q2 (card t_1b5a0c23) — quota theo DRAW cho lượt LUAN SAU THAT:
 * mỗi quẻ tối đa N lần gọi LLM thật, N = config('project.ai.max_deep_reads_per_draw')
 * (env MAX_DEEP_READS_PER_DRAW, default 3 — D1). Cache row (from_cache) KHÔNG
 * trừ lượt; hết N → 429 code 'quota_exceeded' (chữ thường theo đúng literal
 * card — FE Q4/QA Q5 copy nguyên văn chuỗi này, KHÔNG đổi casing).
 *
 * Test matrix card: (a) đếm đúng 3 nhat (that-dem / cache-khong-dem /
 * failed-khong-dem), (b) het N → 429 + #5b cache hit vẫn trả đủ bài, (c) N đổi
 * qua env (unit ProjectConfigTest) + qua config ở đây chứng minh không hardcode.
 * (d)=full suite, (e)=đọc code ghi closeout.
 *
 * free_deep_preview BẬT (bỏ 402) + lock_one_luan TẮT: cổng khóa 1 lượt/quẻ+topic
 * cũ mà bật thì không BAO GIỜ tích được >1 done/draw để đo tầng 3 — hàng rào cũ
 * đã có AlreadyDoneLockTest + InterpretationGateTest phủ regression ở nguyên
 * config mặc định của chúng.
 */
class QuotaPerDrawTest extends Be2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'project.free_deep_preview' => true,
            'project.ai.lock_one_luan' => false,
        ]);
    }

    /** POST #5 + chạy worker thật (queue database → pending) tới done. Trả job done. */
    private function realRead(Device $d, int $drawId, string $topic): AiJob
    {
        $res = $this->postInterp($d, $drawId, $topic)->assertStatus(202)->json('data');

        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));
        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status, 'worker phải đưa job tới done để đếm lượt');

        return $job;
    }

    /** Seed thẳng job trạng thái terminal (không qua worker) — cho ca cache/failed. */
    private function seedJob(Device $d, int $drawId, string $topic, string $status, bool $fromCache = false): AiJob
    {
        $job = AiJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'device_id' => $d->device_id,
            'draw_id' => $drawId,
            'topic' => $topic,
            'status' => AiJob::ST_QUEUED,
            'requested_at' => now()->subMinutes(10),
            'result_key_hash' => hash('sha256', $drawId.'|'.$topic.'|seed'),
        ]);
        $job->forceFill([
            'status' => $status,
            'from_cache' => $fromCache,
            'finished_at' => now(),
            'result' => $status === AiJob::ST_DONE ? $this->cleanMd : null,
            'error_code' => $status === AiJob::ST_FAILED ? 'AI_UPSTREAM' : null,
        ])->save();

        return $job;
    }

    /** Kéo requested_at về quá khứ — device qua cooldown C-03 giữa các bước. */
    private function clearCooldown(Device $d): void
    {
        AiJob::query()->where('device_id', $d->device_id)->update(['requested_at' => now()->subMinutes(10)]);
    }

    private function postInterp(Device $d, int $drawId, string $topic)
    {
        // postInterp luôn xóa cooldown trước: nếu test vẫn đỏ vì AI_COOLDOWN thì đó là
        // bằng chứng gate quota nằm SAI thứ tự so với cooldown — assert code 429 dưới.
        $this->clearCooldown($d);

        return $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawId, 'topic' => $topic, 'idempotency_key' => 'q-'.Str::random(16),
        ]);
    }

    /**
     * (a1)+(b) DONE THAT dem: N=2 → 2 lượt thật OK, lượt 3 → 429 quota_exceeded
     * ĐÚNG envelope, không tạo rac job, provider đứng yên (Http count = 2).
     */
    public function test_a_done_that_dem_den_nguong_429_quota_exceeded(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 2]);
        $this->fakeAi($this->cleanMd);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);

        $this->realRead($d, $draw->id, 'duyen');
        $this->realRead($d, $draw->id, 'tai_loc');

        $res = $this->postInterp($d, $draw->id, 'xuat_hanh')->assertStatus(429);
        $res->assertJsonPath('error.code', 'quota_exceeded')
            ->assertJsonPath('error.details.max_deep_reads_per_draw', 2)
            ->assertJsonPath('error.details.used', 2)
            ->assertJsonPath('error.details.remaining', 0);

        // phân biệt code với cooldown/cap HIỆN CÓ (D2.3)
        $this->assertNotSame('AI_COOLDOWN', $res->json('error.code'));
        $this->assertNotSame('AI_GLOBAL_CAP', $res->json('error.code'));

        $this->assertSame(2, AiJob::query()->where('draw_id', $draw->id)->count(),
            '429 quota không được tạo rac job');
        Http::assertSentCount(2); // 2 request bị chặn = 0 call provider thứ 3
    }

    /** (a2) DONE TU CACHE (from_cache=true) KHONG dem: N=1 + 1 cache-done → vẫn thông. */
    public function test_a_done_tu_cache_khong_tru_luot(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->seedJob($d, $draw->id, 'duyen', AiJob::ST_DONE, fromCache: true);

        $this->postInterp($d, $draw->id, 'tai_loc')->assertStatus(202);
        Http::assertSentCount(0); // request 202 mới queued — provider chưa chạy

        // lượt thật đầu tiên xong → đúng ngưỡng; lượt kế = 429 (cache chưa từng được tính)
        $job = AiJob::query()->where('draw_id', $draw->id)->where('status', AiJob::ST_QUEUED)->firstOrFail();
        (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));
        $this->postInterp($d, $draw->id, 'xuat_hanh')->assertStatus(429)
            ->assertJsonPath('error.code', 'quota_exceeded')
            ->assertJsonPath('error.details.used', 1);
    }

    /** (a3) FAILED khong dem: failed row không chiếm quota. */
    public function test_a_failed_khong_dem(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->seedJob($d, $draw->id, 'duyen', AiJob::ST_FAILED);

        $this->postInterp($d, $draw->id, 'tai_loc')->assertStatus(202);
    }

    /**
     * (b) Het N: POST mới → 429, nhưng DUONG DOC LAI (#5b saved — cache hit thời
     * REVIEW-LUAN) khong nam sau quota gate: van tra du bai cu, 0 dem.
     */
    public function test_b_het_n_cho_duong_doc_lai_van_tra_du_bai(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $done = $this->realRead($d, $draw->id, 'duyen');

        // quota cạn: POST chủ đề khác → 429
        $this->postInterp($d, $draw->id, 'tai_loc')->assertStatus(429)
            ->assertJsonPath('error.code', 'quota_exceeded');

        // đọc lại bài cũ không trừ lượt, không bị chặn:
        foreach ([0, 1, 2] as $i) {
            $this->cookieFor($d)->getJson('/api/ai/interpretations/saved?draw_id='.$draw->id.'&topic=duyen')
                ->assertOk()
                ->assertJsonPath('data.exists', true)
                ->assertJsonPath('data.job_uuid', $done->job_uuid)
                ->assertJsonPath('data.result', $this->cleanMd);
        }
        $this->assertSame(1, AiJob::query()->where('draw_id', $draw->id)->count());
        Http::assertSentCount(1); // 3 lần đọc lại = 0 call
    }

    /**
     * (c-fe) N doc TU CONFIG, khong hardcode 3: voi N=1 hanh vi doi ngay so voi
     * mac dinh 3 (2 done van con luot). Unit env-flag ben ProjectConfigTest.
     */
    public function test_c_nguong_theo_config_khong_hard_code(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);

        // mac dinh 3: 2 done that → con luot
        config(['project.ai.max_deep_reads_per_draw' => 3]);
        $this->assertSame(3, (int) config('project.ai.max_deep_reads_per_draw'));
        $this->fakeAi($this->cleanMd);
        $this->fakeAi($this->cleanMd);
        $this->realRead($d, $draw->id, 'duyen');
        $this->realRead($d, $draw->id, 'tai_loc');
        $this->postInterp($d, $draw->id, 'xuat_hanh')->assertStatus(202);

        // N=5 qua config: 3 done van 202 (hardcode 3 = do mau)
        config(['project.ai.max_deep_reads_per_draw' => 5]);
        $this->postInterp($d, $draw->id, 'duyen')->assertStatus(202);

        // N=1 qua config: quota cũ (draw khác) — dùng device/draw mới, 0 done → thông
        $e = $this->device();
        $drawE = $this->drawFor($e);
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->fakeAi($this->cleanMd);
        $this->realRead($e, $drawE->id, 'duyen');
        $this->postInterp($e, $drawE->id, 'tai_loc')->assertStatus(429);
    }

    /**
     * API #1/#10: remaining_deep_reads = max(0, N − lượt THẬT của draw hôm nay)
     * (D3 + card) — FE hiện "còn x/N". Chưa gieo quẻ hôm nay → nguyên N.
     */
    public function test_remaining_deep_reads_trong_me_va_me_today(): void
    {
        $d = $this->device();

        // chưa có quẻ hôm nay → còn nguyên N=3
        $this->cookieFor($d)->getJson('/api/me')->assertOk()
            ->assertJsonPath('remaining_deep_reads', 3);
        $this->cookieFor($d)->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.remaining_deep_reads', 3);

        $draw = $this->drawFor($d);
        $this->fakeAi($this->cleanMd);
        $this->realRead($d, $draw->id, 'duyen');

        $this->cookieFor($d)->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.remaining_deep_reads', 2);

        // cache row không trừ remaining; failed cũng không
        $this->seedJob($d, $draw->id, 'tai_loc', AiJob::ST_DONE, fromCache: true);
        $this->seedJob($d, $draw->id, 'xuat_hanh', AiJob::ST_FAILED);
        $this->cookieFor($d)->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.remaining_deep_reads', 2);

        // done thật lượt 2 → còn 1
        $this->fakeAi($this->cleanMd);
        $this->realRead($d, $draw->id, 'duyen'); // topic trùng: lock_one_luan đang OFF
        $this->cookieFor($d)->getJson('/api/me')->assertOk()
            ->assertJsonPath('remaining_deep_reads', 1);

        // quota N=1 qua config → remaining tụt tương ứng (chứng minh đọc config)
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->cookieFor($d)->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.remaining_deep_reads', 0);
    }
}
