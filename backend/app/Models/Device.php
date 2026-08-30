<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; device = danh tính chính (02-db §2).
 * Fillable/casts/relations: BE-1 điền khi migration `devices` merge.
 */
class Device extends Model
{
    protected $guarded = ['*'];
}
