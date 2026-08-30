<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; job luận giải AI + cache quẻ+chủ đề (02-db §8).
 * Trạng thái 1 chiều pending→running→done|failed, cooldown/cap đếm từ bảng này (BE-2).
 */
class AiJob extends Model
{
    protected $guarded = ['*'];
}
