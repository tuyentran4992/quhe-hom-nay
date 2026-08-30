<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; one-time unlock + lễ tùy tâm (02-db §7).
 * Bất biến tiền: 1 chiều có log — PAY-01 implement, BE-2 stub guard.
 */
class Payment extends Model
{
    protected $guarded = ['*'];
}
