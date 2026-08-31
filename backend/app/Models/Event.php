<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MKT-F2 — specs/1.mvp/06-mkt-tracking.md §2 `events`: telemetry thô theo device
 * (whitelist name ở const này — 1 nguồn sự thật, TrackController đọc trực tiếp;
 * MKT-F6-fix thêm donate_open). Bảng chỉ có created_at.
 */
class Event extends Model
{
    public const NAME_WHITELIST = ['landing_visit', 'cta_gieo_que', 'donate_open'];

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
