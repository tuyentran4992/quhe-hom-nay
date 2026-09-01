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

    /** LUAN-V3 §5.2: timeout RIÊNG bước router danh mục = 10s (Rules không đổi logic cap/cooldown). */
    public const AI_ROUTER_TIMEOUT_SECONDS = 10;

    /** C-05: giá one-time / chủ đề, đơn vị đồng (VND chẵn). */
    public const PRICE_UNLOCK_VND = 29000;

    /** C-06: cap TOÀN CỤC job AI tạo mới trong 60 phút gần nhất. */
    public const AI_GLOBAL_CAP_PER_HOUR = 90;

    /** C-07: khoảng tiền "Lễ tùy tâm" (đồng). */
    public const DONATE_MIN_VND = 1000;
    public const DONATE_MAX_VND = 500000;

    /** C-08: FE tối thiểu cho animation gieo quẻ — BE không enforce. */
    public const MAGIC_SEQUENCE_MS = 1500;

    private function __construct()
    {
    }
}
