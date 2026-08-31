<?php

namespace Tests\Feature\Api;

use App\Models\Draw;
use App\Models\Hexagram;
use Tests\ApiTestCase;

/**
 * BUG-F7-QA3 (t_b0cfc1b4) — /s/{token} hiển thị hook NGUYÊN VĂN 217 ký tự
 * (payload QA t_a24795b4/payload2.json, hex 48 nguồn hao_dong) → vi phạm
 * SPEC-THE §2 (hook hiển ≤80, cắt tại ranh giới câu/dấu phẩy).
 * Fix phương án 1 của card: BE expose hook.text_clip80 server-side, Blade
 * dùng bản clipped cho <p class="hook"> + og:description.
 *
 * Chuỗi RED thật: thuật toán + field chưa tồn tại → các assert clip80 fail.
 */
class ShareHookClip80Test extends ApiTestCase
{
    /** Bản 217 ký tự nguyên văn từ hex 48 hao 1 (bệnh án QA, nguồn hao_dong). */
    private const HOOK_217 = 'Giếng đã ta sạch rồi mà chưa ai múc — làm đau lòng ta. Vốn đã tới lúc dùng được, chỉ cần người trên sáng suốt thì cả hai cùng hưởng phúc. Tượng: người đi đường thấy vậy cũng thương; mong vua sáng là để nhận được phúc.';

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\HaoTextSeeder())->run();
    }

    private function tokenWithHook(int $hexagramId, array $changing): string
    {
        $dev = $this->deviceId();
        $draw = Draw::query()->create([
            'device_id' => $dev,
            'hexagram_id' => $hexagramId,
            'drawn_date' => self::VN_DATE,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => $changing,
        ]);

        return $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');
    }

    /** hex 48 hao 3 == 217 ký tự (đúng mắt xích bệnh án — nếu seed đổi, fail rõ). */
    public function test_fixture_hook_is_217_codepoints(): void
    {
        $nghia = \App\Models\HaoText::query()->where('hexagram_id', 48)->where('vi', 3)->sole()->nghia;
        $this->assertSame(self::HOOK_217, $nghia, 'nguồn hao_dong hex48/vi3 phải đúng bản 217 ký của QA');
        $this->assertSame(217, mb_strlen($nghia));
    }

    public function test_api_hook_exposes_text_clip80_under_80(): void
    {
        $token = $this->tokenWithHook(48, [4, 3]); // đổi thứ tự — vi nhỏ nhất = 3 thắng
        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');

        $this->assertArrayHasKey('text_clip80', $hook, 'BE phải expose hook.text_clip80 (card t_b0cfc1b4)');
        $this->assertSame(self::HOOK_217, $hook['text'], 'text NGUYÊN VĂN giữ bất biến (FE canvas cần bản đầy đủ)');
        $this->assertLessThanOrEqual(80, mb_strlen((string) $hook['text_clip80']));
        $this->assertStringStartsWith($hook['text_clip80'], self::HOOK_217, 'clip là tiền tố nguyên văn, không cắt giữa từ');
        $this->assertNotSame($hook['text'], $hook['text_clip80']);
    }

    public function test_page_p_hook_and_og_description_under_80(): void
    {
        $token = $this->tokenWithHook(48, [3]);
        $html = $this->asDevice(null)->get("/s/{$token}")->assertOk()->getContent();

        $this->assertSame(217, mb_strlen(self::HOOK_217));
        $this->assertStringNotContainsString(self::HOOK_217, $html, 'trang không còn in nguyên văn 217 ký');

        // <p class="hook">“…”</p>
        $this->assertSame(1, preg_match('/<p class="hook">“(.+?)”<\/p>/su', $html, $m), 'phải có <p class="hook">');
        $this->assertLessThanOrEqual(80, mb_strlen(html_entity_decode($m[1], ENT_QUOTES)), '<p class="hook"> vượt trần 80 ký tự');

        // og:description + meta description (blade xuất `content="..."`)
        $this->assertSame(1, preg_match('/property="og:description" content="([^"]*)"/u', $html, $o), 'thiếu og:description');
        $this->assertLessThanOrEqual(80, mb_strlen(html_entity_decode($o[1], ENT_QUOTES)), 'og:description vượt trần 80 ký tự');
        $this->assertSame(1, preg_match('/name="description" content="([^"]*)"/u', $html, $d), 'thiếu meta description');
        $this->assertLessThanOrEqual(80, mb_strlen(html_entity_decode($d[1], ENT_QUOTES)), 'meta description vượt trần 80 ký tự');
    }

    /** Hook ngắn (dai_ci TH2 ≤80) → text_clip80 == text, trang hiển nguyên văn. */
    public function test_short_hook_clip_equals_text(): void
    {
        $token = $this->tokenWithHook(1, []); // dai_ci hex1 → vế đầu "Sáu hào đều dương"
        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $this->assertSame($hook['text'], $hook['text_clip80'] ?? null);

        $html = $this->asDevice(null)->get("/s/{$token}")->assertOk()->getContent();
        $this->assertStringContainsString('<p class="hook">“Sáu hào đều dương”</p>', $html);
    }

    /** E6 minimal (text rỗng) → text_clip80 rỗng, trang không in <p class="hook">. */
    public function test_e6_minimal_hook_stays_minimal(): void
    {
        Hexagram::query()->where('id', 2)->update(['dai_ci' => '']);
        $token = $this->tokenWithHook(2, []);
        $hook = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card.hook');
        $this->assertSame('minimal', $hook['source']);
        $this->assertSame('', $hook['text_clip80'] ?? 'MISSING');

        $html = $this->asDevice(null)->get("/s/{$token}")->assertOk()->getContent();
        $this->assertStringNotContainsString('class="hook"', $html);
    }
}
