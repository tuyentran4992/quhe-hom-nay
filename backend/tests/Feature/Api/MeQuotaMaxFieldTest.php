<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;

/**
 * Q6 (card t_091a0424) — FE chip "Còn x/N" cần N ngay từ bootstrap, không chờ 429.
 * #1 GET /api/me + #10 GET /api/me/today trả THÊM top-level `max_deep_reads_per_draw`
 * = cùng nguồn QuotaService::maxPerDraw() (config project.ai.max_deep_reads_per_draw,
 * env MAX_DEEP_READS_PER_DRAW, default 3). Controller KHÔNG hardcode — test ca
 * config flip chứng minh 1 nguồn.
 *
 * FE zero-touch: DetailView.vue quotaMax đọc field này; TopicGate QUOTA_COPY.remaining
 * tự chuyển nhánh "Còn x/3 lần hỏi" desktop khi field có.
 */
class MeQuotaMaxFieldTest extends ApiTestCase
{
    /** Default N=3 — cả 2 endpoint phải có key với giá trị 3. */
    public function test_both_endpoints_expose_max_deep_reads_per_draw_default_3(): void
    {
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('max_deep_reads_per_draw', 3);

        $this->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.max_deep_reads_per_draw', 3);
    }

    /** Ca config flip: đổi N (đường env đi qua cùng config) → payload khớp, không hardcode. */
    public function test_field_follows_config_not_hardcoded(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 5]);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('max_deep_reads_per_draw', 5);

        $this->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.max_deep_reads_per_draw', 5);
    }

    /** maxPerDraw() có sàn max(1,·): config ≤0 → field vẫn là 1 (nhu cầu FE chia 0). */
    public function test_field_floors_at_one(): void
    {
        config(['project.ai.max_deep_reads_per_draw' => 0]);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('max_deep_reads_per_draw', 1);
    }

    /** Field có mặt cả khi đã gieo quẻ hôm nay (FE đọc N mọi lúc, không chỉ lúc clean). */
    public function test_field_present_after_draw_today(): void
    {
        $deviceId = $this->deviceId(); // bootstrap device qua cookie #1
        $this->withHeaders($this->drawHeaders($deviceId))->postJson('/api/draws', [])->assertStatus(201);

        $resp = $this->withHeaders($this->drawHeaders($deviceId))->getJson('/api/me')->assertOk();
        $this->assertSame(3, $resp->json('max_deep_reads_per_draw'));
        $this->assertNotNull($resp->json('today_draw'));
    }
}
