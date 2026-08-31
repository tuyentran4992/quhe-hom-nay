<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BE-3XU — 02-db §4b `hexagram_hao_texts` (384 từ hào, PK kép hexagram_id+vi).
 * Bảng chỉ-đọc qua seeder; không có route tự ghi.
 */
class HaoText extends Model
{
    protected $table = 'hexagram_hao_texts';

    protected $fillable = ['hexagram_id', 'vi', 'hao', 'han', 'quoc_am', 'nghia'];

    protected $casts = [
        'hexagram_id' => 'int',
        'vi' => 'int',
    ];

    public $timestamps = true;

    public function hexagram(): BelongsTo
    {
        return $this->belongsTo(Hexagram::class, 'hexagram_id');
    }

    /** Phần tử `hao_texts` 03-api §3/§2b — đúng 5 field {vi,hao,han,quoc_am,nghia}. */
    public function toApi(): array
    {
        return [
            'vi' => (int) $this->vi,
            'hao' => (string) $this->hao,
            'han' => (string) $this->han,
            'quoc_am' => (string) $this->quoc_am,
            'nghia' => (string) $this->nghia,
        ];
    }
}
