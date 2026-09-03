<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\AiBoxClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * QUOTA-N/Q3 (card t_1bb07a82) — judge paraphrase gian DIEM MO RONG Q2
 * (InterpretationService c2.0, ngay TRUOC quota gate lop 3): 1 goi phan quyet
 * router-model xuat DU_GIONG|KHAC|UNCLEAR cho cau hoi moi vs bai done THAT
 * gan nhat CUNG draw_id co question.
 *
 * Matrix card:
 *  - DU_GIONG → tra bai cu matched, KHONG dem quota, KHONG tao row, khong call
 *    luan sau (ke ca khi quota DA CAN — do la tiet kiem goc cua boss 03/09);
 *  - KHAC/UNCLEAR → di tiep luot that, tinh luot;
 *  - judge hong (mang/ranh) → fail-open KHAC: di tiep;
 *  - khong co nguoi so sanh (draw khong co done question / question moi rong)
 *    → 0 goi judge (kiem qua tong call).
 *
 * lock_one_luan OFF nhu QuotaPerDrawTest (cung ly do): bat ON thi khong BAO GIO
 * tich duoc 2 topic tren 1 draw de do judge. Counting luu y: moi lan
 * runToDone VOI question = 1 call router + 1 luan; moi lan judge = 1 call co
 * max_tokens — ket noi bang prompt marker 'Hỏi lại' (chi JudgePrompt co).
 */
class ParaphraseJudgeTest extends Be2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'project.free_deep_preview' => true,
            'project.ai.lock_one_luan' => false,
        ]);
    }

    private function clearCooldown(Device $d): void
    {
        AiJob::query()->where('device_id', $d->device_id)->update(['requested_at' => now()->subMinutes(10)]);
    }

    /** POST #5 ke question + chay worker toi done (luot THAT, dem quota). */
    private function realRead(Device $d, int $drawId, string $topic, string $question): AiJob
    {
        $this->clearCooldown($d);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawId, 'topic' => $topic,
            'idempotency_key' => 'j-'.Str::random(16), 'question' => $question,
        ])->assertStatus(202)->json('data');

        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));
        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);

        return $job;
    }

    /** POST #5 chi xin (khong chay worker) — tra response. */
    private function ask(Device $d, int $drawId, string $topic, ?string $question)
    {
        $this->clearCooldown($d);
        $body = ['draw_id' => $drawId, 'topic' => $topic, 'idempotency_key' => 'j-'.Str::random(16)];
        if ($question !== null) {
            $body['question'] = $question;
        }

        return $this->cookieFor($d)->postJson('/api/ai/interpretations', $body);
    }

    /** Dem call judge da gui (co max_tokens + mang marker prompt JudgePrompt). */
    private function judgeCallCount(): int
    {
        $n = 0;
        Http::recorded(function (Request $req) use (&$n) {
            $body = json_decode((string) $req->body(), true) ?: [];
            $user = '';
            foreach ($body['messages'] ?? [] as $m) {
                if (($m['role'] ?? '') === 'user') {
                    $user = (string) $m['content'];
                }
            }
            if (array_key_exists('max_tokens', $body) && str_contains($user, 'Hỏi lại')) {
                $n++;
            }
        });

        return $n;
    }

    /**
     * (1) DU_GIONG: N=1, 1 luot that topic duyen → hoi topic khac cau NA NAN,
     * judge DU_GIONG → 200 job tai-su-dung (re-design theo seam Q2: row moi
     * from_cache=1, result = bai cu matched), quota KHONG dem (used van 1 →
     * remaining 0 tai N=1), 0 call luan sau them.
     */
    public function test_du_giong_tra_bai_cu_khong_dem_quota(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $old = $this->realRead($d, $draw->id, 'duyen', 'Bao giờ tôi đổi được việc?');

        $this->routerQueue[] = 'DU_GIONG';
        $res = $this->ask($d, $draw->id, 'tai_loc', 'Chuyển việc khi nào thuận lợi')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'done');
        $this->assertStringNotContainsString('quota', $res->getContent());

        $reuse = AiJob::query()->where('job_uuid', $res->json('data.job_uuid'))->firstOrFail();
        $this->assertTrue((bool) $reuse->from_cache, 'job tai-su-dung phai from_cache=1 (seam Q2 / migration 000012)');
        $this->assertSame($old->result, $reuse->result, 'tra DUNG bai cu matched');
        $this->assertSame('tai_loc', $reuse->topic, 'job mang topic khach hoi, ket qua bai cu');

        // quota dem 1 lan that duy nhat: row cache + judge call KHONG duoc tinh
        $this->cookieFor($d)->getJson('/api/me/today')->assertOk()->assertJsonPath('data.remaining_deep_reads', 0);
        // provider luan sau dung yen: chi router(1) + luan(1) + judge(1) = 3 call
        Http::assertSentCount(3);
    }

    /**
     * (2) TIET KIEM GOC (boss 03/09): quota DA CAN (N=1 dung het) → cau hoi na
     * nan DU_GIONG van duoc tra bai mien phi, KHONG bi 429 (judge chay TRUOC
     * quota gate — DIEM MO RONG Q2 chi dung cho do); cau KHAC van bi chan —
     * gate khong bi thong qua.
     */
    public function test_du_giong_van_tra_bai_khi_quota_can(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 1]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $old = $this->realRead($d, $draw->id, 'duyen', 'Bao giờ tôi đổi được việc?');

        // chung minh gate van con: cau hoi KHAC (judge → KHAC) bi 429
        $this->routerQueue[] = 'KHAC';
        $this->ask($d, $draw->id, 'tai_loc', 'Tháng này có nên mua xe không')
            ->assertStatus(429)->assertJsonPath('error.code', 'quota_exceeded');

        $this->routerQueue[] = 'DU_GIONG';
        $res = $this->ask($d, $draw->id, 'xuat_hanh', 'Chuyển việc khi nào thuận lợi')
            ->assertStatus(200)->assertJsonPath('data.status', 'done');
        $this->assertSame(
            $old->result,
            AiJob::query()->where('job_uuid', $res->json('data.job_uuid'))->value('result')
        );
    }

    /** (3) KHAC: di tiep — 202 job MOI queued, tinh luot (hang rao quota hoat dong). */
    public function test_khac_di_tiep_luot_that_dem_quota(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 2]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $old = $this->realRead($d, $draw->id, 'duyen', 'Bao giờ tôi đổi được việc?');

        $this->routerQueue[] = 'KHAC';
        $res = $this->ask($d, $draw->id, 'tai_loc', 'Tháng này có nên mua xe không')
            ->assertStatus(202)->json('data');
        $this->assertNotSame($old->job_uuid, $res['job_uuid'], 'KHAC = job moi, khong tra bai cu');
        $this->assertSame(2, AiJob::query()->where('draw_id', $draw->id)->count());
        $this->cookieFor($d)->getJson('/api/me/today')->assertOk()->assertJsonPath('data.remaining_deep_reads', 1);
        $this->assertSame(1, $this->judgeCallCount());
    }

    /** (4) UNCLEAR (mo ho → thien KHAC theo D4): cung hanh vi nhu KHAC — 202 that. */
    public function test_unclear_di_tiep_dem_quota(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 2]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->realRead($d, $draw->id, 'duyen', 'Bao giờ tôi đổi được việc?');

        $this->routerQueue[] = 'UNCLEAR';
        $this->ask($d, $draw->id, 'tai_loc', 'Chuyện tiền bạc')
            ->assertStatus(202);
        $this->assertSame(2, AiJob::query()->where('draw_id', $draw->id)->count());
    }

    /**
     * (5) judge HONG = fail-open KHAC (D4): timeout/500/output ranh → van 202
     * luot that, khong 5xx, khong fail im lang.
     */
    public function test_judge_hong_fail_open_di_tiep(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 3]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->realRead($d, $draw->id, 'duyen', 'Bao giờ tôi đổi được việc?');

        $cases = [
            'timeout' => new ConnectionException('timed out'),
            'ranh' => '',
            'rac free-text' => 'hình như DU_GIONG thì phải',
        ];
        foreach ($cases as $label => $content) {
            $this->clearCooldown($d);
            $this->routerQueue[] = $content;
            $this->ask($d, $draw->id, 'tai_loc', 'Chuyển việc khi nào '.$label)
                ->assertStatus(202, $label.' phai la hoi that (fail-open)');
        }
        $this->assertSame(4, AiJob::query()->where('draw_id', $draw->id)->count(), '1 that + 3 hoi that ke tiep');
    }

    /**
     * (6) KHONG co nguoi so sanh → 0 goi judge (0 dong token that):
     *  - draw khong co job done nao;
     *  - done chi co question NULL (khong co van ban de doi chieu).
     */
    public function test_khong_co_nguoi_so_sanh_khong_goi_judge(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 3]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();

        // draw moi tinh: hoi dau tien = 0 judge
        $draw = $this->drawFor($d);
        $this->ask($d, $draw->id, 'duyen', 'Bao giờ có người yêu')->assertStatus(202);
        $this->assertSame(0, $this->judgeCallCount());

        // question rong → judge khong co nghia gi, 0 goi
        $this->ask($d, $draw->id, 'tai_loc', null)->assertStatus(202);
        $this->assertSame(0, $this->judgeCallCount());
    }

    /** (7) source = bai done THAT gan nhat CUNG draw co question; row cache loai. */
    public function test_source_doi_chieu_la_done_that_cung_draw(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 5]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $old = $this->realRead($d, $draw->id, 'duyen', 'Viec cua toi the nao');

        // chen 1 row done tu cache (from_cache=1, question khac) — khong duoc lam nguoi
        $cache = AiJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'device_id' => $d->device_id,
            'draw_id' => $draw->id,
            'topic' => 'tai_loc',
            'question' => 'CAU_CACHE_KHONG_DUOC_DUNG',
            'status' => AiJob::ST_QUEUED,
            'requested_at' => now()->subMinutes(2),
            'result_key_hash' => hash('sha256', 'cache'),
        ]);
        $cache->forceFill(['status' => AiJob::ST_DONE, 'from_cache' => true, 'finished_at' => now()->addMinute(), 'result' => 'BAI_CACHE_KHONG_DUOC_TRA'])->save();

        $this->routerQueue[] = 'DU_GIONG';
        $res = $this->ask($d, $draw->id, 'xuat_hanh', 'Chuyen doi viec ra sao')
            ->assertStatus(200)->json('data');
        $reuse = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        $this->assertTrue((bool) $reuse->from_cache);
        $this->assertSame($old->result, $reuse->result, 'nguoi so sanh = bai THAT, khong phai row cache');
        // prompt judge phai chuyen dung van ban nguoi cu
        $seen = '';
        Http::recorded(function (Request $req) use (&$seen) {
            $body = json_decode((string) $req->body(), true) ?: [];
            foreach ($body['messages'] ?? [] as $m) {
                if (($m['role'] ?? '') === 'user' && str_contains((string) $m['content'], 'Hỏi lại')) {
                    $seen = (string) $m['content'];
                }
            }
        });
        $this->assertStringContainsString('Viec cua toi the nao', $seen);
        $this->assertStringContainsString('Chuyen doi viec ra sao', $seen);
        $this->assertStringNotContainsString('CAU_CACHE_KHONG_DUOC_DUNG', $seen);
    }

    /** (8) Cờ config paraphrase_judge=false → quay ve hanh vi Q2 (0 judge, moi hoi that). */
    public function test_cfg_judge_tat_quan_ve_hanh_vi_q2(): void
    {
        config([
            'project.ai.max_deep_reads_per_draw' => 2,
            'project.ai.paraphrase_judge' => false,
        ]);
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->realRead($d, $draw->id, 'duyen', 'Bao giờ tôi đổi được việc?');

        // DU_GIONG dat trong hang doi neu vo tinh bi goi = 202 chung la KHONG goi
        $this->routerQueue[] = 'DU_GIONG';
        $this->ask($d, $draw->id, 'tai_loc', 'Chuyển việc khi nào thuận lợi')->assertStatus(202);
        $this->assertSame(0, $this->judgeCallCount());
    }
}
