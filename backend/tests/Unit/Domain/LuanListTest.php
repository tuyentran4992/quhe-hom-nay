<?php

namespace Tests\Unit\Domain;

use App\Domain\LuanList;
use PHPUnit\Framework\TestCase;

/**
 * RL-BE (card t_0e5c0eb9, D1/D2) — nhãn 8 nhóm + excerpt UTF-8-safe cho
 * GET /api/draws/{draw_id}/luans. Pure unit: không DB/facade (01 §2).
 * Nguồn bảng nhãn: LABELS.md (t_RL-UX) — 'Tài lộc' chữ l thường; phap_ly/
 * tong_quan/KHAC/UNCLEAR/key lạ → gộp «Điều cần bàn».
 */
class LuanListTest extends TestCase
{
    // ==== label ====

    /** D1: 8 nhãn hiển thị từ router_category (đúng token RouterPrompt::DOMAINS). */
    public function test_label_map_8_nhan_tu_router_category(): void
    {
        $this->assertSame('Tình duyên', LuanList::label('tinh_duyen', null));
        $this->assertSame('Tài lộc', LuanList::label('tai_loc', null));
        $this->assertSame('Công việc', LuanList::label('cong_viec', null));
        $this->assertSame('Đi lại', LuanList::label('di_lich', null));
        $this->assertSame('Sức khỏe', LuanList::label('suc_khoe', null));
        $this->assertSame('Học hành', LuanList::label('hoc_hanh', null));
        $this->assertSame('Gia đình', LuanList::label('gia_dinh', null));
        foreach (['phap_ly', 'tong_quan', 'KHAC', 'UNCLEAR'] as $t) {
            $this->assertSame('Điều cần bàn', LuanList::label($t, 'duyen'), "domain $t gộp về Điều cần bàn");
        }
    }

    /** A3: router NULL/lạ → tra topic (CEO chốt: xuat_hanh → 'Xuất hành', KHÔNG để 'Công việc · đi lại'). */
    public function test_label_fallback_theo_topic_khi_router_null_hoac_la(): void
    {
        $this->assertSame('Tình duyên', LuanList::label(null, 'duyen'));
        $this->assertSame('Tài lộc', LuanList::label(null, 'tai_loc'));
        $this->assertSame('Xuất hành', LuanList::label(null, 'xuat_hanh'));
        $this->assertSame('Tình duyên', LuanList::label('khong_co_that', 'duyen'), 'token lạ = coi như NULL');
    }

    /** A3: cả hai NULL → 'Điều cần bàn' (luôn có nhãn, không bao giờ null). */
    public function test_label_ca_hai_null_dieu_can_ban(): void
    {
        $this->assertSame('Điều cần bàn', LuanList::label(null, null));
    }

    // ==== excerpt ====

    /** Bỏ boilerplate đầu bài ("Đây là luận giải…" + '---'), lấy từ marker ### [Hoàn cảnh]. */
    public function test_excerpt_strip_boilerplate_dau(): void
    {
        $result = "Đây là luận giải chuyên sâu dành cho bạn, dựa trên quẻ hôm nay.\n\n---\n\n### [Hoàn cảnh]\n\nQuẻ hôm nay nhắc mình chậm lại.";
        $ex = LuanList::excerpt($result);
        $this->assertStringStartsWith('### [Hoàn cảnh]', $ex);
        $this->assertStringNotContainsString('Đây là luận giải', $ex);
    }

    /** ≤120 code-point tính cả «…», cắt tại khoảng trắng gần nhất, không chặt giữa từ. */
    public function test_excerpt_cat_120_codepoint_tai_khoang_trang(): void
    {
        // 300 ký tự không dấu + có dấu xen kẽ, toàn khoảng trắng đơn giữa các từ
        $words = [];
        for ($i = 0; $i < 60; $i++) {
            $words[] = $i % 5 === 0 ? 'huyền' : 'thường';
        }
        $result = '### [Hoàn cảnh] '.implode(' ', $words).' ### [Vì sao khuyên vậy] cái gì đó rất dài phía sau';
        $ex = LuanList::excerpt($result);

        $this->assertLessThanOrEqual(120, mb_strlen($ex), 'trần 120 code-point');
        $this->assertStringEndsWith('…', $ex, 'bị cắt phải có ellipsis');
        // không chặt giữa từ: thân excerpt (bỏ «…» và khoảng trắng trước nó) phải
        // kết thúc bằng MỘT TOÀN VẸN trong 2 từ của bài, không phải đầu «thườ»
        $body = rtrim(mb_substr($ex, 0, -1));
        $this->assertTrue(
            str_ends_with($body, 'thường') || str_ends_with($body, 'huyền'),
            "cắt phải nguyên từ, đuôi thực tế: ...".mb_substr($body, -8)
        );

        // không rò nội dung khối sau trần cắt
        $this->assertStringNotContainsString('[Vì sao', $ex);
    }

    /** UTF-8-safe: không được sinh chuỗi broken byte khi trần cắt giữa cluster dấu tiếng Việt. */
    public function test_excerpt_utf8_safe_khong_chet_giua_byte(): void
    {
        $result = '### [Hoàn cảnh] '.str_repeat('ẩ ', 100);
        $ex = LuanList::excerpt($result);
        $this->assertTrue(mb_check_encoding($ex, 'UTF-8'));
        $this->assertLessThanOrEqual(120, mb_strlen($ex));
    }

    /** Bài ngắn (<120) → nguyên văn sau boilerplate, KHÔNG có «…». */
    public function test_excerpt_ngan_giu_nguyen_khong_ellipsis(): void
    {
        $result = "Đây là luận giải mở đầu.\n\n---\n\n### [Hoàn cảnh]\nNgắn gọn thôi.";
        $ex = LuanList::excerpt($result);
        $this->assertSame('### [Hoàn cảnh] Ngắn gọn thôi.', $ex);
        $this->assertStringNotContainsString('…', $ex);
    }

    /** Collapse nhiều khoảng trắng/xuống dòng thành 1 dấu cách (preview 1 dòng). */
    public function test_excerpt_collapse_whitespace(): void
    {
        $result = "### [Hoàn cảnh]\n\nQuẻ hôm nay\nnhắc mình   chậm lại.";
        $this->assertStringContainsString('Quẻ hôm nay nhắc mình chậm lại.', LuanList::excerpt($result));
    }

    /** result null/rỗng → '' (phòng thủ, done-luôn-có-result nhưng hợp đồng phải an toàn). */
    public function test_excerpt_null_tra_ve_rong(): void
    {
        $this->assertSame('', LuanList::excerpt(null));
        $this->assertSame('', LuanList::excerpt('   '));
    }
}
