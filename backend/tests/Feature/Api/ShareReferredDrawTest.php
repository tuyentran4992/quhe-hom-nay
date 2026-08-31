<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use Tests\ApiTestCase;

/**
 * F7-BE (ADR-002 §3) — V7 `share_referred_draw`: device có first-touch
 * utm_medium=share POST /api/draws 201 → hook SAU response, bắn 1 event
 * props {draw_id}. DrawService/roller KHÔNG đụng — moc ở DrawController.
 * Device không utm_medium=share → không bắn (không nhiễu số K-factor).
 */
class ShareReferredDrawTest extends ApiTestCase
{
    public function test_referred_device_draw_fires_v7_once(): void
    {
        // đến qua share
        $r = $this->asDevice(null)->postJson('/api/track', [
            'name' => 'landing_visit',
            'utm' => ['source' => 'app_card', 'medium' => 'share', 'campaign' => 'share_card_v1'],
        ]);
        $dev = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'qhn_device')->getValue();

        $this->asDevice($dev)->postJson('/api/draws', [])->assertStatus(201);

        $ev = Event::query()->where('name', 'share_referred_draw')->sole();
        $this->assertSame($dev, $ev->device_id);
        $this->assertArrayHasKey('draw_id', $ev->props);

        // gieo ngày hôm sau → bắn tiếp (mỗi draw 1 event)
        \Carbon\Carbon::setTestNow('2026-08-31 02:00:00');
        $this->asDevice($dev)->postJson('/api/draws', [])->assertStatus(201);
        $this->assertSame(2, Event::query()->where('name', 'share_referred_draw')->count());
    }

    public function test_organic_device_draw_does_not_fire_v7(): void
    {
        $dev = $this->deviceId(); // không utm
        $this->asDevice($dev)->postJson('/api/draws', [])->assertStatus(201);
        $this->assertSame(0, Event::query()->where('name', 'share_referred_draw')->count());
    }

    /** medium kênh khác (social…) KHÔNG tính referred — khóa đúng utm_medium=share. */
    public function test_other_medium_does_not_fire(): void
    {
        $r = $this->asDevice(null)->postJson('/api/track', [
            'name' => 'landing_visit',
            'utm' => ['medium' => 'social'],
        ]);
        $dev = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'qhn_device')->getValue();
        $this->asDevice($dev)->postJson('/api/draws', [])->assertStatus(201);
        $this->assertSame(0, Event::query()->where('name', 'share_referred_draw')->count());
    }
}
