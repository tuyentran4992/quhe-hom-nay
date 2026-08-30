<?php

namespace App\Services;

use App\Domain\Calendar;
use App\Domain\Rules;
use App\Domain\Topic;
use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * BE-2 — 03-api §5/#6: gate 402 → cooldown C-03 → cap C-06 → INSERT ai_jobs + dispatch.
 * 1 trách nhiệm: ĐIỀU PHỐI job luận sâu. KHÔNG gọi AI-Box ở đây (chỉ RunAiBoxJob — 01 §2),
 * không đụng tiền (PaymentService).
 *
 * Cache AC-2: trước khi dispatch, tra job `done` mới nhất CÓ CÙNG QUẺ (hexagram_id) +
 * CÙNG CHỦ ĐỀ; khớp → job mới sinh ra đã done, sao chép result, KHÔNG dispatch → worker
 * không gọi provider (chứng minh bằng đếm log `aibox.request.sent`).
 */
class InterpretationService
{
    /**
     * Quyết định của #5 — trả về ['conflict' => Response] controller sẽ return,
     * hoặc ['job' => AiJob, 'created' => bool]. Validation/402/429 ném DomainException.
     *
     * @param  array{draw_id?:mixed,topic?:mixed,idempotency_key?:mixed}  $body
     */
    public function request(Device $device, array $body): AiJob
    {
        // (a) validate — 422 do FormRequest ở controller; đây là invariant tầng service.
        $topic = Topic::tryFrom((string) ($body['topic'] ?? ''));
        if ($topic === null) {
            throw InterpretationException::validation(['topic' => ['Chủ đề không tồn tại (C-02).']]);
        }
        $draw = $device->draws()->whereKey((int) $body['draw_id'])->first();
        if ($draw === null) {
            // 404 NOT_FOUND: không phải draw của device này (03-api §5 rule draw_id).
            throw InterpretationException::notFound('draw_id không hợp lệ với thiết bị này.');
        }
        $allowed = [Calendar::todayVn(), Calendar::yesterdayVn()];
        if (! in_array($draw->drawn_date->format('Y-m-d'), $allowed, true)) {
            throw InterpretationException::validation([
                'draw_id' => ['Chỉ nhận luận sâu cho quẻ hôm nay hoặc hôm qua.'],
            ]);
        }

        // idempotency TRƯỚC mọi gate (F6: same key same body = same job, không nhân row):
        $hash = hash('sha256', $draw->id.'|'.$topic->value);
        if ($key = trim((string) ($body['idempotency_key'] ?? ''))) {
            $existing = AiJob::query()->where('device_id', $device->device_id)
                ->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ($existing->result_key_hash !== $hash) {
                    throw InterpretationException::conflict();
                }

                return $existing; // same body → same job
            }
        }

        // (b) entitlement — 03-api §5: payments paid cho topic (02-db §6).
        $paid = Payment::query()
            ->where('device_id', $device->device_id)
            ->where('kind', 'unlock')->where('topic', $topic->value)
            ->where('status', Payment::ST_PAID)->exists();
        if (! $paid) {
            throw InterpretationException::unlockRequired($topic->value);
        }

        // (c) cooldown C-03 = 90 GIÂY/device theo ai_jobs.requested_at mới nhất.
        $last = AiJob::query()->where('device_id', $device->device_id)
            ->max('requested_at');
        if ($last !== null) {
            $elapsed = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last), true);
            if ($elapsed < Rules::AI_COOLDOWN_SECONDS) {
                throw InterpretationException::cooldown(
                    (int) ceil(Rules::AI_COOLDOWN_SECONDS - $elapsed)
                );
            }
        }

        // (d) cap TOÀN CỤC C-06: job TẠO MỚI trong 60 phút gần nhất ≤ 90.
        $recent = AiJob::query()->where('requested_at', '>=', now()->subHour())->count();
        if ($recent >= Rules::AI_GLOBAL_CAP_PER_HOUR) {
            throw InterpretationException::globalCap();
        }

        // (e) INSERT + cache tra trước khi dispatch.
        $job = AiJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'device_id' => $device->device_id,
            'draw_id' => $draw->id,
            'topic' => $topic->value,
            'status' => AiJob::ST_QUEUED,
            'requested_at' => now(),
            'idempotency_key' => $key ?: null,
            'result_key_hash' => $hash,
        ]);

        $cached = $this->findCacheHit($draw->hexagram_id, $topic->value, $job->id);
        if ($cached !== null) {
            // AC-2 cache: SAME QUẺ + SAME CHỦ ĐỀ → done ngay, không đụng provider.
            $job->forceFill([
                'status' => AiJob::ST_DONE,
                'result' => $cached->result,
                'finished_at' => now(),
                'attempts' => 0,
            ])->save();

            return $job;
        }

        RunAiBoxJob::dispatch($job->id);

        return $job;
    }

    /** Job done gần nhất cùng hexagram+topic KHÁC chính nó — nguồn cache DB (AC-2). */
    public function findCacheHit(int $hexagramId, string $topic, int $excludeJobId): ?AiJob
    {
        return AiJob::query()
            ->where('status', AiJob::ST_DONE)
            ->where('topic', $topic)
            ->where('id', '!=', $excludeJobId)
            ->whereIn('draw_id', function ($q) use ($hexagramId) {
                $q->select('id')->from('draws')->where('hexagram_id', $hexagramId);
            })
            ->latest('finished_at')
            ->first();
    }

    /** 03-api #6 — poll theo uuid CHỈ của device (uuid lạ = 404, ẩn tồn tại F7). */
    public function poll(Device $device, string $jobUuid): ?AiJob
    {
        return AiJob::query()
            ->where('job_uuid', $jobUuid)
            ->where('device_id', $device->device_id)
            ->first();
    }
}
