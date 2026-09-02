<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureDeviceSession;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * BE-2 — 05-testplan F8 (payments stub) + #7 idempotency + #7b→#9→#5 chuỗi tiền thật:
 * tạo đơn → simulate paid → poll #9 → #5 hết 402 (hết tiền là hết quyền, không vòng sau).
 */
class PaymentTest extends Be2TestCase
{
    private function createPayload(string $kind = 'unlock', ?string $topic = 'duyen', ?int $amount = null, ?string $key = null): array
    {
        return array_filter([
            'kind' => $kind,
            'topic' => $topic,
            'amount_vnd' => $amount,
            'idempotency_key' => $key ?? 'pay-'.Str::random(16),
        ], fn ($v) => $v !== null);
    }

    public function test_f7_create_unlock_201_dung_9_field_contract_va_price_ghi_de(): void
    {
        $d = $this->device();
        $res = $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload(amount: 999999))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount_vnd', 29000) // client gửi sai -> server ghi đè giá chốt
            ->assertJsonPath('data.stub', true)
            ->assertJsonPath('data.kind', 'unlock')
            ->assertJsonPath('data.topic', 'duyen');
        $res->assertJsonStructure(['data' => [
            'order_code', 'kind', 'topic', 'amount_vnd', 'status',
            'qr_data', 'confirm_url', 'checkout_url', 'stub', 'expires_at',
        ]]);
        // expires_at RFC3339 UTC
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $res->json('data.expires_at'));
    }

    public function test_f7_qr_data_content_ma_trung_tinh_QH_order_code_ca_2_kind(): void
    {
        // Mockup V2 (boss duyệt 02/09): nội dung CK = QH<order_code>, CẤM tên app.
        // FE parseVietQr theo segment → giữ nguyên format vietqr/action/qr/{bin}/{account}/{amount}/{content}.
        foreach ([['unlock', 'duyen', null, 29000], ['donate', null, 50000, 50000]] as [$kind, $topic, $amount, $expected]) {
            $d = $this->device();
            $res = $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload($kind, $topic, $amount))->assertCreated();
            $qr = (string) $res->json('data.qr_data');
            $order = (int) $res->json('data.order_code');
            $segments = explode('/', $qr);
            $this->assertSame('vietqr', $segments[0]);
            $this->assertSame('action', $segments[1]);
            $this->assertSame('qr', $segments[2]);
            $this->assertSame('970436', $segments[3]);
            $this->assertSame('stub'.$order, $segments[4]);
            $this->assertSame((string) $expected, $segments[5]);
            // segment content: đúng mã trung tính, không dấu, không '+', không token tên app
            $this->assertSame('QH'.$order, $segments[6]);
            $this->assertStringNotContainsString('Qu+Hom+Nay', $qr);
            $this->assertStringNotContainsString('+', end($segments));
        }
    }

    public function test_f7_idempotency_same_key_same_body_200_khoc_body_409(): void
    {
        $d = $this->device();
        $key = 'pay-same-000001';
        $first = $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload(key: $key))->assertCreated();
        $again = $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload(key: $key))
            ->assertOk()->assertJsonPath('data.order_code', $first->json('data.order_code'));
        self::assertNotEquals($first->status(), $again->status()); // 201 vs 200 phân biệt created/replay

        // same key, khác topic -> 409
        $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload(topic: 'tai_loc', key: $key))
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        // key đụng đơn device khác: uq_payments_idem toàn cục theo 02-db §6 -> 409, không replay cross-device
        $this->cookieFor($this->device())->postJson('/api/payments/create', $this->createPayload(key: $key))
            ->assertStatus(409);
    }

    public function test_f7_donate_trong_c07_unlock_khong_dc_gui_tien(): void
    {
        $d = $this->device();
        $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload('donate', null, 50000))->assertCreated();
        $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload('donate', null, 500))
            ->assertStatus(422)->assertJsonPath('error.details.errors.amount_vnd.0', 'Lễ tùy tâm trong khoảng 1.000–500.000đ (C-07).');
        $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload('unlock', 'duyen', null, 'pay-u-00000001'))
            ->assertCreated(); // unlock: không có amount vẫn hợp lệ (server tự 29k)
    }

    public function test_f8_paid_bat_quyen_unlock_va_poll_anh_ton_tai(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);

        // chưa tiền -> 402
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'f8-pre-00001',
        ])->assertStatus(402)->assertJsonPath('error.code', 'UNLOCK_REQUIRED');

        // mua -> simulate paid (webhook stub) -> #9 paid -> #5 thông gate
        $order = $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload())->json('data.order_code');
        $this->cookieFor($d)->postJson("/api/payments/{$order}/simulate-paid")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->cookieFor($d)->getJson("/api/payments/{$order}/status")
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.paid_at', fn ($v) => is_string($v));

        // repeat simulate không phá trạng thái (1 chiều, log) — paid giữ nguyên paid_at gốc
        $paidAt = (string) Payment::query()->where('order_code', $order)->value('paid_at');
        $this->cookieFor($d)->postJson("/api/payments/{$order}/simulate-paid");
        $this->assertSame($paidAt, (string) Payment::query()->where('order_code', $order)->value('paid_at'));

        $this->fakeAi($this->cleanMd);
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'f8-post-00001',
        ])->assertStatus(202);
        // topic khác chưa mua vẫn 402 — paid chỉ mở đúng topic
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'tai_loc', 'idempotency_key' => 'f8-post-00002',
        ])->assertStatus(402)->assertJsonPath('error.details.topic', 'tai_loc');

        // #9 device khác = 404 (ẩn tồn tại)
        $this->cookieFor($this->device())->getJson("/api/payments/{$order}/status")->assertNotFound();
    }

    public function test_f7b_simulate_chi_co_trong_moi_truong_khong_production(): void
    {
        $d = $this->device();
        $order = $this->cookieFor($d)->postJson('/api/payments/create', $this->createPayload())->json('data.order_code');
        // local/qa OK (setUp mặc định env=testing !== production); route thuộc group
        // EnsureDeviceSession -> client phải có cookie như mọi endpoint #7/#9.
        $this->cookieFor($d)->postJson("/api/payments/{$order}/simulate-paid")->assertOk();
        // production -> 404 như đơn ma, không lộ endpoint tồn tại
        app()->instance('env', 'production');
        $this->cookieFor($d)->postJson("/api/payments/{$order}/simulate-paid")->assertNotFound();
    }

    public function test_khong_co_cookie_thi_middleware_gan_an_device_moi(): void
    {
        // 02-db §8: device là danh tính gốc, request lạ = device ẩn mới (Set-Cookie),
        // KHÔNG phải 401. 401 theo 03-api §0.3 chỉ dành cho webhook/API key.
        $res = $this->postJson('/api/payments/create', $this->createPayload())->assertCreated();
        $res->assertCookie(EnsureDeviceSession::COOKIE);
        // job/device của anon không poll được bằng device khác (ẩn tồn tại §6/§9)
        $this->cookieFor($this->device())
            ->getJson('/api/payments/'.$res->json('data.order_code').'/status')->assertNotFound();
    }
}
