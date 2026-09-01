<?php

namespace Tests\Unit\Domain;

use App\Domain\BianRule;
use PHPUnit\Framework\TestCase;

/**
 * LUAN-V2 (SPEC-LUAN-V2 §3.2, card t_c86f3954) — luật Biện quẻ Chu Hy 7 case.
 * Pure unit: không DB/facade (01 §2). T1–T8 theo bảng §9 SPEC.
 */
class BianRuleTest extends TestCase
{
    public function test_0_dong_quyse_tu_goc_khong_bien(): void
    {
        $r = BianRule::quiTrinh([]);
        $this->assertSame(0, $r['n_dong']);
        $this->assertNull($r['chu_tich']);
        $this->assertTrue($r['can_quese_goc']);
        $this->assertFalse($r['can_loi_bien']);
        $this->assertStringContainsString('Luận theo quẻ từ quẻ gốc', $r['loi_luan']);
        $this->assertStringContainsString('không có hào động', mb_strtolower($r['loi_luan']));
    }

    public function test_1_dong_hao_tu_hao_dong(): void
    {
        $r = BianRule::quiTrinh([3]);
        $this->assertSame(1, $r['n_dong']);
        $this->assertSame(3, $r['chu_tich']);
        $this->assertSame('dong', $r['chu_tich_vi_tri']);
        $this->assertFalse($r['can_quese_goc']);
        $this->assertFalse($r['can_loi_bien'], 'D2: case 1 cấm nội dung biến');
        $this->assertStringContainsString('hào 3', $r['loi_luan']);
        $this->assertStringContainsString('duy nhất', $r['loi_luan']);
    }

    public function test_2_dong_hao_tren_lam_chu(): void
    {
        $r = BianRule::quiTrinh([2, 5]);
        $this->assertSame(2, $r['n_dong']);
        $this->assertSame(5, $r['chu_tich']);
        $this->assertSame('trên', $r['chu_tich_vi_tri']);
        $this->assertFalse($r['can_quese_goc']);
        $this->assertFalse($r['can_loi_bien'], 'D2: case 2 cấm nội dung biến');
        $this->assertStringContainsString('CẢ HAI hào động', $r['loi_luan']);
        $this->assertStringContainsString('hào TRÊN (hào 5)', $r['loi_luan']);
    }

    public function test_3_dong_can_goc_va_bien(): void
    {
        $r = BianRule::quiTrinh([1, 2, 3]);
        $this->assertSame(3, $r['n_dong']);
        $this->assertNull($r['chu_tich']);
        $this->assertTrue($r['can_quese_goc']);
        $this->assertTrue($r['can_loi_bien'], 'D2: case 3 là case duy nhất <6 mở biến');
        $this->assertStringContainsString('gốc làm chủ', $r['loi_luan']);
        $this->assertStringContainsString('biến làm ứng', $r['loi_luan']);
    }

    public function test_4_dong_hao_tinh_duoi_lam_chu(): void
    {
        $r = BianRule::quiTrinh([1, 2, 3, 4]);
        $this->assertSame(4, $r['n_dong']);
        $this->assertSame(5, $r['chu_tich']);
        $this->assertSame('dưới', $r['chu_tich_vi_tri']);
        $this->assertFalse($r['can_quese_goc']);
        $this->assertFalse($r['can_loi_bien'], 'D2: case 4 cấm nội dung biến');
        $this->assertStringContainsString('2 hào TĨNH', $r['loi_luan']);
        $this->assertStringContainsString('hào DƯỚI (hào 5)', $r['loi_luan']);
    }

    public function test_5_dong_hao_tinh_duy_nhat(): void
    {
        $r = BianRule::quiTrinh([1, 2, 3, 4, 5]);
        $this->assertSame(5, $r['n_dong']);
        $this->assertSame(6, $r['chu_tich']);
        $this->assertSame('tinh', $r['chu_tich_vi_tri']);
        $this->assertFalse($r['can_quese_goc']);
        $this->assertFalse($r['can_loi_bien'], 'D2: case 5 cấm nội dung biến');
        $this->assertStringContainsString('hào TĨNH duy nhất (hào 6)', $r['loi_luan']);
    }

    public function test_6_dong_can_dung_cua_khon_riac_lai_quese_bien(): void
    {
        $all = [1, 2, 3, 4, 5, 6];

        // Quẻ Càn (id1): dùng lời DỤNG (quần long vô thủ), KHÔNG quẻ từ gốc/biến.
        $r = BianRule::quiTrinh($all, 1);
        $this->assertSame(6, $r['n_dong']);
        $this->assertNull($r['chu_tich']);
        $this->assertFalse($r['can_quese_goc'], 'case 6 Càn/Khôn: không dùng quẻ từ');
        $this->assertTrue($r['can_loi_bien']);
        $this->assertStringContainsString('quần long', mb_strtolower($r['loi_luan']));
        $this->assertStringContainsString('dụng', mb_strtolower($r['loi_luan']));

        // Quẻ Khôn (id2): dụng lục.
        $k = BianRule::quiTrinh($all, 2);
        $this->assertTrue($k['can_loi_bien']);
        $this->assertStringContainsString('dụng', mb_strtolower($k['loi_luan']));

        // Quẻ thường (id30 Ly Vi Hỏa): 6 động → luận theo quẻ từ QUẺ BIẾN (id30 → Khôn id1... 
        // Càn=1 [1,1,1,1,1,1] 6 dong → bien Khôn=2; Ly=30 lines [1,0,1,1,0,1] → bien [0,1,0,0,1,0]=id2 Khôn)
        $c = BianRule::quiTrinh($all, 30);
        $this->assertTrue($c['can_quese_goc'], 'case 6 quẻ thường: có block quẻ (của BIẾN)');
        $this->assertTrue($c['can_loi_bien']);
        $this->assertStringContainsString('quẻ từ QUẺ BIẾN', $c['loi_luan']);
    }

    public function test_bian_rule_reject_vi_ngoai_khoang(): void
    {
        foreach ([[0], [7], [2, 2], [1, 1], [3, 2, 1, 2]] as $bad) {
            $this->expectException(\InvalidArgumentException::class);
            BianRule::quiTrinh($bad);
        }
    }
}
