<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * BE-0 smoke: khung app boot được + route hoạt động (AC4 dưới dạng test).
 * migrate/schema thuộc test suite riêng của từng card sở hữu migration.
 */
class ScaffoldSmokeTest extends TestCase
{
    public function test_welcome_page_serves(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_health_endpoint_ok(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertExactJson(['data' => ['status' => 'ok']]);
    }

    public function test_up_endpoint_ok(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
