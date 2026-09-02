<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\AiBoxClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * REVIEW-LUAN (card t_8aa93a01, BOSS-GO 02/09 mục 1) — KHÓA 1 lượt luận per
 * (hexagram_id, topic): nguồn khóa = MỌI job status=done cùng (quẻ, chủ đề),
 * BẤT KỂ question (rộng hơn le trich nguu cache AC-2 cu — le cu van dung cho
 * cache, khong dung lam khoa). Duong 200-done-gia-man cua AC-2 bi thay bang
 * 409 AI_ALREADY_DONE; doc lai bai qua #5b GET /api/ai/interpretations/saved.
 *
 * 6 case bat buoc cua card + PII F7: saved KHONG bao gio lot field question.
 */
class AlreadyDoneLockTest extends Be2TestCase
{
    /** POST #5 + chạy worker inline nếu còn queued (đúng pattern AiCacheTest). */
    private function interpret(Device $d, int $drawId, string $topic, ?string $question = null, int $expected = 202): array
    {
        $body = ['draw_id' => $drawId, 'topic' => $topic, 'idempotency_key' => 'lk-'.Str::random(16)];
        if ($question !== null) {
            $body['question'] = $question;
        }
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', $body)
            ->assertStatus($expected)->json('data');

        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        if ($job->status === AiJob::ST_QUEUED) {
            (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));
            $job->refresh();
        }

        return ['job' => $job, 'http' => $res];
    }

    private function clearCooldown(Device $d): void
    {
        AiJob::query()->where('device_id', $d->device_id)->update(['requested_at' => now()->subMinutes(10)]);
    }

    /** Case 1 — lượt đầu, chưa có bài done: 202 bình thường, khóa im lặng. */
    public function test_luot_dau_chua_done_van_202(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $r = $this->interpret($d, $this->drawFor($d, 11)->id, 'duyen');
        $this->assertSame(AiJob::ST_DONE, $r['job']->status);
        Http::assertSentCount(1);
    }

    /**
     * Case 2 — sau done, POST lại CÓ question (device khác, key khác): 409
     * AI_ALREADY_DONE, provider không gọi thêm, DB không rác job mới.
     * (Nguồn khóa là job done của device A — khoa theo (hexagram,topic), không theo device/draw.)
     */
    public function test_post_lai_co_question_sau_done_ben_409(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen'); // done → call #1

        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11);
        $before = AiJob::count();
        $this->cookieFor($b)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawB->id, 'topic' => 'duyen',
            'idempotency_key' => 'lk-'.Str::random(16), 'question' => 'đổi việc được không',
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');

        $this->assertSame($before, AiJob::count(), '409 phải TRƯỚC INSERT — không tạo rac job');
        Http::assertSentCount(1, 'khóa phải chặn trước mọi dispatch — provider không thêm call');
    }

    /**
     * Case 3 — POST lại KHÔNG question: cũng 409. Đường 200-done-giả-man
     * (AC-2 copy result tại chỗ) không còn tồn tại → không row mới.
     */
    public function test_post_lai_khong_question_cung_409_khong_con_duong_200_gia_man(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen');

        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $before = AiJob::count();
        $this->cookieFor($b)->postJson('/api/ai/interpretations', [
            'draw_id' => $this->drawFor($b, 11)->id, 'topic' => 'duyen', 'idempotency_key' => 'lk-'.Str::random(16),
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');
        $this->assertSame($before, AiJob::count());
        Http::assertSentCount(1);
    }

    /**
     * Case 4 — BẤT BIẾN: job FAILED (AI_FILTERED) KHÔNG phải nguồn khóa.
     * Cùng (hexagram,topic) vẫn được hỏi lại → 202, provider call.
     *
     * FIX-LUAN-SAU 02/09 (t_20f28886, vào main aacc07e): 1 lượt bẩn giờ được
     * TỰ regenerate — RunAiBoxJob gọi provider lần 2, queue fake rỗng thì
     * harness fallback bài sạch → regen thành công → job DONE (test cũ giả
     * định "1 call/job" nên đỏ ở dòng assert failed). Phải fake bẩn CẢ HAI
     * lượt mới chạm kịch bản "bất biến thất bại" = failed AI_FILTERED.
     */
    public function test_job_failed_khong_khoa_duong(): void
    {
        $this->fakeAi('Thỉnh bùa ngay hôm nay để đổi vận tuyệt đối.'); // lượt 1: phạm wordguard
        $this->fakeAi('Vẫn bẩn: bùa đổi vận.');                        // lượt regen: cạn ngân sách → AI_FILTERED
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $draw = $this->drawFor($a, 11);
        $bad = $this->interpret($a, $draw->id, 'duyen');
        $this->assertSame(AiJob::ST_FAILED, $bad['job']->status);

        $this->clearCooldown($a);
        $this->fakeAi($this->cleanMd);
        $again = $this->interpret($a, $draw->id, 'duyen'); // 202 như trước
        $this->assertSame(AiJob::ST_DONE, $again['job']->status);
        Http::assertSentCount(3); // 1 bẩn + 1 regen-bẩn (failed) + 1 sạch (done)
    }

    /**
     * Case 5 — hết cooldown mà vẫn có done → 409 (cooldown hết không thành
     * đường lách); VÀ trong cooldown + có done → cũng 409 chứ không 429
     * (gate DONE đứng TRƯỚC cooldown đúng thứ tự card chốt).
     */
    public function test_done_thang_cooldown_khong_duoc_lach(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $draw = $this->drawFor($a, 11);
        $this->interpret($a, $draw->id, 'duyen'); // done, requested_at = now (đang cooldown)

        // trong cooldown: POST key mới → 409 AI_ALREADY_DONE (không phải 429)
        $this->cookieFor($a)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'lk-'.Str::random(16),
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');

        // hết cooldown: vẫn 409
        $this->clearCooldown($a);
        $this->cookieFor($a)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'lk-'.Str::random(16),
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');
        Http::assertSentCount(1);
    }

    /** topic khác cùng quẻ KHÔNG bị khóa (khóa đúng粒度 (hexagram,topic)). */
    public function test_khoa_khong_an_xuyen_topic(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->payUnlock($a, 'tai_loc');
        $draw = $this->drawFor($a, 11);
        $this->interpret($a, $draw->id, 'duyen');
        $this->clearCooldown($a);
        $r = $this->interpret($a, $draw->id, 'tai_loc'); // 202 — topic chưa luận
        $this->assertSame(AiJob::ST_DONE, $r['job']->status);
    }

    /** replay same key + same body SAU khi job done: F6 bất biến — vẫn 200 same job (không 409). */
    public function test_idempotency_replay_giu_duoc_200(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock(d: $d, topic: 'duyen');
        $draw = $this->drawFor($d, 11);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'lk-replay01',
        ])->assertStatus(202)->json('data');
        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class)); // done rồi

        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'lk-replay01',
        ])->assertStatus(200)->assertJsonPath('data.job_uuid', $res['job_uuid']);
        $this->assertSame(1, AiJob::count());
    }

    // ───────────────────────── #5b GET /api/ai/interpretations/saved ─────────

    /**
     * Case 6a — device ĐÃ unlock + có nguồn done: 200 exists:true + đủ
     * {job_uuid, result, completed_at}; KHÔNG bao giờ lộ question (F7/PII).
     */
    public function test_saved_unlocked_tra_bai_khong_lot_question(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $draw = $this->drawFor($a, 11);
        $r = $this->interpret($a, $draw->id, 'duyen', 'mật khẩu wifi nhà tôi là abc123');

        $resp = $this->cookieFor($a)->getJson('/api/ai/interpretations/saved?draw_id='.$draw->id.'&topic=duyen')
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.job_uuid', $r['job']->job_uuid)
            ->assertJsonPath('data.result', $this->cleanMd);
        $completedAt = $resp->json('data.completed_at');
        $this->assertIsString($completedAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $completedAt);
        $json = json_encode($resp->json());
        $this->assertStringNotContainsString('question', $json, 'saved CAM tra field question');
        $this->assertStringNotContainsString('abc123', $json, 'PII question cam ro ri');
    }

    /**
     * Case 6b — draw KHÁC nhưng cùng hexagram (khóa theo quẻ, không theo draw):
     * saved vẫn trả bài done của (hexagram, topic) → job_uuid nguồn.
     */
    public function test_saved_quy_draw_ve_hexagram(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $first = $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen');

        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11); // draw id khác, hexagram 11 giống
        $this->cookieFor($b)->getJson('/api/ai/interpretations/saved?draw_id='.$drawB->id.'&topic=duyen')
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.job_uuid', $first['job']->job_uuid);
    }

    /** Case 6c — CHƯA unlock topic → 402 UNLOCK_REQUIRED như #5 (cấm chia bài khi mở khóa). */
    public function test_saved_chua_unlock_402(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen');

        $b = $this->device(); // chưa paid duyen
        $drawB = $this->drawFor($b, 11);
        $this->cookieFor($b)->getJson('/api/ai/interpretations/saved?draw_id='.$drawB->id.'&topic=duyen')
            ->assertStatus(402)->assertJsonPath('error.code', 'UNLOCK_REQUIRED')
            ->assertJsonPath('error.details.topic', 'duyen');
    }

    /** Case 6d — chưa có bài done → 200 exists:false (nulls), không 404. */
    public function test_saved_khong_co_done_exists_false(): void
    {
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d, 11);
        $this->cookieFor($d)->getJson('/api/ai/interpretations/saved?draw_id='.$draw->id.'&topic=duyen')
            ->assertOk()
            ->assertJsonPath('data.exists', false);
    }

    /** #5b validate: topic lạ → 422; draw của device khác → 404 (ẩn tồn tại F7); thiếu key → 422. */
    public function test_saved_422_va_404_an_ton_tai(): void
    {
        $a = $this->device();
        $drawA = $this->drawFor($a, 11);
        $b = $this->device();
        $this->payUnlock($b, 'duyen');

        $this->cookieFor($b)->getJson('/api/ai/interpretations/saved?draw_id='.$drawA->id.'&topic=duyen')
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->cookieFor($b)->getJson('/api/ai/interpretations/saved?draw_id=1&topic=phat_dat')
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->cookieFor($b)->getJson('/api/ai/interpretations/saved?topic=duyen')
            ->assertStatus(422);
    }
}
