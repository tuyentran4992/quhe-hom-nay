<?php

namespace App\Domain;

/**
 * ENUM CẤU TRÚC nghiệp vụ — phần còn lại sau CFG-BE (t_ce2a6834, boss chốt 02/09).
 *
 * MỌI cơ + SỐ thay đổi được (giá, cooldown, cap, attempts, donate, draw/ngày,
 * cờ...)đã dời về `backend/config/project.php` — đổi giá trị nghiệp vụ = sửa
 * file đó + `php artisan config:clear`, KHÔNG sửa code, KHÔNG đụng class này.
 *
 * Ở lại đây chỉ những gì là HÌNH CẤU TRÚC không phải "số để đổi":
 *  - TOPICS: enum DB (cột topic) — thêm/bớt chủ đề là migration + FE + spec,
 *    không phải việc boss đổi số một chiều.
 *  - MAGIC_SEQUENCE_MS: FE-only (animation gieo quẻ, 04-ui), BE không enforce —
 *    FE đọc từ frontend/src/constants.js; giữ constant này chỉ làm Neo-Check
 *    đối chiếu spec C-08, không có reader backend nào.
 *
 * Pure PHP: cấm import facade/HTTP (kiến trúc hexagonal nhẹ, spec 1.mvp/01 §2) —
 * Rules cũng KHÔNG đọc config(): ai cần số nghiệp vụ thì gọi config('project.*')
 * ngay tại chỗ dùng, giữ class này không phụ thuộc framework.
 */
final class Rules
{
    /** C-02: đúng 3 chủ đề unlock (enum DB). */
    public const TOPICS = ['duyen', 'tai_loc', 'xuat_hanh'];

    /** C-08: FE tối thiểu cho animation gieo quẻ — BE không enforce. */
    public const MAGIC_SEQUENCE_MS = 1500;

    private function __construct() {}
}
