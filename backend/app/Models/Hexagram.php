<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Danh mục 64 quẻ (02-db §4, nội dung SEED-01 sha256 76cfc11f...).
 * Ánh xạ 1-1 cột → field API §2 (03-api): snake_case nguyên DB, JSON cast thẳng;
 * KHÔNG BAO GIỜ có canSoi (cột không tồn tại — 02-db §4).
 */
class Hexagram extends Model
{
    protected $fillable = [
        'id', 'han', 'ten', 'quoc_am', 'upper', 'lower', 'lines', 'symbol', 'dai_ci',
        'free_content', 'keywords', 'vv_nien', 'cat', 'ban_goc', 'luan_nay',
    ];

    public $incrementing = false;

    protected $keyType = 'int';

    protected $casts = [
        'id' => 'int',
        'lines' => 'array',
        'free_content' => 'array',
        'keywords' => 'array',
        'cat' => 'array',
        'ban_goc' => 'array',
    ];

    /**
     * Payload §2 — đúng 16 field hợp đồng, key snake_case như cột DB;
     * lines/keywords/cat là mảng số/chuỗi; free_content/ban_goc là object JSON nguyên trạng.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => (int) $this->id,
            'han' => $this->han,
            'ten' => $this->ten,
            'quoc_am' => $this->quoc_am,
            'upper' => $this->upper,
            'lower' => $this->lower,
            'lines' => array_map(intval(...), $this->lines ?? []),
            'symbol' => $this->symbol,
            'dai_ci' => $this->dai_ci,
            'free_content' => $this->free_content,
            'keywords' => $this->keywords,
            'vv_nien' => $this->vv_nien,
            'cat' => $this->cat,
            'ban_goc' => $this->ban_goc,
            'luan_nay' => $this->luan_nay,
        ];
    }
}
