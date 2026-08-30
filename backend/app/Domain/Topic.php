<?php

namespace App\Domain;

/**
 * C-02 (specs/1.mvp/03-api.md §0): đúng 3 chủ đề unlock, khớp enum DB `topic`.
 * Pure PHP — cấm import facade/HTTP.
 */
enum Topic: string
{
    case Duyen = 'duyen';
    case TaiLoc = 'tai_loc';
    case XuatHanh = 'xuat_hanh';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
