<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Device = danh tính chính (02-db §2, 02-db §8). Không auto-increment;
 * device_id CHAR(26) base32 do EnsureDeviceSession sinh qua CSPRNG.
 */
class Device extends Model
{
    protected $table = 'devices';

    protected $primaryKey = 'device_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['device_id', 'first_seen', 'last_seen', 'session_id'];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
    ];

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class, 'device_id', 'device_id');
    }
}
