<?php

namespace Tests\Unit\Domain;

use App\Domain\Rules;
use App\Domain\Topic;
use PHPUnit\Framework\TestCase;

/**
 * CFG-BE (t_ce2a6834): Rules chỉ còn ENUM CẤU TRÚC (TOPICS, MAGIC_SEQUENCE_MS).
 * Mọi cơ + SỐ nghiệp vụ khóa ở bảng C đã dời sang config/project.php — chốt
 * giá trị đọc file ProjectConfigTest (và spec 03-api §0 là nguồn đối chiếu).
 */
final class RulesTest extends TestCase
{
    public function test_rules_chi_con_enum_cau_truc(): void
    {
        $this->assertSame(['duyen', 'tai_loc', 'xuat_hanh'], Rules::TOPICS);
        $this->assertSame(1500, Rules::MAGIC_SEQUENCE_MS, 'FE-only, BE không enforce');

        // Anti-corruption: số nghiệp vụ quay lại constant = sai thiết kế CFG-BE
        $consts = (new \ReflectionClass(Rules::class))->getConstants();
        $this->assertSame(['TOPICS', 'MAGIC_SEQUENCE_MS'], array_keys($consts),
            'Rules phải đúng 2 constant cấu trúc; thêm số = phải vào project.php');
    }

    public function test_topic_enum_is_single_source_for_c02(): void
    {
        $this->assertSame(Rules::TOPICS, Topic::values());
    }
}
