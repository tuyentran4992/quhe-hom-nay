<?php

namespace Tests\Unit\Domain;

use App\Domain\Rules;
use PHPUnit\Framework\TestCase;

/**
 * CFG-BE (t_ce2a6834, boss chốt 02/09) — khóa GIÁ TRỊ CƠ của specs/1.mvp/03-api.md §0
 * tại nguồn kỹ thuật duy nhất: backend/config/project.php.
 *
 * Đổi số trên server = sửa project.php + config:clear; muốn đổi KHẢI NIỆM chốt
 * (29k, 90s...) thì phải sửa bảng C trong spec TRƯỚC rồi mới tới đây — test đỏ
 * nhắc đúng luật đó.
 *
 * Chạy PHPUnit thuần (không Laravel) nên đọc config qua require file — file
 * project.php dùng env() (hàm Laravel) cho đúng 1 khóa free_deep_preview; ở đây
 * require trong môi trường có shim env() bên dưới.
 */
final class ProjectConfigTest extends TestCase
{
    private static array $cfg;

    public static function setUpBeforeClass(): void
    {
        if (! function_exists('env')) {
            // shim tối thiểu cho phpunit Unit suite (bootstrap Laravel không nạp env() global)
            eval('function env($k, $d = null) { $v = $_ENV[$k] ?? getenv($k); return $v === false || $v === null ? $d : $v; }');
        }
        self::$cfg = require __DIR__.'/../../../config/project.php';
    }

    public function test_bang_c_khong_doi(): void
    {
        $c = self::$cfg;
        $this->assertSame(1, $c['draw']['free_per_day'], 'C-01');
        $this->assertSame(['duyen', 'tai_loc', 'xuat_hanh'], Rules::TOPICS, 'C-02');
        $this->assertSame(90, $c['ai']['cooldown_seconds'], 'C-03 chốt 90 GIÂY, không phải 90 lần');
        $this->assertSame(120, $c['ai']['timeout_seconds'], 'C-04 timeout');
        $this->assertSame(3, $c['ai']['max_attempts'], 'C-04 attempts');
        $this->assertSame(29000, $c['price']['unlock_vnd'], 'C-05 giá unlock');
        $this->assertSame(90, $c['ai']['global_cap_per_hour'], 'C-06 cap toàn cục');
        $this->assertSame(1000, $c['donate']['min_vnd'], 'C-07 donate sàn');
        $this->assertSame(500000, $c['donate']['max_vnd'], 'C-07 donate trần');
        $this->assertSame(1500, Rules::MAGIC_SEQUENCE_MS, 'C-08 FE-only');
    }

    public function test_khoa_moi_cfg_be(): void
    {
        $c = self::$cfg;
        $this->assertTrue($c['ai']['lock_one_luan'],
            'lock 1 lượt/quẻ+chủ đề = mặc định nghiệp vụ từ BOSS-GO 02/09 (t_8aa93a01)');
        $this->assertSame(1, $c['ai']['filter_regenerations'], 'FIX-LUAN-SAU 02/09');
        $this->assertSame(45, $c['ai']['filter_regenerate_budget_s'], 'FIX-LUAN-SAU 02/09');
        $this->assertSame('qwen3.6-flash', $c['ai']['router_model'], 'BUG-V3-1 probe 01/09');
        $this->assertSame(8, $c['ai']['router_max_tokens']);
        $this->assertSame(10, $c['ai']['router_timeout_seconds']);
        // QUOTA-N/Q2 (t_1b5a0c23): N lượt luận sau THAT tối đa / quẻ — default 3
        // (boss chốt "worst-case 3 gọi AI/quẻ/ngày"). Đổi N = env, KHÔNG đổi code.
        $this->assertSame(3, $c['ai']['max_deep_reads_per_draw'], 'QUOTA-N default 3');
        // BE-PAY-EXPIRE (t_bbfff19b): C-14/C-15 — TTL cron expire đơn pending.
        // 600s > timer FE 300s là BẤT BIẾN (BE không expire khi khách còn quét QR);
        // muốn hạ phải sửa spec 03-api §7c trước.
        $this->assertSame(600, $c['pay']['expire_ttl_seconds'], 'C-14: TTL expire (giây)');
        $this->assertGreaterThanOrEqual(600, $c['pay']['expire_ttl_seconds'],
            'C-14 phải ≥ 2x FE poll 300s — hạ xuống = cắt quyền khách đang chuyển tiền');
        $this->assertTrue($c['pay']['expire_cron_enabled'], 'C-15: cờ cron default BẬT');
    }

    /**
     * CFG-FREEDEEP (t_e825563b, boss chốt 02/09): DEFAULT NGHIỆP VỤ là TRUE —
     * luận sâu free toàn bộ, chốt bằng ĐỌC NGUỒN tĩnh (env chỉ còn là đường
     * REVERT có chủ đích = false). Không assert runtime trong shell nhiễm env —
     * bài học chính card này.
     */
    public function test_free_deep_preview_mac_dinh_bat_tu_02_09(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../config/project.php');
        $this->assertStringContainsString("env('FREE_DEEP_PREVIEW', true)", $src,
            'default phải là true ngay trong project.php — env false chỉ để revert');
    }

    /**
     * QUOTA-N/Q2 (t_1b5a0c23, D1): N đọc từ ENV MAX_DEEP_READS_PER_DRAW — chốt bằng
     * ĐỌC NGUỒN tĩnh (cùng phong cách test trên) vì Unit suite chạy ngoài Laravel:
     * đặt env=1/5 ngoài shell → ngưỡng đổi theo, không hardcoded 3 trong service.
     */
    public function test_max_deep_reads_doc_env_khong_hardcode(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../config/project.php');
        $this->assertStringContainsString("env('MAX_DEEP_READS_PER_DRAW', 3)", $src,
            'D1: key phải nằm project.php section ai, bọc env default 3');
        $this->assertSame(3, self::$cfg['ai']['max_deep_reads_per_draw']);

        // env đổi → config đọc lại đổi (chứng minh đường env thật sự sống).
        // (int) vì shim env() của Unit suite không cast number-string như Env
        // repository Laravel (production: env('...') tự trả int 5) — service đã
        // (int)-cast tại QuotaService::maxPerDraw() nên cả 2 môi trường an toàn.
        putenv('MAX_DEEP_READS_PER_DRAW=5');
        $_ENV['MAX_DEEP_READS_PER_DRAW'] = '5';
        $cfg5 = require __DIR__.'/../../../config/project.php';
        $this->assertSame(5, (int) $cfg5['ai']['max_deep_reads_per_draw'], 'N=5 qua env');
        putenv('MAX_DEEP_READS_PER_DRAW=1');
        $_ENV['MAX_DEEP_READS_PER_DRAW'] = '1';
        $cfg1 = require __DIR__.'/../../../config/project.php';
        $this->assertSame(1, (int) $cfg1['ai']['max_deep_reads_per_draw'], 'N=1 qua env');
        putenv('MAX_DEEP_READS_PER_DRAW');
        unset($_ENV['MAX_DEEP_READS_PER_DRAW']);
    }
}
