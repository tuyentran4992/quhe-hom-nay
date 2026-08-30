<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * BUGFIX t_a0d9ee0f — 03-api §8 / 05-testplan F8: route #8 POST /api/webhooks/payos
 * chưa từng tồn tại → QA đo được 404, spec bắt 401 UNAUTHENTICATED khi signature sai.
 * Dàn test phủ toàn bộ hành vi §8: verify HMAC raw body → 401; paid đúng tiền;
 * idempotent theo gateway_ref; cancelled; sai tiền → expired vẫn 200; đúng format
 * {"error":{"code":"OK"}}; webhook KHÔNG tạo device (ngoài EnsureDeviceSession).
 */
class PayOsWebhookTest extends Be2TestCase
{
    private const SECRET = 'webhook-test-secret-a0d9-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payos.webhook_secret' => self::SECRET]);
    }

    /** HMAC SHA256 hex của raw body — đúng định nghĩa §8. */
    private function sign(string $raw): string
    {
        return hash_hmac('sha256', $raw, self::SECRET);
    }

    private function postWebhook(string $raw, ?string $signature): \Illuminate\Testing\TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_X_PAYOS_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/webhooks/payos', [], [], [], $server, $raw);
    }

    private function pendingPayment(int $amount = 29000): Payment
    {
        return Payment::query()->create([
            'order_code' => random_int(1_000_000_000_0, 9_999_999_999_9),
            'device_id' => $this->device()->device_id,
            'kind' => 'unlock',
            'topic' => 'duyen',
            'amount_vnd' => $amount,
            'status' => Payment::ST_PENDING,
            'idempotency_key' => 'wh-'.Str::random(24),
        ]);
    }

    private function body(int $orderCode, int $amount, string $ref, bool $cancelled = false): string
    {
        return json_encode(['data' => [
            'code' => 'PC2608', 'id' => '1234567', 'orderCode' => $orderCode,
            'amount' => $amount, 'cancelled' => $cancelled, 'payDate' => null,
            'transactionRef' => $ref, 'channel' => 1,
        ]]);
    }

    // ---------- F8 bản QA curl: signature sai → 401 UNAUTHENTICATED (không phải 404) ----------

    public function test_f8_signature_sai_tra_401_dung_envelope(): void
    {
        $res = $this->postWebhook('{"data":{"orderCode":"x","amount":1,"transactionRef":"y"}}', 'sai')
            ->assertStatus(401);
        $res->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_thieu_header_signature_cung_vi_401(): void
    {
        $this->postWebhook('{}', null)->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_secret_chua_cau_hinh_fail_closed_401_du_chu_ky_dung_thuat_toan(): void
    {
        config(['payos.webhook_secret' => '']);
        $raw = $this->body(1, 1, 'ref');
        // ký bằng rỗng vẫn "khớp" về mặt hàm — fail-closed phải chặn trước khi so
        $this->postWebhook($raw, hash_hmac('sha256', $raw, ''))->assertStatus(401);
    }

    public function test_chu_ky_tinh_sai_body_bi_401(): void
    {
        $p = $this->pendingPayment();
        $this->postWebhook($this->body((int) $p->order_code, 29000, 'ref-1'), $this->sign('other-body'))
            ->assertStatus(401);
        $this->assertSame(Payment::ST_PENDING, $p->fresh()->status);
    }

    // ---------- Đường thành công §8 ----------

    public function test_webhook_dung_paid_gateway_ref_va_200_dung_format_payos(): void
    {
        $p = $this->pendingPayment();
        $raw = $this->body((int) $p->order_code, 29000, 'txn-aaa');
        $res = $this->postWebhook($raw, $this->sign($raw))
            ->assertOk()
            ->assertExactJson(['error' => ['code' => 'OK']]);
        $this->assertStringStartsWith('application/json', (string) $res->headers->get('Content-Type'));

        $fresh = $p->fresh();
        $this->assertSame(Payment::ST_PAID, $fresh->status);
        $this->assertSame('txn-aaa', $fresh->gateway_ref);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_webhook_lap_cung_transactionref_200_ngay_giu_nguyen_paid_at(): void
    {
        $p = $this->pendingPayment();
        $raw = $this->body((int) $p->order_code, 29000, 'txn-dup');
        $sig = $this->sign($raw);
        $this->postWebhook($raw, $sig)->assertOk();
        $paidAt = $p->fresh()->paid_at->toIso8601String();

        // phát lặp thứ hai (payOS retry) → 200, không đổi paid_at
        $this->postWebhook($raw, $sig)->assertOk()->assertExactJson(['error' => ['code' => 'OK']]);
        $fresh = $p->fresh();
        $this->assertSame(Payment::ST_PAID, $fresh->status);
        $this->assertSame($paidAt, $fresh->paid_at->toIso8601String());
    }

    public function test_webhook_amount_khop_giua_thuc_te_paid_vi_29000(): void
    {
        $p = $this->pendingPayment();
        $raw = $this->body((int) $p->order_code, 29000, 'txn-amt');
        $this->postWebhook($raw, $this->sign($raw))->assertOk();
        $this->assertSame(Payment::ST_PAID, $p->fresh()->status);
    }

    public function test_webhook_cancelled_khong_paid_chuyen_cancelled(): void
    {
        $p = $this->pendingPayment();
        $raw = $this->body((int) $p->order_code, 29000, 'txn-cxl', cancelled: true);
        $this->postWebhook($raw, $this->sign($raw))->assertOk()
            ->assertExactJson(['error' => ['code' => 'OK']]);
        $this->assertSame(Payment::ST_CANCELLED, $p->fresh()->status);
    }

    public function test_webhook_sai_so_tien_expired_van_200_ok(): void
    {
        $p = $this->pendingPayment();
        $raw = $this->body((int) $p->order_code, 1000, 'txn-bad-amount');
        $this->postWebhook($raw, $this->sign($raw))->assertOk()
            ->assertExactJson(['error' => ['code' => 'OK']]);
        $this->assertSame(Payment::ST_EXPIRED, $p->fresh()->status);
    }

    public function test_webhook_don_khong_ton_tai_404_khong_do_sang_don_khac(): void
    {
        $p = $this->pendingPayment();
        $raw = $this->body(999_999_999_9, 29000, 'txn-ghost');
        $this->postWebhook($raw, $this->sign($raw))->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertSame(Payment::ST_PENDING, $p->fresh()->status);
    }

    public function test_webhook_khong_phai_json_400_sau_khi_signature_dung(): void
    {
        $raw = 'not-json-at-all';
        $this->postWebhook($raw, $this->sign($raw))->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
    }

    public function test_webhook_thieu_ordercode_bi_400(): void
    {
        $raw = json_encode(['data' => ['amount' => 1, 'transactionRef' => 'r']]);
        $this->postWebhook($raw, $this->sign($raw))->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
    }

    // ---------- Anti-pollution: #8 nằm NGOÀI EnsureDeviceSession ----------

    public function test_webhook_khong_sinh_device_moi(): void
    {
        $before = Device::query()->count();
        $this->postWebhook('{}', 'sai')->assertStatus(401);
        $this->assertSame($before, Device::query()->count(), 'webhook gateway không được tạo device');
    }

    public function test_webhook_paid_mo_duong_402_len_AI_xuyen_toan_trinh(): void
    {
        // Chuỗi tiền thật: #7b-style pending → #8 paid → #5 hết 402 (entitlement đọc từ row paid)
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'ai-wh-0000'.$draw->id,
        ])->assertStatus(402);

        $p = Payment::query()->create([
            'order_code' => random_int(1_000_000_000_0, 9_999_999_999_9),
            'device_id' => $d->device_id, 'kind' => 'unlock', 'topic' => 'duyen',
            'amount_vnd' => 29000, 'status' => Payment::ST_PENDING,
            'idempotency_key' => 'wh-'.Str::random(24),
        ]);
        $raw = $this->body((int) $p->order_code, 29000, 'txn-e2e');
        $this->postWebhook($raw, $this->sign($raw))->assertOk();

        $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'ai-wh-2-'.$draw->id,
        ])->assertStatus(202);
    }
}
