<?php

namespace Tests\Unit\Domain;

use App\Domain\Wordguard;
use PHPUnit\Framework\TestCase;

/**
 * FIX-LUAN-SAU 02/09 (card t_20f28886) — Wordguard::matchedWords: feedback
 * regenerate phải nêu ĐÚNG chữ phạm, không đoán mò. Bẫy kinh điển: "cốt lõi"
 * (từ ghép nghĩa trong sáng) chạm pattern \bc[oố]t\b — model cần biết chính xác
 * chữ "cốt" bị chặn để viết lại đúng chỗ, không cắt cả câu.
 */
final class WordguardMatchedWordsTest extends TestCase
{
    public function test_trich_dung_doan_khop_ke_ca_tu_ghep_oan(): void
    {
        // fixture thật gây bệnh ~1/5 lượt: "cốt lõi" dính pattern c[oố]t
        $words = Wordguard::matchedWords('Cốt lõi của quẻ là biết dừng đúng lúc.');

        $this->assertSame(['cốt'], $words, 'phải trả đúng đoạn khớp, lowercase');
    }

    public function test_unique_theo_thu_tu_pattern(): void
    {
        // nhiều chữ cấm trong 1 bài ("bùa" lặp 2 lần): trả đúng các chữ phạm, unique
        $words = Wordguard::matchedWords('Làm lễ bùa cúng tế. Mua bùa ngay hôm nay.');

        $this->assertSame($words, array_values(array_unique($words)), 'kết quả phải unique');
        $this->assertSame(['cúng', 'bùa'], $words, 'theo thứ tự pattern trong BANNED_PATTERNS');
    }

    public function test_van_bat_duoc_dau_hoa(): void
    {
        $this->assertSame(['thỉnh'], Wordguard::matchedWords('Thỉnh lâu ngày trong tâm sẽ an.'));
    }

    public function test_sach_tra_rong(): void
    {
        $this->assertSame([], Wordguard::matchedWords(
            'Bài này hoàn toàn sạch, chỉ tham khảo giải trí về văn hoá.'
        ));
    }
}
