<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\AiBoxClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LUAN-V2 (card t_c86f3954, SPEC-LUAN-V2 §4/§5/§9 T9–T13) — trường `question`
 * end-to-end + cache AC-2 siết: job CÓ question không ăn cache và không làm
 * nguồn cache (D1). Hash = sha256(draw|topic|question) — đổi hash làm
 * same-key-different-question thành 409 có chủ đích.
 */
class QuestionCacheTest extends Be2TestCase
{
    /** POST #5 (tuỳ chọn question) + chạy worker nếu còn queued. */
    private function interpret(Device $d, int $drawId, string $topic, ?string $question, int $expected = 202, ?string $key = null): array
    {
        $body = ['draw_id' => $drawId, 'topic' => $topic, 'idempotency_key' => $key ?? 'q-'.Str::random(16)];
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

    /** T9 — normalize: whitespace-only → NULL DB; >200 sau trim → 422; " abc "/"abc" cùng hash. */
    public function test_question_trim_200_va_rong_hoa_null(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);

        // "   " → question NULL trong DB
        $r = $this->interpret($d, $draw->id, 'duyen', '   ');
        $this->assertNull($r['job']->question, 'whitespace-only phải normalize thành NULL');

        // 201 ký tự (sau trim) → 422 đúng message SPEC §4.1
        // (REVIEW-LUAN t_8aa93a01: validate hình dạng đứng TRƯỚC khóa done → vẫn 422
        // dù hex 11+duyen đã done ở bước trên — bat biến "409 không được che 422".)
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen',
            'idempotency_key' => 'q-'.Str::random(16), 'question' => str_repeat('a', 201),
        ])->assertStatus(422)->assertJsonPath('error.details.errors.question.0', 'Câu hỏi tối đa 200 ký tự.');

        // đúng 200 ký tự → hợp lệ. REVIEW-LUAN: hex 11+duyen đã done → khóa 409 đứng
        // trước INSERT, nên bước "job hợp lệ" phải dùng quẻ khác (12). uq device+date
        // chặn 2 draw/1 device/1 ngày → device mới B (chưa cooldown).
        $b1 = $this->device();
        $this->payUnlock($b1, 'duyen');
        $r200 = $this->interpret($b1, $this->drawFor($b1, 12)->id, 'duyen', str_repeat('ạ', 200));
        $this->assertSame(200, mb_strlen($r200['job']->question), 'đếm unicode mb_strlen, không phải byte');

        // " abc " vs "abc" → CÙNG body (same key, không 409, same job) — device C,
        // quẻ 13 (chưa done) để idempotency-gate là gate duy nhất được chạm tới.
        $c1 = $this->device();
        $this->payUnlock($c1, 'duyen');
        $draw3 = $this->drawFor($c1, 13);
        $a = $this->interpret($c1, $draw3->id, 'duyen', ' abc ', key: 'q-samehash');
        $b = $this->cookieFor($c1)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw3->id, 'topic' => 'duyen', 'idempotency_key' => 'q-samehash', 'question' => 'abc',
        ])->assertStatus(200)->json('data');
        $this->assertSame($a['job']->job_uuid, $b['job_uuid'], 'hash tính trên giá trị đã trim');
        $this->assertSame('abc', AiJob::query()->where('job_uuid', $a['job']->job_uuid)->value('question'));
    }

    /** T10 — D1: thêm question vào body cũ với cùng key → hash lệch → 409 có chủ đích. */
    public function test_hash_idempotency_co_question_409(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);

        $this->interpret($d, $draw->id, 'duyen', null, key: 'q-conflict');
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'q-conflict',
            'question' => 'bao giờ có người yêu',
        ])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    /**
     * T11 → REVIEW-LUAN (t_8aa93a01): nguồn KHÓA rộng hơn nguồn cache cũ — job done
     * CÓ question vẫn chặn (hexagram, topic). Device B hỏi question NULL cùng quẻ 11
     * → trước đây MISS/202, nay 409 AI_ALREADY_DONE, không job mới, không call thêm.
     */
    public function test_khoa_dat_ca_job_co_question(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $drawA = $this->drawFor($a, 11);
        $first = $this->interpret($a, $drawA->id, 'duyen', 'có vướng mắc riêng');
        $this->assertSame('done', $first['job']->status);
        $this->assertSame('có vướng mắc riêng', $first['job']->question);

        // device B, cùng hexagram 11 + topic → KHÓA bất kể question
        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11);
        $this->cookieFor($b)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawB->id, 'topic' => 'duyen', 'idempotency_key' => 'q-'.Str::random(16),
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');

        // LUAN-V3 §5.3: job A có question → router + luận = 2 call; B bị khóa → 0 call.
        $this->assertSame(1, AiJob::query()->count(), '409 không tạo rac job');
        Http::assertSentCount(2, 'nguồn khóa có question vẫn chặn → B không được call lần 3');
    }

    /**
     * T12 → REVIEW-LUAN: đường lách "thêm question để né khóa" bị đóng —
     * hex 11+duyen đã done (question NULL) → request CÓ question vẫn 409.
     */
    public function test_question_khong_phai_duong_lach_qua_khoa(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen', null); // NULL-done

        // device B hỏi câu hỏi → trước: MISS 202; nay: 409 vì khóa theo (hexagram, topic)
        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $this->cookieFor($b)->postJson('/api/ai/interpretations', [
            'draw_id' => $this->drawFor($b, 11)->id, 'topic' => 'duyen',
            'idempotency_key' => 'q-'.Str::random(16), 'question' => 'x',
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');
        // A question NULL → đúng 1 call luận; B khóa → 0.
        Http::assertSentCount(1);
    }

    /**
     * T13 → REVIEW-LUAN: cặp question NULL/NULL (regression AC-2 cũ) nay là 409
     * khóa cứng + đọc lại nguyên văn qua #5b saved — bài của A, không call lần 2.
     */
    public function test_ca_hai_null_bi_khoa_va_doc_lai_qua_saved(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen', null);

        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11);
        $this->cookieFor($b)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawB->id, 'topic' => 'duyen', 'idempotency_key' => 'q-'.Str::random(16),
        ])->assertStatus(409)->assertJsonPath('error.code', 'AI_ALREADY_DONE');

        // #5b: B đọc lại đúng bài A, không lọt question (ở đây question NULL)
        $this->cookieFor($b)->getJson('/api/ai/interpretations/saved?draw_id='.$drawB->id.'&topic=duyen')
            ->assertOk()->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.result', $this->cleanMd);
        Http::assertSentCount(1);
    }

    /** question KHÔNG lọt ra payload #6 (ẩn với device khác, cùng nguyên tắc F7). */
    public function test_question_khong_lo_ra_api_payload(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $r = $this->interpret($d, $this->drawFor($d)->id, 'duyen', 'bí mật riêng tư');
        $poll = $this->cookieFor($d)->getJson('/api/ai/jobs/'.$r['job']->job_uuid)->assertOk();
        $this->assertStringNotContainsString('question', json_encode($poll->json()));
        $this->assertStringNotContainsString('bí mật riêng tư', json_encode($poll->json()));
    }
}
