<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F7-BE — SPEC-THE §5 `share_links`: mapping token → (draw, device sharer).
 * Idempotency do uq_share_links_draw_device khóa ở DB; views đếm V5 ở service.
 */
class ShareLink extends Model
{
    public $timestamps = false;

    protected $table = 'share_links';

    protected $fillable = ['token', 'draw_id', 'device_id', 'created_at', 'views'];

    protected $casts = [
        'id' => 'int',
        'draw_id' => 'int',
        'views' => 'int',
        'created_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class, 'draw_id');
    }
}
