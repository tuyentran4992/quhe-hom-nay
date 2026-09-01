<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;

/**
 * F8-BE (t_03424b76, CONTRACT-F8-DONATE C1) — tín hiệu "luận sâu đang FREE".
 * #1 /api/me + #10 /api/me/today trả THÊM top-level `free_deep` = config('preview.free_deep').
 * FE chỉ tin key này, KHÔNG suy ra từ entitlements (device trả 29k cũng đủ 3 topic).
 * entitlements GIỮ NGUYÊN shape mảng (TopicGate `.includes()`) — test khóa cả hai chiều.
 */
class MeFreeDeepTest extends ApiTestCase
{
    /** C1 #1: mặc định flag OFF → payload có free_deep=false (key PHẢI tồn tại). */
    public function test_me_payload_has_free_deep_false_by_default(): void
    {
        config(['preview.free_deep' => false]);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('free_deep', false);
    }

    /** C1 #1: bật preview → true ngay, không cần seed payment. */
    public function test_me_payload_free_deep_true_when_preview_flag_on(): void
    {
        config(['preview.free_deep' => true]);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('free_deep', true);
    }

    /** C1 #10: cùng tín hiệu nằm trong data của /api/me/today. */
    public function test_me_today_has_free_deep_in_data_false_then_true(): void
    {
        config(['preview.free_deep' => false]);
        $this->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.free_deep', false);

        config(['preview.free_deep' => true]);
        $this->getJson('/api/me/today')->assertOk()
            ->assertJsonPath('data.free_deep', true);
    }

    /** C1 ràng buộc ngược: entitlements vẫn là mảng string, không đổi shape khi có key mới. */
    public function test_entitlements_shape_unchanged_string_array(): void
    {
        config(['preview.free_deep' => true]);

        $resp = $this->getJson('/api/me')->assertOk();
        $entitlements = $resp->json('entitlements');
        $this->assertIsArray($entitlements);
        $this->assertContainsOnly('string', $entitlements);
        // flag ON → gate free mở 3 topic (hành vi cũ, bất biến)
        $this->assertSame(\App\Domain\Rules::TOPICS, $entitlements);
    }
}
