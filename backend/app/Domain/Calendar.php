<?php

namespace App\Domain;

/**
 * BE-2 — lịch nghiệp vụ VN (02-db §5 drawn_date = dương lịch Asia/Ho_Chi_Minh;
 * 03-api #1 server_date_vn). Pure PHP, nhận timestamp override để test fake date
 * (05-testplan F3 yêu cầu DateProvider mock được).
 */
final class Calendar
{
    public const VN_TZ = 'Asia/Ho_Chi_Minh';

    /** "YYYY-MM-DD" dương lịch VN tại $unixTs (mặc định = now). */
    public static function todayVn(?int $unixTs = null): string
    {
        return self::at($unixTs)->format('Y-m-d');
    }

    /** "YYYY-MM-DD" của ngày VN liền trước. */
    public static function yesterdayVn(?int $unixTs = null): string
    {
        return self::at($unixTs)->modify('-1 day')->format('Y-m-d');
    }

    /** 0h VN kế tiếp (UTC unix ts) — dùng cho DRAW_LIMIT_REACHED.next_draw_at. */
    public static function nextMidnightVn(?int $unixTs = null): int
    {
        $t = self::at($unixTs)->modify('tomorrow midnight');

        return $t->getTimestamp();
    }

    /** RFC3339 UTC của 0h VN kế tiếp. */
    public static function nextMidnightVnRfc3339(?int $unixTs = null): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', self::nextMidnightVn($unixTs));
    }

    private static function at(?int $unixTs): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@'.($unixTs ?? time())))
            ->setTimezone(new \DateTimeZone(self::VN_TZ));
    }

    private function __construct()
    {
    }
}
