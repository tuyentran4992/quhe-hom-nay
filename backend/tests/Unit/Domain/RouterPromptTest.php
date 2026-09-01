<?php

namespace Tests\Unit\Domain;

use App\Domain\RouterPrompt;
use PHPUnit\Framework\TestCase;

/**
 * LUAN-V3 (card t_a97403d8, SPEC-LUAN-V3 amended §5.2/§8 T22) — hằng số prompt
 * router + parse whitelist ĐÚNG 5 giá trị. RED-first: class chưa tồn tại → fail.
 *
 * Parse §5.2: trim + uppercase-insensitive match whitelist 5 giá trị;
 * DƯ THỪA văn bản → coi như UNCLEAR (về luồng-cũ, không phải lỗi).
 */
class RouterPromptTest extends TestCase
{
    /** T22a — 5 giá trị whitelist nhận đúng, bất chấp whitespace/hoa-thường. */
    public function test_router_prompt_parse_5_gia_tri(): void
    {
        $cases = [
            "duyen\n" => 'duyen',
            ' DUYEN ' => 'duyen',
            'tai_loc' => 'tai_loc',
            'TAI_LOC' => 'tai_loc',
            'xuat_hanh' => 'xuat_hanh',
            'XUAT_HANH' => 'xuat_hanh',
            'KHONG_THUOC_NAO' => 'KHONG_THUOC_NAO',
            ' khong_thuoc_nao ' => 'KHONG_THUOC_NAO',
            'UNCLEAR' => 'UNCLEAR',
            'unclear' => 'UNCLEAR',
        ];
        foreach ($cases as $raw => $want) {
            $this->assertSame($want, RouterPrompt::parse((string) $raw), "parse('$raw')");
        }
    }

    /** T22b — câu văn thừa quanh từ khóa → UNCLEAR (không đoán). */
    public function test_parse_thua_van_ban_va_rong_ra_unclear(): void
    {
        $this->assertSame('UNCLEAR', RouterPrompt::parse('có lẽ là duyen'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse(''));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('   '));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('{"topic": "duyen"}'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('duyen tai_loc'));
    }

    /** T22c — prompt router chứa đủ 5 nhãn + câu hỏi nhúng nguyên văn + lệnh 1 từ. */
    public function test_prompt_chu_5_nhan_va_cau_hoi(): void
    {
        $p = RouterPrompt::forQuestion('bao giờ em có người yêu');
        foreach (['duyen', 'tai_loc', 'xuat_hanh', 'KHONG_THUOC_NAO', 'UNCLEAR'] as $label) {
            $this->assertStringContainsString($label, $p);
        }
        $this->assertStringContainsString('Câu hỏi: "bao giờ em có người yêu"', $p);
        $this->assertStringContainsString('ĐÚNG một từ', $p);
        // bản 056beba bug đếm "sáu khả năng" — amended chốt 5 (§5.2)
        $this->assertStringNotContainsString('sáu khả năng', $p);
        $this->assertStringContainsString('năm khả năng', $p);
    }
}
