<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * CFG-BE (t_ce2a6834) — lớp phòng vệ thứ 2 sau phpunit.xml force="true":
     * mọi test KHỞI ĐẦU với default nghiệp vụ chuẩn (paywall bật — bài học 02/09:
     * FREE_DEEP_PREVIEW=true trong .env máy dev làm 4 test F8 paywall đỏ oan).
     * Test cần bật preview thì tự config(['project.free_deep_preview' => true])
     * trong body (chạy SAU setUp nên vẫn thắng — xem MeFreeDeepTest).
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['project.free_deep_preview' => false]);
    }
}
