<?php

namespace Tests\Feature\Api;

use App\Models\AiJob;
use Illuminate\Support\Str;

/**
 * 05-testplan F4 (402 gate) + F5 (cooldown project.php) + F6 (idempotency #5) +
 * F7 (ẩn tồn tại) trên /api/ai/interpretations + /api/ai/jobs.
 */
class InterpretationGateTest extends Be2TestCase
{
    /** F4 — #5 khi chưa paid: 402 ĐÚNG payload mẫu §5 (price_vnd 29000). */
    public function test_f4_unlock_required_payload(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);

        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'key-f4-0001',
        ]);

        $res->assertStatus(402)->assertJson([
            'error' => [
                'code' => 'UNLOCK_REQUIRED',
                'message' => 'Chủ đề này cần mở khóa 29.000đ.',
                'details' => ['topic' => 'duyen', 'price_vnd' => 29000, 'payment_create_url' => '/api/payments/create'],
            ],
        ]);
        $this->assertSame(0, AiJob::count(), '402 phải không tạo job');
    }

    /** F5 — 202/200 rồi 429 AI_COOLDOWN retry_after ≤90; requested_at cũ 91s → thông. */
    public function test_f5_cooldown_ninety_seconds(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);

        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'key-f5-0001',
        ])->assertStatus(202);

        $second = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'key-f5-0002',
        ]);
        $second->assertStatus(429)->assertJsonPath('error.code', 'AI_COOLDOWN');
        $retry = $second->json('error.details.retry_after_seconds');
        $this->assertIsInt($retry);
        $this->assertGreaterThanOrEqual(1, $retry);
        $this->assertLessThanOrEqual((int) config('project.ai.cooldown_seconds'), $retry);

        // mock requested_at cũ 91 giây → hết cooldown, được thông qua
        AiJob::query()->where('device_id', $d->device_id)->update(['requested_at' => now()->subSeconds(91)]);
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'key-f5-0003',
        ])->assertStatus(202);
    }

    /** F6 — #5 same key same body = CÙNG job_uuid 200, không row mới; đổi body → 409. */
    public function test_f6_idempotency_same_key(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);

        $body = ['draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'key-f6-0001'];
        $a = $this->cookieFor($d)->postJson('/api/ai/interpretations', $body);
        $jobsAfterFirst = AiJob::count();
        $b = $this->cookieFor($d)->postJson('/api/ai/interpretations', $body);

        $b->assertStatus(200)->assertJsonPath('data.job_uuid', $a->json('data.job_uuid'));
        $this->assertSame($jobsAfterFirst, AiJob::count(), 'replay không được nhân row');

        // same key, khác body (topic khác) → 409 IDEMPOTENCY_CONFLICT
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'tai_loc', 'idempotency_key' => 'key-f6-0001',
        ])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    /** F7 — device B poll job của A → 404 NOT_FOUND (không 403). #9 đơn của A cũng 404. */
    public function test_f7_poll_other_device_hidden(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $uuid = $this->cookieFor($a)->postJson('/api/ai/interpretations', [
            'draw_id' => $this->drawFor($a)->id, 'topic' => 'duyen', 'idempotency_key' => 'key-f7-0001',
        ])->json('data.job_uuid');

        $b = $this->device();
        $this->cookieFor($b)->getJson('/api/ai/jobs/'.$uuid)
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        // của chính nó → 200, shape §6 đủ 7 field
        $this->cookieFor($a)->getJson('/api/ai/jobs/'.$uuid)
            ->assertStatus(200)
            ->assertJsonPath('data.job_uuid', $uuid)
            ->assertJsonStructure(['data' => [
                'job_uuid', 'status', 'topic', 'result', 'error_code', 'requested_at', 'finished_at',
            ]]);
    }

    /** C-06 cap 90 job/60 phút TOÀN CỤC → 429 AI_GLOBAL_CAP.
     * REVIEW-LUAN (t_8aa93a01): gate done-409 đứng TRƯỚC cap theo thứ tự chốt,
     * nên job bơm nền phải khác topic (tai_loc) với request POST (duyen) — cap
     * đếm mọi topic, còn khóa done chỉ chặn đúng (hexagram, topic). */
    public function test_global_cap_90_jobs_per_hour(): void
    {
        $this->fakeAi($this->cleanMd);
        $d = $this->device();
        $draw = $this->drawFor($d);
        // bơm 90 job done requested_at cách đây 2 phút — device lạ, không dính cooldown F5
        for ($i = 0; $i < (int) config('project.ai.global_cap_per_hour'); $i++) {
            AiJob::query()->create([
                'job_uuid' => (string) Str::uuid(),
                'device_id' => $d->device_id,
                'draw_id' => $draw->id,
                'topic' => 'tai_loc',
                'status' => AiJob::ST_DONE,
                'attempts' => 1,
                'result' => 'x',
                'requested_at' => now()->subMinutes(2),
                'finished_at' => now()->subMinutes(2),
            ]);
        }
        $this->payUnlock($d, 'duyen');
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'key-cap-0001',
        ])->assertStatus(429)->assertJsonPath('error.code', 'AI_GLOBAL_CAP');
    }

    /** Validate #5: thiếu field / topic lạ → 422 VALIDATION_FAILED details.errors. */
    public function test_validation_422_shapes(): void
    {
        $d = $this->device();
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['errors']]]);
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => 1, 'topic' => 'phat_dat', 'idempotency_key' => 'key-vl-000001',
        ])->assertStatus(422)->assertJsonPath('error.details.errors.topic.0', 'Chủ đề không tồn tại (C-02).');
    }
}
