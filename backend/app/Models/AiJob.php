<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BE-2 — specs/1.mvp/02-db.md §7 `ai_jobs`.
 * Bất biến trạng thái 1 chiều queued→running→done|failed (transit() chặn bước ngược).
 * cooldown C-03 + cap C-06 đếm từ requested_at; result cache theo (draw, topic).
 */
class AiJob extends Model
{
    public const ST_QUEUED = 'queued';

    public const ST_RUNNING = 'running';

    public const ST_DONE = 'done';

    public const ST_FAILED = 'failed';

    private const ALLOWED_TRANSITIONS = [
        self::ST_QUEUED => [self::ST_RUNNING, self::ST_FAILED],
        self::ST_RUNNING => [self::ST_DONE, self::ST_FAILED],
        self::ST_DONE => [],
        self::ST_FAILED => [],
    ];

    protected $fillable = [
        'job_uuid', 'device_id', 'draw_id', 'topic', 'question', 'status', 'attempts',
        'result', 'error_code', 'requested_at', 'finished_at',
        'idempotency_key', 'result_key_hash',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'requested_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    /** Chuyển trạng thái có log — vi phạm 1 chiều = RuntimeException (rule tiền/queue). */
    public function transitTo(string $next, array $extra = []): void
    {
        if (! in_array($next, self::ALLOWED_TRANSITIONS[$this->status], true)) {
            throw new \RuntimeException("ai_jobs#{$this->id}: bước trạng thái ngược {$this->status} → {$next} bị cấm");
        }
        $this->forceFill(['status' => $next] + $extra)->save();
    }

    /** 03-api §6 payload poll — 7 field đúng contract. */
    public function toApi(): array
    {
        return [
            'job_uuid' => $this->job_uuid,
            'status' => $this->status,
            'topic' => $this->topic,
            'result' => $this->status === self::ST_DONE ? $this->result : null,
            'error_code' => $this->status === self::ST_FAILED ? $this->error_code : null,
            'requested_at' => $this->requested_at->format('Y-m-d\TH:i:s\Z'),
            'finished_at' => $this->finished_at?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
