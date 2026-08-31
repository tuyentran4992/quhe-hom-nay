<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\ShareLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\ApiTestCase;

/**
 * F7-BE — SharePageController::showPage (GET /s/{token}) + GET /s/{token}/cta
 * theo F7-CONTRACT §1/§3 (lệnh dev-lead 18:15). BE sở hữu controller + đếm view
 * V5 (1/device/token/ngày — kiểm row events props.token+name+hôm nay VN trước),
 * FE sở hữu blade `share` (dev-lead merge view thật).
 */
class SharePageTest extends ApiTestCase
{
    private function tokenFor(string $dev): string
    {
        $draw = \App\Models\Draw::query()->create([
            'device_id' => $dev,
            'hexagram_id' => 11,
            'drawn_date' => self::VN_DATE,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => [2, 4],
        ]);

        return $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');
    }

    public function test_show_page_returns_200_share_view_with_public_data(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);

        $resp = $this->asDevice(null)->get("/s/{$token}");
        $resp->assertOk();
        $resp->assertViewIs('share');
        $view = $resp->original->getData();
        $this->assertSame($token, $view['payload']['card']['qr_text'] === "/s/{$token}" ? $token : null);
        $this->assertSame(
            ['hexagram_id', 'symbol', 'ten', 'drawn_date', 'hook', 'keywords', 'disclaimer', 'qr_text'],
            array_keys($view['payload']['card'])
        );
        $this->assertArrayHasKey('sharer_label', $view['payload']);
    }

    /** V5 — đếm views: 1/device/token/NGÀY (F7-CONTRACT §3 + lệnh dev-lead 18:23). */
    public function test_view_counted_once_per_device_per_day(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);

        // người lạ A mở 3 lần trong ngày → 1 view
        $devA = $this->deviceId();
        for ($i = 0; $i < 3; $i++) {
            $this->asDevice($devA)->get("/s/{$token}")->assertOk();
        }
        $this->assertSame(1, ShareLink::query()->where('token', $token)->value('views'));
        $this->assertSame(1, Event::query()->where('name', 'share_link_view')->count());

        // người lạ B mở → +1
        $devB = $this->deviceId();
        $this->asDevice($devB)->get("/s/{$token}")->assertOk();
        $this->assertSame(2, ShareLink::query()->where('token', $token)->value('views'));

        // A quay lại NGÀY HÔM SAU (vẫn trong Carbon test? không — nhảy ngày) → +1
        Carbon::setTestNow('2026-08-31 02:00:00');
        $this->asDevice($devA)->get("/s/{$token}")->assertOk();
        $this->assertSame(3, ShareLink::query()->where('token', $token)->value('views'));
    }

    /** Event share_link_view phải mang props token + device_is_new (METRICS V5). */
    public function test_view_event_carries_token_props(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);
        $this->asDevice(null)->get("/s/{$token}")->assertOk(); // device hoàn toàn mới

        $ev = Event::query()->where('name', 'share_link_view')->sole();
        $this->assertSame($token, $ev->props['token']);
        $this->assertTrue($ev->props['device_is_new']);
    }

    /** Chính chủ (sharer) mở link của mình KHÔNG đếm — chống phình views. */
    public function test_sharer_self_view_not_counted(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);

        $this->asDevice($sharer)->get("/s/{$token}")->assertOk();
        $this->assertSame(0, ShareLink::query()->where('token', $token)->value('views'));
        $this->assertSame(0, Event::query()->where('name', 'share_link_view')->count());
    }

    /** GET /s/{token}/cta → 302 đúng deep-link UTM khóa (F7-CONTRACT §2) + bắn V6. */
    public function test_cta_redirect_and_fires_event(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer);
        $viewer = $this->deviceId();

        $resp = $this->asDevice($viewer)->get("/s/{$token}/cta");
        $resp->assertStatus(302);
        $this->assertSame(
            '/app/draw?utm_source=app_card&utm_medium=share&utm_campaign=share_card_v1',
            parse_url($resp->headers->get('Location'), PHP_URL_PATH).'?'.parse_url($resp->headers->get('Location'), PHP_URL_QUERY)
        );

        $ev = Event::query()->where('name', 'share_link_cta_click')->sole();
        $this->assertSame($token, $ev->props['token']);
        $this->assertSame('share', $ev->props['utm_medium']);
    }

    /** CTA token lạ → 404 (không redirect lạc). */
    public function test_cta_unknown_token_404(): void
    {
        $this->get('/s/nosuchtoken/cta')->assertNotFound();
    }

    /** /s/ token lạ → 404 nhẹ E3, không 500, không lộ nội khu. */
    public function test_show_page_unknown_token_light_404(): void
    {
        $resp = $this->get('/s/zzzzzzzzzz');
        $resp->assertNotFound();
        $resp->assertViewIs('share-404');
        $body = $resp->getContent();
        $this->assertStringNotContainsString('draw_id', $body);
        $this->assertStringNotContainsString('device_id', $body);
        $this->assertStringNotContainsString('sql', strtolower($body));
    }

    /** Token dạng rác (không base62(10)) → 404 ngay, không query DB. */
    public function test_malformed_token_404(): void
    {
        $this->get('/s/short9ch')->assertNotFound();
        $this->get('/s/has-dash-1')->assertNotFound();
    }
}
