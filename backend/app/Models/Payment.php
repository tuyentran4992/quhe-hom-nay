<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BE-2 — specs/1.mvp/02-db.md §6 `payments`.
 * Entitlement = row (device_id, kind=unlock, topic, status=paid) — §6 ghi rõ.
 * Trạng thái đơn 1 chiều có log (stub #7/#7b BE-2; code payOS thật = PAY-01).
 */
class Payment extends Model
{
    public const ST_PENDING = 'pending';
    public const ST_PAID = 'paid';
    public const ST_CANCELLED = 'cancelled';
    public const ST_EXPIRED = 'expired';
    public const ST_REFUNDED = 'refunded';

    private const ALLOWED_TRANSITIONS = [
        self::ST_PENDING => [self::ST_PAID, self::ST_CANCELLED, self::ST_EXPIRED],
        self::ST_PAID => [self::ST_REFUNDED],
        self::ST_CANCELLED => [],
        self::ST_EXPIRED => [],
        self::ST_REFUNDED => [],
    ];

    protected $fillable = [
        'order_code', 'device_id', 'user_id', 'kind', 'topic', 'amount_vnd',
        'status', 'gateway_ref', 'paid_at', 'idempotency_key', 'request_hash',
    ];

    protected $casts = [
        'order_code' => 'integer',
        'amount_vnd' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    /** Bước trạng thái 1 chiều + log (bất biến tiền — sai 1 bước = exception). */
    public function transitTo(string $next, array $extra = []): void
    {
        if ($this->status === $next) {
            return; // idempotent: webhook/simulate lặp không phải lỗi
        }
        if (! in_array($next, self::ALLOWED_TRANSITIONS[$this->status], true)) {
            throw new \RuntimeException("payments#{$this->id}: bước trạng thái ngược {$this->status} → {$next} bị cấm");
        }
        $this->forceFill(['status' => $next] + $extra)->save();
    }

    /** 03-api #9 payload — 6 field đúng contract. */
    public function toStatusApi(): array
    {
        return [
            'order_code' => (int) $this->order_code,
            'status' => $this->status,
            'kind' => $this->kind,
            'topic' => $this->topic,
            'amount_vnd' => (int) $this->amount_vnd,
            'paid_at' => $this->paid_at?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
