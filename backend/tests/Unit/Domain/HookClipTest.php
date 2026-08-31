<?php

namespace Tests\Unit\Domain;

use App\Domain\HookClip;
use PHPUnit\Framework\TestCase;

/**
 * BUG-F7-QA3 (t_b0cfc1b4) — UT thuận toán clip80 server-side cho hook hiển thị
 * (SPEC-THE §2 ≤80 ký tự, cắt tại ranh giới câu/dấu phẩy). Thuật toán PHẢI ≥
 * bản FE clip80 (utils/shareCard.js): cùng ưu tiên dấu câu → ranh giới từ,
 * code-point-safe (mb_*, tiếng ViệtCompose + chữ Hán không vỡ đa-byte).
 */
class HookClipTest extends TestCase
{
    /** Câu hook thật 217 ký tự từ payload QA (hex 48, nguồn hao_dong). */
    private const HOOK_217 = 'Giếng đã ta sạch rồi mà chưa ai múc — làm đau lòng ta. Vốn đã tới lúc dùng được, chỉ cần người trên sáng suốt thì cả hai cùng hưởng phúc. Tượng: người đi đường thấy vậy cũng thương; mong vua sáng là để nhận được phúc.';

    public function test_short_text_is_returned_verbatim(): void
    {
        $this->assertSame('Sáu hào đều dương', HookClip::clip('Sáu hào đều dương'));
    }

    public function test_exactly_80_is_not_clipped(): void
    {
        $t = str_repeat('a ', 39).'ab'; // đúng 80 ký tự
        $this->assertSame(80, mb_strlen($t));
        $this->assertSame($t, HookClip::clip($t));
    }

    public function test_empty_and_whitespace_return_null(): void
    {
        $this->assertNull(HookClip::clip(''));
        $this->assertNull(HookClip::clip('   '));
    }

    public function test_cuts_at_last_punctuation_within_limit_preserving_it(): void
    {
        // câu dài 217 ký tự thật: dấu gần trần nhất ≤80 phải được GIỮ lại
        $clip = HookClip::clip(self::HOOK_217);
        $this->assertNotNull($clip);
        $this->assertLessThanOrEqual(80, mb_strlen($clip));
        $this->assertStringStartsWith('Giếng đã ta sạch rồi', $clip);
        $last = mb_substr($clip, -1);
        $this->assertContains($last, HookClip::PUNCT, 'phải kết thúc tại dấu câu được giữ');
        // không được cắt mất cả mệnh đề đầu khi mệnh đề vẫn ≤80
        $this->assertStringContainsString('múc', $clip);
    }

    public function test_punctuation_priority_scans_downward_not_greedy(): void
    {
        // "aaaaaaaa, bbbbbbbb." dài 20 ký tự với trần 15: cắt tại DẤU CHẤM-PHẨY
        // cuối cùng ≤15 (giữ dấu), không phải dấu đầu tiên.
        $t = 'aaaaaaaa, bbbbbbbb.';
        $clip = HookClip::clip($t, 15);
        $this->assertSame('aaaaaaaa,', $clip);
    }

    public function test_falls_back_to_word_boundary_when_no_punctuation_fits(): void
    {
        $t = 'rắn dài không dấu phẩy gì cả nhưng rất nhiều từ nối tiếp nhau để vượt trần tám mươi ký tự hoàn toàn';
        $clip = HookClip::clip($t, 40);
        $this->assertNotNull($clip);
        $this->assertLessThanOrEqual(40, mb_strlen($clip));
        $this->assertStringEndsNotWith(' ', $clip, 'không để cách thừa');
        // phải cắt tại khoảng trắng — từ cuối không bị xén
        $this->assertStringContainsString(' ', $clip);
    }

    public function test_returns_null_when_single_word_exceeds_max(): void
    {
        $this->assertNull(HookClip::clip(str_repeat('chữ', 100), 80));
        $this->assertNull(HookClip::clip(str_repeat('a', 81)));
    }

    public function test_code_point_safe_vietnamese_and_han(): void
    {
        // chuỗi toàn chữ Hán + dấu câu Hán: cắt không được vỡ byte (mb_strlen
        // đếm code-point; ký tự cuối phải là dấu câu giữ lại, không phải mojibake)
        $t = '君子以自强不息，厚德载物。'.str_repeat('盈不可久。', 10);
        $clip = HookClip::clip($t, 20);
        $this->assertNotNull($clip);
        $this->assertLessThanOrEqual(20, mb_strlen($clip));
        $this->assertSame($clip, mb_convert_encoding(mb_convert_encoding($clip, 'UTF-8', 'UTF-8'), 'UTF-8'));
        $this->assertContains(mb_substr($clip, -1), ['。', '，'], 'giữ dấu câu Hán trong PUNCT');
    }

    public function test_217_char_hook_clips_under_80_and_is_prefix_of_original(): void
    {
        $clip = HookClip::clip(self::HOOK_217);
        $this->assertLessThanOrEqual(80, mb_strlen($clip));
        $this->assertStringStartsWith($clip, self::HOOK_217, 'clip phải là ĐẦU của nguyên văn (không chèn/xén)');
    }
}
