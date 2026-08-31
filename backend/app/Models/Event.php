<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MKT-F2 — specs/1.mvp/06-mkt-tracking.md §2 `events`: telemetry thô theo device
 * (whitelist name ở TrackService, không phải ở model). Bảng chỉ có created_at.
 * F7-BE (ADR-002 §1 / F7-CONTRACT §1): +7 tên event share METRICS V1–V7
 * NGUYÊN VĂN, không prefix qhn_ — pipeline reject ngoài whitelist vẫn 422 ở F2.
 */
class Event extends Model
{
    public const NAME_WHITELIST = [
        'landing_visit', 'cta_gieo_que', // F2
        // F7-BE — METRICS t_09f89119 V1–V7 (thêm cuối, giữ thứ tự F2):
        'share_card_open',
        'share_card_created',
        'share_card_error',
        'share_card_done',
        'share_link_view',
        'share_link_cta_click',
        'share_referred_draw',
    ];

    public $timestamps = false;

    protected $table = 'events';

    protected $fillable = ['device_id', 'name', 'props'];

    protected $casts = [
        'props' => 'array',
        'created_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }
}
