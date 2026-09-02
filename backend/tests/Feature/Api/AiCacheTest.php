<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\AiBoxClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * AC-2 (05-testplan): cache theo QUẺ + CHỦ ĐỀ — job sau cùng hexagram + cùng topic
 * ăn luôn bài done trước đó, KHÔNG dispatch → provider chỉ đúng 1 lần call.
 * Đếm bằng Http::assertSentCount — proof cứng, không phải log.
 */
class AiCacheTest extends Be2TestCase
{
    /** Create request #5 + chạy worker nếu job còn queued (queue database → pending). */
    private function interpret(Device $d, int $drawId, string $topic, int $expected = 202): array
    {
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawId, 'topic' => $topic, 'idempotency_key' => 'ac2-'.Str::random(16),
        ])->assertStatus($expected)->json('data');

        $job = AiJob::query()->where('job_uuid', $res['job_uuid'])->firstOrFail();
        if ($job->status === AiJob::ST_QUEUED) {
            (new RunAiBoxJob($job->id))->handle(app(AiBoxClient::class));
            $job->refresh();
        }

        return ['job' => $job, 'http' => $res];
    }

    /** Đẩy requested_at về quá khứ để device qua cooldown C-03 giữa các bước test. */
    private function clearCooldown(Device $d): void
    {
        AiJob::query()->where('device_id', $d->device_id)->update(['requested_at' => now()->subMinutes(10)]);
    }

    public function test_ac2_cung_que_cung_topic_chi_goi_provider_dung_mot_lan(): void
    {
        $this->fakeAi($this->cleanMd);

        // device A trả tiền duyen, gieo quẻ 11, luận → provider call #1
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $drawA = $this->drawFor($a, 11);
        $first = $this->interpret($a, $drawA->id, 'duyen');
        $this->assertSame(AiJob::ST_DONE, $first['job']->status);

        // device B KHÁC, quẻ KHÁC id nhưng CÙNG hexagram 11 + CÙNG topic → cache hit
        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11); // hexagram_id giống, draw id khác
        $second = $this->interpret($b, $drawB->id, 'duyen', 200); // cache → done tại chỗ

        $this->assertSame(AiJob::ST_DONE, $second['job']->status, 'job cache phải done ngay');
        $this->assertSame($first['job']->result, $second['job']->result);
        $this->assertNotSame($first['job']->job_uuid, $second['job']->job_uuid, 'cache tạo job riêng cho device');
        Http::assertSentCount(1); // ← AC-2: hai luận sâu, MỘT lần gọi AI-Box

        // #6 device B poll job cache → 200 status done, result đầy đủ
        $this->cookieFor($b)->getJson('/api/ai/jobs/'.$second['job']->job_uuid)
            ->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.result', $this->cleanMd);
    }

    /**
     * AC-2: topic khác cùng quẻ = bài luận khác (prompt khác) → provider gọi lần 2.
     * Cooldown 90s device chốt ở F5 — bước 2 của CÙNG device phải đẩy requested_at
     * quá khứ thì mới qua được gate (làm rõ hai chuyện độc lập, không lẫn vào cache).
     */
    public function test_cache_khong_an_xuyen_topic(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $this->payUnlock($a, 'tai_loc');
        $draw = $this->drawFor($a, 11);

        $this->interpret($a, $draw->id, 'duyen');
        $this->clearCooldown($a);
        $other = $this->interpret($a, $draw->id, 'tai_loc');

        // topic khác cùng quẻ = bài luận khác → provider phải gọi lần 2
        Http::assertSentCount(2);
        $this->assertSame('tai_loc', $other['job']->topic);
    }

    public function test_job_failed_khong_bi_dung_lam_cache(): void
    {
        // bài bẩn AI_FILTERED → failed; job sau vẫn phải gọi provider
        // (FIX-LUAN-SAU 02/09: fake 2 lượt bẩn vì 1 lượt giờ được regenerate)
        $this->fakeAi('Thỉnh bùa ngay hôm nay để đổi vận tuyệt đối.');
        $this->fakeAi('Vẫn bẩn: bùa đổi vận.');
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $draw = $this->drawFor($a, 11);
        $bad = $this->interpret($a, $draw->id, 'duyen');
        $this->assertSame(AiJob::ST_FAILED, $bad['job']->status);

        $this->fakeAi($this->cleanMd);
        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11);
        $good = $this->interpret($b, $drawB->id, 'duyen');

        $this->assertSame(AiJob::ST_DONE, $good['job']->status);
        $this->assertNotSame($bad['job']->result, $good['job']->result);
        Http::assertSentCount(3); // failed không thế chỗ done (2 lượt bẩn có regen + 1 lượt sạch)
    }

    /**
     * AC-2 + cooldown: provider chỉ 1 lần cho 2 device (cache); job cache device B
     * trả 200 done ngay tại #5. B dùng requested_at quá khứ để chứng minh cache
     * không cần chờ worker — không nhầm với cooldown của A (device khác nhau).
     */
    public function test_cache_job_done_taicho_200_khong_can_worker(): void
    {
        $this->fakeAi($this->cleanMd);
        $a = $this->device();
        $this->payUnlock($a, 'duyen');
        $drawA = $this->drawFor($a, 11);
        $this->interpret($a, $drawA->id, 'duyen');

        $b = $this->device();
        $this->payUnlock($b, 'duyen');
        $drawB = $this->drawFor($b, 11);
        // requested_at tương lai xa? không — để null: device B chưa có job nào → cooldown im lặng
        $this->cookieFor($b)->postJson('/api/ai/interpretations', [
            'draw_id' => $drawB->id, 'topic' => 'duyen', 'idempotency_key' => 'ac2-cd-'.Str::random(12),
        ])->assertStatus(200) // 200 = done tại chỗ, không 202 chờ worker
            ->assertJsonPath('data.status', 'done');
        Http::assertSentCount(1);
    }
}
