<?php

namespace App\Domain;

/**
 * Hằng số nghiệp vụ — NGUỒN DUY NHẤT (specs/1.mvp/03-api.md §0, bảng C-01..C-08).
 * Pure PHP: cấm import facade/HTTP (kiến trúc hexagonal nhẹ, spec 1.mvp/01 §2).
 * FE/BE đối chiếu một mối — sửa giá trị = sửa spec trước, code sau.
 */
final class Rules
{
    /** C-01: 1 quẻ free / device / ngày dương lịch VN (uq_draws_device_date). */
    public const FREE_DRAW_PER_DAY = 1;

    /** C-02: đúng 3 chủ đề unlock (enum DB). */
    public const TOPICS = ['duyen', 'tai_loc', 'xuat_hanh'];

    /** C-03: cooldown GIỮA 2 lần xin luận sâu của 1 device = 90 GIÂY thời gian. */
    public const AI_COOLDOWN_SECONDS = 90;

    /** C-04: 1 job AI chết sau 120s, tối đa 3 lần thử. */
    public const AI_TIMEOUT_SECONDS = 120;

    public const AI_MAX_ATTEMPTS = 3;

    /**
     * BUG-QHN-100 (QA t_00c3fb07): ngưỡng đòi xác 'running' — worker chết giữa
     * chứng để lại job running vĩnh viễn, FE poll vô hạn. Ngưỡng = hard timeout
     * thật của worker (AI_TIMEOUT_SECONDS+30 = 150s do SIGKILL) + dư 30s đồng hồ
     * → claim sau chỉ cướp khi CHỦ CŨ CHẮC CHẮN chết, không bao giờ 2 worker
     * cùng gọi provider trên 1 job.
     */
    public const AI_ZOMBIE_AFTER_SECONDS = self::AI_TIMEOUT_SECONDS + 30 + 30;

    /**
     * FIX-LUAN-SAU 02/09 (OBS-FILTER t_c146e45a): model thỉnh thoảng sinh chữ dính
     * wordguard (vd "cốt" trong "cốt lõi" — regex \b...c[oố]t\b bắt cả từ ghép nghĩa
     * thông thường) → job fail AI_FILTERED, user thấy "bàn cờ im tiếng" (~1/5 call).
     * Cho phép TỰ SINH LẠI tối đa số lần này trong CÙNG handle() trước khi chịu
     * failed — người dùng không phải bấm nút thử lại.
     */
    public const AI_FILTER_REGENERATIONS = 1;

    /**
     * FIX-LUAN-SAU: lượt đầu hoàn tất trong ngân sách này (giây) thì mới đáng
     * regenerate — user FE poll tối đa 130s (constants.js AI_POLL_MAX_MS), worker
     * timeout 150s (Rules::AI_TIMEOUT_SECONDS+30). 45s để lượt 2 còn cửa về kịp.
     */
    public const AI_FILTER_REGENERATE_BUDGET_S = 45;

    /** LUAN-V3 §5.2: timeout RIÊNG bước router danh mục = 10s (Rules không đổi logic cap/cooldown). */
    public const AI_ROUTER_TIMEOUT_SECONDS = 10;

    /**
     * BUG-V3-1 (card t_05d92158): model router danh mục —single source of truth.
     * BẮT BUỘC non-reasoning phía content: model reasoning (deepseek-v4-flash)
     * nhét toàn bộ output SAU reasoning → budget 8 token bị lý lẽ ăn hết,
     * content='' vĩnh viễn (probe thật 01/09: mt=64/192 vẫn length/cắt).
     * qwen3.6-flash: 4/4 nhãn nguyên văn đúng whitelist ở mt=8, 3.0–5.8s (<10s).
     */
    public const AI_ROUTER_MODEL = 'qwen3.6-flash';

    /**
     * BUG-V3-1: budget router phát động qua constant (trước là magic number 8
     * nằm trong AiBoxClient). Giữ 8 vì model mặc định non-reasoning — nếu vận
     * hành đổi sang model reasoning, PHẢI tăng kèm (≥192 theo probe) hoặc router
     * chết im lặng trở lại; log aibox.router.result (finish=length, route=null)
     * là tín hiệu bắt bệnh.
     */
    public const AI_ROUTER_MAX_TOKENS = 8;

    /** C-05: giá one-time / chủ đề, đơn vị đồng (VND chẵn). */
    public const PRICE_UNLOCK_VND = 29000;

    /** C-06: cap TOÀN CỤC job AI tạo mới trong 60 phút gần nhất. */
    public const AI_GLOBAL_CAP_PER_HOUR = 90;

    /** C-07: khoảng tiền "Lễ tùy tâm" (đồng). */
    public const DONATE_MIN_VND = 1000;

    public const DONATE_MAX_VND = 500000;

    /** C-08: FE tối thiểu cho animation gieo quẻ — BE không enforce. */
    public const MAGIC_SEQUENCE_MS = 1500;

    private function __construct() {}
}
