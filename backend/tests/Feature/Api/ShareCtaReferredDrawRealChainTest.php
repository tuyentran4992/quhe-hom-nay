<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Draw;
use App\Models\Event;
use Tests\ApiTestCase;

/**
 * BUG-F7-QA1 (t_18c8b219) — tái hiện CHUỖI THẬT của QA (FIX-ROUND-1.md BUG-1):
 * A tạo share link → device B HOÀN TOÀN MỚI mở /s/{token} → B bấm CTA
 * (GET /s/{token}/cta, 302 mang utm) → B gieo quẻ thật → V7 `share_referred_draw`
 * PHẢI fire. Test KHÔNG tự seed devices.utm_* hộ production — mắt xích ghi utm
 * là thứ bị gãy (recordCtaClick chỉ ghi utm_medium vào PROPS event, không ghi
 * CỘT devices.utm_medium mà maybeFireReferredDraw đọc).
 *
 * Test cũ ShareReferredDrawTest xanh vì tự bắn /api/track payload utm hộ cái mà
 * chuột thật KHÔNG bắn (SPA không capture utm ở /app/*). Đây là test chống lại
 * "test xanh ≠ hàng dùng được".
 */
class ShareCtaReferredDrawRealChainTest extends ApiTestCase
{
    /** Draw tay cho sharer A (không cần luồng gieo thật — chỉ cần token để B bấm). */
    private function tokenFor(string $sharerDevice): string
    {
        $draw = Draw::query()->create([
            'device_id' => $sharerDevice,
            'hexagram_id' => 11,
            'drawn_date' => self::VN_DATE,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => [2, 4],
        ]);

        return $this->asDevice($sharerDevice)
            ->postJson('/api/share-links', ['draw_id' => $draw->id])
            ->json('token');
    }

    /**
     * CHUỖI THẬT: B mới tinh vào /s/ → bấm CTA → gieo quẻ → V7 fire +
     * devices.utm_* của B được ghi first-touch từ chính bộ utm CTA redirect.
     */
    public function test_real_mouse_chain_cta_click_then_draw_fires_v7(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);

        // B: cookie null → EnsureDeviceSession sinh device mới tinh (không utm nào)
        $respB = $this->asDevice(null)->get("/s/{$token}")->assertOk();
        $deviceB = collect($respB->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'qhn_device')->getValue();

        $this->assertNull(
            Device::query()->find($deviceB)->utm_medium,
            'Điều kiện tiền đề: device B phải trắng utm (như QA), không được seed hộ'
        );

        // B bấm CTA — chuột chỉ nhận 302, KHÔNG bắn /api/track (SPA không capture utm)
        $cta = $this->asDevice($deviceB)->get("/s/{$token}/cta");
        $cta->assertStatus(302);
        $this->assertStringContainsString('utm_medium=share', (string) $cta->headers->get('Location'));

        // >>> Mắt xích gãy: CTA click phải để lại utm trên CỘT devices của B <<<
        $rowB = Device::query()->find($deviceB);
        $this->assertSame('share', $rowB->utm_medium, 'CTA click phải ghi devices.utm_medium first-touch');
        $this->assertSame('app_card', $rowB->utm_source);
        $this->assertSame('share_card_v1', $rowB->utm_campaign);

        // B gieo quẻ thật → V7 fire 1 event, props {draw_id}
        $this->asDevice($deviceB)->postJson('/api/draws', [])->assertStatus(201);

        $v7 = Event::query()->where('name', 'share_referred_draw')->where('device_id', $deviceB)->sole();
        $this->assertSame(
            (int) Draw::query()->where('device_id', $deviceB)->value('id'),
            (int) $v7->props['draw_id']
        );
    }

    /** FIRST-TOUCH-KHÓA: B đã có utm từ kênh khác → CTA KHÔNG đè (bất biến 06-mkt §2). */
    public function test_cta_click_does_not_overwrite_existing_utm(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);

        // B đến từ kênh social trước (có sẵn utm_medium='social' — not 'share')
        $r = $this->asDevice(null)->postJson('/api/track', [
            'name' => 'landing_visit',
            'utm' => ['source' => 'fb', 'medium' => 'social', 'campaign' => 'boot'],
        ]);
        $deviceB = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'qhn_device')->getValue();

        $this->asDevice($deviceB)->get("/s/{$token}/cta")->assertStatus(302);

        $rowB = Device::query()->find($deviceB);
        $this->assertSame('social', $rowB->utm_medium, 'first-touch: cột đã có giá trị thì CTA không đè');
        $this->assertSame('fb', $rowB->utm_source);

        // B gieo quẻ → KHÔNG phải referred (medium≠share) → V7 im lặng
        $this->asDevice($deviceB)->postJson('/api/draws', [])->assertStatus(201);
        $this->assertSame(0, Event::query()->where('name', 'share_referred_draw')->count());
    }

    /** B bấm CTA NHƯNG không gieo quẻ → không có V7, không có draw (idempotent funnel). */
    public function test_cta_click_without_draw_fires_nothing_extra(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);
        $deviceB = $this->deviceId();

        $this->asDevice($deviceB)->get("/s/{$token}/cta")->assertStatus(302);

        $this->assertSame(1, Event::query()->where('name', 'share_link_cta_click')->count());
        $this->assertSame(0, Event::query()->where('name', 'share_referred_draw')->count());
    }

    /** CTA token rác → 404 nhẹ, KHÔNG ghi utm vào device phát sinh (chống đốt first-touch). */
    public function test_cta_unknown_token_does_not_capture_utm(): void
    {
        $this->asDevice(null)->get('/s/nosuchtoken1/cta')->assertNotFound();

        $this->assertSame(
            0,
            Device::query()->whereNotNull('utm_medium')->count(),
            '404 không được để lại utm_medium ở bất kỳ device nào'
        );
        $this->assertSame(0, Event::query()->where('name', 'share_link_cta_click')->count());
    }
}
