<?php

namespace Tests\Unit\Services;

use App\Domain\JudgePrompt;
use PHPUnit\Framework\TestCase;

/**
 * QUOTA-N/Q3 (card t_1bb07a82) — parse + prompt của bước PHÂN QUYẾT paraphrase.
 * Pure PHP như RouterPrompt (01 §2): không facade/HTTP.
 *
 * Chuẩn phán quyết theo card: theo NGHĨA, không trùng từ khóa —
 * "hai láng giềng nhau" vs "thằng bao kia" = KHAC. Mock provider output ở
 * LlmParaphraseJudgeTest; đây là tầng văn bản thô.
 */
class JudgeVerdictTest extends TestCase
{
    public function test_parse_dung_3_token(): void
    {
        $this->assertSame('DU_GIONG', JudgePrompt::parse('DU_GIONG'));
        $this->assertSame('KHAC', JudgePrompt::parse('khac'));
        $this->assertSame('UNCLEAR', JudgePrompt::parse('  unclear  '));
    }

    public function test_parse_ranh_va_rac_ve_unclear_khong_bao_gio_throw(): void
    {
        // fail-open D4: mọi output ngoài whitelist → UNCLEAR (KHAC ở tầng ghép)
        $this->assertSame('UNCLEAR', JudgePrompt::parse(''));
        $this->assertSame('UNCLEAR', JudgePrompt::parse('   '));
        $this->assertSame('UNCLEAR', JudgePrompt::parse('có lẽ DU_GIONG không'));
        $this->assertSame('UNCLEAR', JudgePrompt::parse('DU_GIONG.'), 'cấm substring — nguyên token hoặc UNCLEAR');
        $this->assertSame('UNCLEAR', JudgePrompt::parse('tinh_duyen'), 'nhãn router không phải nhãn judge');
    }

    public function test_forpair_chua_ca_hai_cau_va_cam_free_text(): void
    {
        $p = JudgePrompt::forPair('hai láng giềng nhau', 'thằng bao kia');
        $this->assertStringContainsString('hai láng giềng nhau', $p);
        $this->assertStringContainsString('thằng bao kia', $p);
        $this->assertStringContainsString('DU_GIONG', $p);
        $this->assertStringContainsString('KHAC', $p);
        $this->assertStringContainsString('UNCLEAR', $p);
        $this->assertStringNotContainsString('{old}', $p);
        $this->assertStringNotContainsString('{new}', $p);
    }
}
