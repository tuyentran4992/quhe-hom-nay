<?php

namespace Tests;

use Carbon\Carbon;
use Database\Seeders\HexagramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * BE-1 — nền test chung cho nhóm endpoint "gieo quẻ/hiện tại" (03-api #1..#4, #10).
 * - Seed 64 quẻ (nguồn khóa SEED-01) — không có quẻ thì không tra được pattern.
 * - Đóng băng "hôm nay" = 2026-08-30 02:15 UTC (= 09:15 VN, giữa ngày dương lịch VN).
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    /** Ngày VN chốt cho test (Asia/Ho_Chi_Minh). */
    protected const VN_DATE = '2026-08-30';

    protected function setUp(): void
    {
        parent::setUp();
        (new HexagramSeeder())->run();
        Carbon::setTestNow('2026-08-30 02:15:00');
        // Laravel chỉ nhét cookie vào request khi withCredentials (MakesHttpRequests:715);
        // qhn_device là cookie PLAIN (HttpOnly, không mã hóa) → gửi qua withUnencryptedCookies.
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Gửi request tiếp theo với cookie device $id (null = client mới, không cookie). */
    protected function asDevice(?string $deviceId): static
    {
        // THAY hẳn jar cookie (không merge) để test đa-device (A→B→A) không lẫn danh tính
        $this->unencryptedCookies = $deviceId === null ? [] : ['qhn_device' => $deviceId];

        return $this;
    }

    /**
     * Tương thích cú pháp withHeaders($this->drawHeaders($id)): ĐĂNG KÝ cookie qua
     * withUnencryptedCookies (side effect) rồi trả mảng header rỗng.
     */
    protected function drawHeaders(string $deviceId): array
    {
        $this->asDevice($deviceId);

        return [];
    }

    /**
     * Gọi GET /api/me với cookie device đang có (null = device lạ) → trả về value
     * cookie qhn_device (HttpOnly nên đọc qua header).
     */
    protected function deviceId(?string $cookie = null): string
    {
        $resp = $this->asDevice($cookie)->getJson('/api/me');
        $resp->assertOk();
        $new = collect($resp->headers->getCookies())->first(
            fn (Cookie $c) => $c->getName() === 'qhn_device'
        );

        return $new?->getValue() ?? $cookie;
    }
}
