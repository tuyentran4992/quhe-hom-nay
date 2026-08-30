<?php

namespace Tests\Unit\Domain;

use App\Domain\Rules;
use App\Domain\Topic;
use PHPUnit\Framework\TestCase;

/**
 * Khóa bảng C specs/1.mvp/03-api.md §0 — sửa giá trị phải sửa spec trước.
 */
final class RulesTest extends TestCase
{
    public function test_constants_match_spec_table_c(): void
    {
        $this->assertSame(1, Rules::FREE_DRAW_PER_DAY);
        $this->assertSame(['duyen', 'tai_loc', 'xuat_hanh'], Rules::TOPICS);
        $this->assertSame(90, Rules::AI_COOLDOWN_SECONDS, 'C-03 chốt 90 GIÂY, không phải 90 lần');
        $this->assertSame(120, Rules::AI_TIMEOUT_SECONDS);
        $this->assertSame(3, Rules::AI_MAX_ATTEMPTS);
        $this->assertSame(29000, Rules::PRICE_UNLOCK_VND);
        $this->assertSame(90, Rules::AI_GLOBAL_CAP_PER_HOUR);
        $this->assertSame(1000, Rules::DONATE_MIN_VND);
        $this->assertSame(500000, Rules::DONATE_MAX_VND);
        $this->assertSame(1500, Rules::MAGIC_SEQUENCE_MS);
    }

    public function test_topic_enum_is_single_source_for_c02(): void
    {
        $this->assertSame(Rules::TOPICS, Topic::values());
    }
}
