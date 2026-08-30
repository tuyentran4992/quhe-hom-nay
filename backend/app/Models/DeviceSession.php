<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; phiên device (02-db §2 cột device_sessions
 * nếu tách; framework `sessions` là driver SESSION_DRIVER=database).
 * BE-1 khai triển khi có migration tương ứng.
 */
class DeviceSession extends Model
{
    protected $guarded = ['*'];
}
