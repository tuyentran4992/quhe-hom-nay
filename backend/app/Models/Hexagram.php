<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; danh mục 64 quẻ (02-db §4, nội dung do SEED-01).
 * Cấm query trước khi migration hexagrams (branch card/t_fdb90b30) merge.
 */
class Hexagram extends Model
{
    protected $guarded = ['*'];

    public $incrementing = false;

    protected $keyType = 'int';
}
