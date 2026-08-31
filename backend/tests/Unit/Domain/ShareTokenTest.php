<?php

namespace Tests\Unit\Domain;

use App\Domain\ShareToken;
use PHPUnit\Framework\TestCase;

/**
 * F7-BE (ADR-002 §3) — ShareToken thuần PHP: base62(10) CSPRNG, pattern DeviceIdentity.
 * E4 06/SPEC-THE: token không suy ra draw_id, không brute-force (base62^10 ≈ 8.4e17).
 */
class ShareTokenTest extends TestCase
{
    public function test_generate_returns_10_base62_chars(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $t = ShareToken::generate();
            $this->assertSame(10, strlen($t), "token phải đúng 10 ký tự, got '$t'");
            $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{10}$/', $t);
        }
    }

    public function test_generate_is_unique_across_samples(): void
    {
        $seen = [];
        for ($i = 0; $i < 2000; $i++) {
            $t = ShareToken::generate();
            $this->assertArrayNotHasKey($t, $seen, "va chạm token '$t' trong 2000 mẫu — CSPRNG/alphabet hỏng");
            $seen[$t] = true;
        }
    }

    /** CSPRNG thật: các ký tự phân bố đều trên 62 mặt (n=20k, mỗi mặt kỳ vọng ~3225). */
    public function test_distribution_covers_alphabet(): void
    {
        $counts = [];
        for ($i = 0; $i < 2000; $i++) {
            foreach (str_split(ShareToken::generate()) as $c) {
                $counts[$c] = ($counts[$c] ?? 0) + 1;
            }
        }
        $this->assertGreaterThanOrEqual(55, count($counts), 'phải phủ ≥55/62 mặt alphabet');
    }

    public function test_is_valid_accepts_only_10_base62(): void
    {
        $this->assertTrue(ShareToken::isValid(ShareToken::generate()));
        $this->assertTrue(ShareToken::isValid('0aZ9zA1b2c'));
        $this->assertFalse(ShareToken::isValid(null));
        $this->assertFalse(ShareToken::isValid(''));
        $this->assertFalse(ShareToken::isValid('short1234'));   // 9 ký tự
        $this->assertFalse(ShareToken::isValid('toolong1234')); // 11 ký tự
        $this->assertFalse(ShareToken::isValid('has-dash1'));   // ngoài bảng 0-9A-Za-z
        $this->assertFalse(ShareToken::isValid('has space1'));
        $this->assertFalse(ShareToken::isValid('unicodeạ1'));
    }
}
