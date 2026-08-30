<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BE-2 — specs/1.mvp/02-db.md §5 `draws`. C-01 do uq_draws_device_date chặn ở DB,
 * code không tự enforce. BE-1 thêm logic gieo; model này chỉ map đủ cho gate #5 +
 * payload §3.2 (draw object dùng chung #1 #3 #10).
 */
class Draw extends Model
{
    protected $fillable = [
        'device_id', 'user_id', 'hexagram_id', 'drawn_date', 'lines_rolled', 'changing_lines',
    ];

    protected $casts = [
        'lines_rolled' => 'array',
        'changing_lines' => 'array',
        'drawn_date' => 'date:Y-m-d',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function hexagram(): BelongsTo
    {
        return $this->belongsTo(Hexagram::class, 'hexagram_id');
    }

    /**
     * draw object 03-api §3.2 — 6 field, RFC3339 UTC, shape cố định (FE đọc #1/#3/#10).
     */
    public function toApi(): array
    {
        return [
            'id' => (int) $this->id,
            'hexagram_id' => (int) $this->hexagram_id,
            'drawn_date' => $this->drawn_date->format('Y-m-d'),
            'lines_rolled' => array_map('intval', $this->lines_rolled ?? []),
            'changing_lines' => array_map('intval', $this->changing_lines ?? []),
            'created_at' => $this->created_at->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
