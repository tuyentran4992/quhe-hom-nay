<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BE-2 — specs/1.mvp/02-db.md §2 `devices` (danh tính chính, gốc mọi entitlement).
 * PK là device_id CHAR(26) tự sinh, không auto-increment.
 */
class Device extends Model
{
    protected $table = 'devices';

    public $incrementing = false;

    protected $keyType = 'string';

    public $primaryKey = 'device_id';

    /** 02-db §2: devices không có created_at/updated_at — chỉ first_seen/last_seen. */
    public $timestamps = false;

    protected $fillable = ['device_id', 'session_id'];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
    ];

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class, 'device_id', 'device_id');
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(AiJob::class, 'device_id', 'device_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'device_id', 'device_id');
    }
}
