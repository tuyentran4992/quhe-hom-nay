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
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen',
            'idempotency_key' => 'q-'.Str::random(16), 'question' => str_repeat('a', 201),
        ])->assertStatus(422)->assertJsonPath('error.details.errors.question.0', 'Câu hỏi tối đa 200 ký tự.');

        // đúng 200 ký tự → hợp lệ
        $this->clearCooldown($d);
        $r200 = $this->interpret($d, $draw->id, 'duyen', str_repeat('ạ', 200));
        $this->assertSame(200, mb_strlen($r200['job']->question), 'đếm unicode mb_strlen, không phải byte');

        // " abc " vs "abc" → CÙNG body (same key, không 409, same job)
        $this->clearCooldown($d);
        $a = $this->interpret($d, $draw->id, 'duyen', ' abc ', key: 'q-samehash');
        $b = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'q-samehash', 'question' => 'abc',
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

    /** T11 — nguồn cache: job done CÓ question bị whereNull loại → device B question NULL vẫn MISS. */
    public function test_cache_chi_an_job_question_null(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $drawA = $this->drawFor($a, 11);
        $first = $this->interpret($a, $drawA->id, 'duyen', 'có vướng mắc riêng');
        $this->assertSame('done', $first['job']->status);
        $this->assertSame('có vướng mắc riêng', $first['job']->question);

        // device B question NULL, cùng hexagram 11 + topic → KHÔNG được ăn bài có question
        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11);
        $second = $this->interpret($b, $drawB->id, 'duyen', null, 202); // MISS → queued
        $this->assertSame('queued', $second['http']['status']);

        // LUAN-V3 §5.3: job A có question → thêm 1 call router (2 call); job B
        // question NULL + cache MISS → KHÔNG router, đúng 1 call luận → tổng 3.
        Http::assertSentCount(3, 'nguồn question!=null phải bị loại → provider call luận lần 2 (+1 router job A)');
    }

    /** T12 — job mới CÓ question: LUÔN MISS bất kể có job NULL-done trước đó. */
    public function test_job_co_question_khong_bao_gio_an_cache(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen', null); // NULL-done = nguồn cache hợp lệ

        // device B hỏi câu hỏi → không ăn cache, 202 queued, provider call #2
        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $r = $this->interpret($b, $this->drawFor($b, 11)->id, 'duyen', 'x', 202);
        $this->assertSame('queued', $r['http']['status']);
        $this->assertSame('x', $r['job']->refresh()->question);
        // LUAN-V3 §5.3: A question NULL → 1 call luận; B có question → router + luận = 2.
        Http::assertSentCount(3);
    }

    /** T13 — regression AC-2 cũ: cả hai phía question NULL → vẫn HIT, 1 call. */
    public function test_cache_traditional_ac2_van_xanh_question_null(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->interpret($a, $this->drawFor($a, 11)->id, 'duyen', null);

        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $cached = $this->interpret($b, $this->drawFor($b, 11)->id, 'duyen', null, 200);
        $this->assertSame('done', $cached['job']->status);
        $this->assertNull($cached['job']->question);
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
