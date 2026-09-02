<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * CFG-BE (t_ce2a6834) + CFG-FREEDEEP (t_e825563b) — lớp phòng vệ thứ 2 sau
     * phpunit.xml force="true": production default nay là TRUE (luận sâu free,
     * boss chốt 02/09) nhưng suite VẪN khởi đầu với gate 402 đang BẬT để test
     * phủ cả hai nhánh (bài học 02/09: env máy dev đổi chiều làm test đỏ oan).
     * Test cần bật preview thì tự config(['project.free_deep_preview' => true])
     * trong body (chạy SAU setUp nên vẫn thắng — xem MeFreeDeepTest).
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['project.free_deep_preview' => false]);
    }
}
