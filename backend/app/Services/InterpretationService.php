<?php

namespace App\Services;

use App\Domain\Calendar;
use App\Domain\Topic;
use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * BE-2 — 03-api §5/#6: gate 402 → KHÓA done (409) → cooldown C-03 → cap C-06
 * → INSERT ai_jobs + dispatch.
 * 1 trách nhiệm: ĐIỀU PHỐI job luận sâu. KHÔNG gọi AI-Box ở đây (chỉ RunAiBoxJob — 01 §2),
 * không đụng tiền (PaymentService).
 *
 * REVIEW-LUAN (t_8aa93a01): cache AC-2 "tạo job done ngay tại chỗ" đã bị THAY
 * bằng 409 AI_ALREADY_DONE (boss GO 02/09 — mỗi (quẻ, topic) chỉ luận 1 lần ở
 * giai đoạn này). Nguồn đọc lại = #5b GET /api/ai/interpretations/saved.
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

        // LUAN-V2 §4.1: question đã strip/trim ở controller — đây là chốt cuối
        // (service có thể gọi thẳng từ test/CLI): rỗng sau trim → null. DB lưu BẢN TRIM.
        $question = trim((string) ($body['question'] ?? ''));
        $question = $question !== '' ? $question : null;

        // idempotency TRƯỚC mọi gate (F6: same key same body = same job, không nhân row).
        // LUAN-V2 D1: hash 3 thành phần — cùng key cũ + thêm question → lệch hash → 409
        // có chủ đích (question đổi bài luận, không phải cùng body).
        $hash = hash('sha256', $draw->id.'|'.$topic->value.'|'.($question ?? ''));
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
        // PREVIEW OVERRIDE (CFG-BE: nguồn chuẩn = config/project.php, env chỉ để
        // bật preview): boss tạm mở luận sâu free — bỏ qua 402, GIỮ cooldown/cap/
        // idempotency. Tắt bằng free_deep_preview=false trong project.php.
        if (! $paid && ! config('project.free_deep_preview')) {
            throw InterpretationException::unlockRequired($topic->value);
        }

        // (c1) REVIEW-LUAN (t_8aa93a01): KHÓA 1 lượt luận per (hexagram, topic) —
        // nguồn khóa = MỌI job done cùng quẻ+chủ đề, BẤT KỂ question (rộng hơn
        // nguồn cache AC-2 vẫn whereNull question). Gate đặt TRƯỚC cooldown/cap/
        // INSERT: hết cooldown không thành đường lách, và 409 không tạo rac job.
        // CFG-BE: bọc bằng cờ project.ai.lock_one_luan — =false quay về đường cũ
        // (cooldown/cap vẫn giữ); mặc định TRUE = hành vi boss đã GO 02/09.
        if (config('project.ai.lock_one_luan')
            && $this->findDoneSource($draw->hexagram_id, $topic->value) !== null) {
            throw InterpretationException::alreadyDone();
        }

        // (c) cooldown C-03 theo time device → config('project.ai.cooldown_seconds').
        $cooldown = (int) config('project.ai.cooldown_seconds');
        $last = AiJob::query()->where('device_id', $device->device_id)
            ->max('requested_at');
        if ($last !== null) {
            $elapsed = now()->diffInSeconds(Carbon::parse($last), true);
            if ($elapsed < $cooldown) {
                throw InterpretationException::cooldown(
                    (int) ceil($cooldown - $elapsed)
                );
            }
        }

        // (d) cap TOÀN CỤC C-06: job TẠO MỚI trong 60 phút gần nhất ≤ cap config.
        if (AiJob::query()->where('requested_at', '>=', now()->subHour())->count()
            >= (int) config('project.ai.global_cap_per_hour')) {
            throw InterpretationException::globalCap();
        }

        // (e) INSERT + dispatch — đường 202 DUY NHẤT còn lại (không còn nhánh
        // "cache done tại chỗ": đã thay bằng 409 ở (c1) theo card t_8aa93a01).
        $job = AiJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'device_id' => $device->device_id,
            'draw_id' => $draw->id,
            'topic' => $topic->value,
            'question' => $question,
            'status' => AiJob::ST_QUEUED,
            'requested_at' => now(),
            'idempotency_key' => $key ?: null,
            'result_key_hash' => $hash,
        ]);

        RunAiBoxJob::dispatch($job->id);

        return $job;
    }

    /**
     * REVIEW-LUAN — NGUỒN KHÓA/#5b: job `done` mới nhất cùng hexagram + topic,
     * BẤT KỂ question (rộng hơn nguồn cache AC-2 cũ — le cu chi nhan question
     * NULL, nieng theo card chot; AC-2 done-tai-cho không còn dùng nguồn này).
     * done của bất kỳ device nào cũng khóa: khóa theo quẻ+chủ đề, không theo draw/device.
     */
    public function findDoneSource(int $hexagramId, string $topic): ?AiJob
    {
        return AiJob::query()
            ->where('status', AiJob::ST_DONE)
            ->where('topic', $topic)
            ->whereIn('draw_id', function ($q) use ($hexagramId) {
                $q->select('id')->from('draws')->where('hexagram_id', $hexagramId);
            })
            ->latest('finished_at')
            ->first();
    }

    /**
     * #5b REVIEW-LUAN — quyết định đọc lại: validate hình dạng ở controller, mọi
     * gate (404 ẩn tồn tại → 402 entitlement như #5 → tra nguồn done) ở đây.
     * @return array{exists:bool, job_uuid:?string, result:?string, completed_at:?string}
     */
    public function saved(Device $device, int $drawId, Topic $topic): array
    {
        $draw = $device->draws()->whereKey($drawId)->first();
        if ($draw === null) {
            // ẩn tồn tại F7: draw của device khác = 404, không lộ là có quẻ này
            throw InterpretationException::notFound('draw_id không hợp lệ với thiết bị này.');
        }

        // entitlement CÙNG điều kiện #5 (kể cả preview flag) — cấm chia bài khi mở khóa
        $paid = Payment::query()
            ->where('device_id', $device->device_id)
            ->where('kind', 'unlock')->where('topic', $topic->value)
            ->where('status', Payment::ST_PAID)->exists();
        if (! $paid && ! config('project.free_deep_preview')) {
            throw InterpretationException::unlockRequired($topic->value);
        }

        $source = $this->findDoneSource((int) $draw->hexagram_id, $topic->value);

        return [
            'exists' => $source !== null,
            'job_uuid' => $source?->job_uuid,
            'result' => $source?->result,
            'completed_at' => $source?->finished_at?->format('Y-m-d\TH:i:s\Z'),
        ];
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
