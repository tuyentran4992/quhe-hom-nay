<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kết quả gieo quẻ (02-db §5). BẤT BIẾN C-01: uq_draws_device_date chặn trùng
 * device+ngày ngay ở DB — service không tự đếm kẻo race.
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

    public function hexagram(): BelongsTo
    {
        return $this->belongsTo(Hexagram::class, 'hexagram_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    /**
     * draw object dùng chung §3.2 (03-api) — id, hexagram_id, drawn_date,
     * lines_rolled, changing_lines (DB NULL → [] theo contract), created_at RFC3339 UTC.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => (int) $this->id,
            'hexagram_id' => (int) $this->hexagram_id,
            'drawn_date' => $this->drawn_date->format('Y-m-d'),
            'lines_rolled' => array_map(intval(...), $this->lines_rolled ?? []),
            'changing_lines' => array_map(intval(...), $this->changing_lines ?? []),
            'created_at' => $this->created_at?->copy()->timezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
