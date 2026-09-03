<?php

namespace Tests\Unit\Domain;

use App\Domain\RouterPrompt;
use PHPUnit\Framework\TestCase;

/**
 * LUAN-V3 (card t_a97403d8, SPEC-LUAN-V3 amended §5.2/§8 T22) + ROUTER-FMT
 * (card t_18927e08) — prompt + parse cho bước ROUTER danh mục.
 *
 * Parse: trim + uppercase-insensitive match whitelist ĐÚNG 11 token DOMAINS
 * (nâng từ 5 giá trị tab cũ — 3 nhãn duyen/xuat_hanh/KHONG_THUOC_NAO KHÔNG còn
 * thuộc từ vựng router, kỳ vọng enum sửa theo card, test cũ không bỏ).
 * Mọi output khác (văn bản thừa, rỗng, free-text) → UNCLEAR = về luồng cũ,
 * KHÔNG phải lỗi.
 */
class RouterPromptTest extends TestCase
{
    /** ROUTER-FMT A1 — const DOMAINS đúng 11 token, thứ tự chốt theo card. */
    public function test_domains_const_dung_11_token(): void
    {
        $this->assertSame([
            'tinh_duyen', 'tai_loc', 'cong_viec', 'di_lich', 'suc_khoe',
            'hoc_hanh', 'gia_dinh', 'phap_ly', 'tong_quan', 'KHAC', 'UNCLEAR',
        ], RouterPrompt::DOMAINS);
    }

    /** ROUTER-FMT A1 — DOMAIN_TO_TAB: 11 domain → tab cũ / KHONG_THUOC_NAO / null. */
    public function test_domain_to_tab_dung_bang_map_chot(): void
    {
        $this->assertSame([
            'tinh_duyen' => 'duyen',
            'tai_loc' => 'tai_loc',
            'cong_viec' => 'xuat_hanh',
            'di_lich' => 'xuat_hanh',
            'suc_khoe' => 'KHONG_THUOC_NAO',
            'hoc_hanh' => 'KHONG_THUOC_NAO',
            'gia_dinh' => 'KHONG_THUOC_NAO',
            'phap_ly' => 'KHONG_THUOC_NAO',
            'tong_quan' => 'KHONG_THUOC_NAO',
            'KHAC' => 'KHONG_THUOC_NAO',
            'UNCLEAR' => null,
        ], RouterPrompt::DOMAIN_TO_TAB);
    }

    /** T22a (nâng cấp 11 domain) — nhận đúng, bất chấp whitespace/hoa-thường. */
    public function test_router_prompt_parse_11_domains(): void
    {
        $cases = [
            "tinh_duyen\n" => 'tinh_duyen',
            ' TINH_DUYEN ' => 'tinh_duyen',
            'tai_loc' => 'tai_loc',
            'TAI_LOC' => 'tai_loc',
            'cong_viec' => 'cong_viec',
            'CONG_VIEC' => 'cong_viec',
            'di_lich' => 'di_lich',
            'DI_LICH' => 'di_lich',
            'suc_khoe' => 'suc_khoe',
            'SUC_KHOE' => 'suc_khoe',
            'hoc_hanh' => 'hoc_hanh',
            'gia_dinh' => 'gia_dinh',
            'phap_ly' => 'phap_ly',
            'tong_quan' => 'tong_quan',
            'khac' => 'KHAC',
            'KHAC' => 'KHAC',
            'UNCLEAR' => 'UNCLEAR',
            'unclear' => 'UNCLEAR',
        ];
        foreach ($cases as $raw => $want) {
            $this->assertSame($want, RouterPrompt::parse((string) $raw), "parse('$raw')");
        }
    }

    /** T22b — câu văn thừa/quanh từ khóa/label tab CŨ → UNCLEAR (không đoán, cấm free-text). */
    public function test_parse_thua_van_ban_va_rong_ra_unclear(): void
    {
        $this->assertSame('UNCLEAR', RouterPrompt::parse('có lẽ là duyen'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse(''));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('   '));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('{"topic": "duyen"}'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('duyen tai_loc'));
        // ROUTER-FMT: ngoài whitelist 11 → UNCLEAR hết, kể cả nhãn tab cũ
        $this->assertSame('UNCLEAR', RouterPrompt::parse('duyen duyen'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('tinh duyen'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('tien_trinh'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('duyen.'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('duyen'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('xuat_hanh'));
        $this->assertSame('UNCLEAR', RouterPrompt::parse('KHONG_THUOC_NAO'));
    }

    /** T22c (nâng cấp 11 domain) — prompt đủ nhãn + câu hỏi nhúng nguyên văn + lệnh 1 từ. */
    public function test_prompt_chu_du_nhan_va_cau_hoi(): void
    {
        $p = RouterPrompt::forQuestion('bao giờ em có người yêu');
        foreach (RouterPrompt::DOMAINS as $label) {
            $this->assertStringContainsString($label, $p);
        }
        $this->assertStringContainsString('Câu hỏi: "bao giờ em có người yêu"', $p);
        $this->assertStringContainsString('ĐÚNG một từ', $p);
        // bản 056beba bug đếm "sáu khả năng"; ROUTER-FMT chốt 11
        $this->assertStringNotContainsString('sáu khả năng', $p);
        $this->assertStringNotContainsString('năm khả năng', $p);
        $this->assertStringContainsString('mười một khả năng', $p);
    }

    /** ROUTER-FMT A1 — bẫy bề mặt: nguyên tắc + ví dụ cưới-tốn-tiền giữ nguyên tinh thần. */
    public function test_prompt_giu_nguyen_tac_dieu_khach_muon_biet(): void
    {
        $p = RouterPrompt::PROMPT;
        $this->assertStringContainsString('ĐIỀU VIỆC KHÁCH MUỐN BIẾT', $p);
        $this->assertStringContainsString('cưới tốn bao nhiêu tiền', $p);
        $this->assertStringContainsString('tai_loc', $p);
    }
}
