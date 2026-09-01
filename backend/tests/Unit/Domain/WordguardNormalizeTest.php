<?php

namespace Tests\Unit\Domain;

use App\Domain\Wordguard;
use PHPUnit\Framework\TestCase;

/**
 * BUG-V3-4 (card t_fc8a8953, nguồn QA t_dcc84365) — `**` thô rỉ ra UI.
 *
 * FE luanRender.js CHU DICH giu nguyen `**` (quyet dinh 1-cho duy nhat BUG-V3-2:
 * BE normalize mot cho TRUOC khi luu result). Worker that doi — 3/6 bai THAT
 * (DB_162, DB_50, DB_51) van den mat khach nguyen van `**`.
 *
 * Bien nghiem (card y 1): chi doi `**x**` -> `x` — CAM mat ky tu thuong,
 * CAM an nham `*italic don*` / `**` khong dong cap.
 */
final class WordguardNormalizeTest extends TestCase
{
    /** Fixture THAT nguyen ven DB_162 (evidence QA) — 1 cap `**` can doi,
     *  1 dong `*italic*` cuoi bai phai SONG. */
    private const DB162_PATH = '/data/agents/qa-engineer/outbox/t_dcc84365/evidence/t5_db/DB_162_c1-duyen-khop.txt';

    private function assertNoConsume(string $in, string $out): void
    {
        $strip = static fn (string $s) => str_replace(['*', "\n"], '', $s);
        $this->assertSame($strip($in), $strip($out),
            'normalize CAM an nham ky tu thuong — moi sai khac phai la dau *');
    }

    public function test_bai_that_db162_sach_cap_va_giu_italic_don(): void
    {
        if (!is_file(self::DB162_PATH)) {
            $this->markTestSkipped('thieu evidence QA t_dcc84365 tren may chay test');
        }
        $raw = file_get_contents(self::DB162_PATH);

        $out = Wordguard::stripBoldMarkers($raw);

        $this->assertStringNotContainsString('**', $out);
        // noi dung chu giu NGUYEN VEN, chi mat cap `**` in dam
        $this->assertStringContainsString('lời khuyên từ quẻ là: hãy chủ động, nhưng đừng chủ động tràn lan.', $out);
        $this->assertStringContainsString("*Chỉ mang tính tham khảo giải trí về văn hoá.*", $out);
        $this->assertStringContainsString('*“Kiến long tại điền, lợi kiến đại nhân.”* —', $out);
        $this->assertNoConsume($raw, $out);
    }

    public function test_doi_cap_can_ben_xuyen_dong(): void
    {
        $out = Wordguard::stripBoldMarkers("**a b** và **c-d**");
        $this->assertSame('a b và c-d', $out);
    }

    public function test_mark_am_khong_luu_ky_tu_dac_biet(): void
    {
        // model kiem dinh bat thuong: **\n** (mo dong ngay sau la xuong dong)
        $out = Wordguard::stripBoldMarkers("**\n**");
        $this->assertStringNotContainsString("**", $out);
    }

    public function test_stray_giua_bai_cung_la_marker_strip_italic_don_giu(): void
    {
        $out = Wordguard::stripBoldMarkers("giữa dòng **chưa đóng cặp, hết\n\n*ghi chú in nghiêng* đơn\n");
        $this->assertStringNotContainsString('**', $out);
        // italic đơn (1 dấu *) KHÔNG phải đối tượng → còn NGUYÊN ven
        $this->assertStringContainsString("*ghi chú in nghiêng* đơn\n", $out);
        // chữ không mất: chỉ đúng 2 ký tự `**` biến mất
        $this->assertSame("giữa dòng chưa đóng cặp, hết\n\n*ghi chú in nghiêng* đơn\n", $out);
    }

    public function test_bold_le_chen_italic_giu_dau_le(): void
    {
        // `***x***` (markdown bold+italic): strip non-overlapping -> `*x*` — con
        // dau `*` le giu nguyen, khong an nham chu x
        $this->assertSame('*x*', Wordguard::stripBoldMarkers('***x***'));
    }

    public function test_khong_doi_van_ban_sach(): void
    {
        $in = "dòng bình thường, 10% giảm, a*b*c, — gạch ngang.\n";
        $this->assertSame($in, Wordguard::stripBoldMarkers($in));
    }

    public function test_idempotent(): void
    {
        $in = "lời khuyên: **hãy chủ động**, đừng *lan man*.";
        $once = Wordguard::stripBoldMarkers($in);
        $this->assertSame($once, Wordguard::stripBoldMarkers($once));
    }

    public function test_unicode_khong_phase_dau_tiem(): void
    {
        $out = Wordguard::stripBoldMarkers("**hãy chủ động—tràn lan** nhé");
        $this->assertSame('hãy chủ động—tràn lan nhé', $out);
    }
}
