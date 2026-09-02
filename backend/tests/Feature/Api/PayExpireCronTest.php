<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * BE-PAY-EXPIRE (t_bbfff19b, QA-DONATE-QR ghi nhận #6) — DB không còn row pending cô đơn.
 *
 * Bệnh cũ: FE timer 300s tự expire UI-side nhưng BE KHÔNG bao giờ transit
 * pending→expired (chỉ webhook sai tiền mới expire) → dump 9 rows hết hạn trên
 * UI vẫn pending trong DB. Nay có cron `payments:expire-pending` đọc ngưỡng
 * TTL + cờ bật/tắt từ config('project.pay') — CẤM hardcode (lệ CONFIG-PROJECT 02/09).
 *
 * Bất biến tiền giữ nguyên: chỉ đơn PENDING quá TTL mới bị động; đơn paid
 * (kể cả paid sát giờ / tạo đã lâu) không bao giờ đổi; race webhook-paid-giữa-cron
 * thắng nhờ điều kiện status='pending' lúc ghi + transitTo 1 chiều.
 *
 * Hợp đồng #8 (AC-3): webhook tiền ĐÚNG đến sau khi BE expire → đơn revive về
 * paid (khách chuyển thật tiền vẫn về + nhận quyền), log cảnh báo. Đây là ngoại
 * lệ có chủ đích của "expired là trạng thái cuối": BE expire chỉ là suy đoán
 * "hết TTL mà gateway chưa báo gì"; khi gateway xác nhận tiền thật, tiền thật
 * thắng suy đoán. Webhook sai tiền vào đơn đã expired → vẫn expired (200 OK).
 */
class PayExpireCronTest extends Be2TestCase
{
    /** Đơn pending mới tinh hoặc giả lập tuổi đời qua created_at. */
    private function pending(\DateTimeInterface|string|null $createdAt = null): Payment
    {
        $p = Payment::query()->create([
            'order_code' => random_int(1_000_000_000_0, 9_999_999_999_9),
            'device_id' => $this->device()->device_id,
            'kind' => 'donate',
            'topic' => null,
            'amount_vnd' => 20000,
            'status' => Payment::ST_PENDING,
            'idempotency_key' => 'exp-'.Str::random(24),
        ]);
        if ($createdAt !== null) {
            // created_at là cột do Eloquent tự đổ — forceFill+saveStillTouching không
            // có, sửa thẳng query builder để không dính auto-timestamp.
            Payment::query()->where('id', $p->id)
                ->update(['created_at' => $createdAt instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($createdAt)->toDateTimeString()
                    : $createdAt]);
            $p->refresh();
        }

        return $p;
    }

    private function runCron(): void
    {
        $this->artisan('payments:expire-pending')->assertSuccessful();
    }

    // ---------- AC-2a: đơn quá TTL → expired ----------

    public function test_don_pending_qua_ttl_been_expired(): void
    {
        // TTL mặc định 600s (FE poll 300s + dự phòng đồng hồ) — đặt tuổi 20 phút.
        $old = $this->pending(now()->subMinutes(20));
        // Border under: mới 1 phút → còn hạn, không được động vào.
        $fresh = $this->pending(now()->subMinute());

        $this->runCron();

        $this->assertSame(Payment::ST_EXPIRED, $old->fresh()->status, 'pending quá TTL phải expired ở DB');
        $this->assertSame(Payment::ST_PENDING, $fresh->fresh()->status, 'pending còn hạn giữ nguyên');
    }

    public function test_don_het_han_poll_9_tra_expired_dung_hop_dong_fe(): void
    {
        // Tái hiện đúng ca QA: đơn hết hạn trên UI nhưng #9 vẫn báo pending.
        $p = $this->pending(now()->subMinutes(20));
        $this->runCron();
        $this->cookieFor(\App\Models\Device::find($p->device_id))
            ->getJson("/api/payments/{$p->order_code}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');
    }

    // ---------- AC-1: ngưỡng TTL + cờ nằm trong config, test không phụ thuộc env máy dev ----------

    public function test_ttl_doc_tu_config_khong_hardcode(): void
    {
        $this->assertSame(600, config('project.pay.expire_ttl_seconds'),
            'C-14: ngưỡng expire đơn pending (giây) chốt tại config/project.php');
        $this->assertTrue(config('project.pay.expire_cron_enabled'),
            'C-15: cờ bật/tắt cron expire, default BẬT');

        // Chứng minh reader thật dùng config: TTL giả 60s → đơn 5 phút tuổi phải expire.
        config(['project.pay.expire_ttl_seconds' => 60]);
        $p = $this->pending(now()->subMinutes(5));
        $this->runCron();
        $this->assertSame(Payment::ST_EXPIRED, $p->fresh()->status);
    }

    public function test_co_tat_cron_thi_khong_ai_bien_expired(): void
    {
        config(['project.pay.expire_cron_enabled' => false]);
        $p = $this->pending(now()->subDay());
        $this->runCron();
        $this->assertSame(Payment::ST_PENDING, $p->fresh()->status, 'cờ tắt = command không ghi gì');
    }

    // ---------- AC-2b/2c: đơn paid bất khả xâm phạm (kể cả sát giờ — race case) ----------

    public function test_don_paid_khong_bao_gio_bi_expire(): void
    {
        $paid = $this->pending(now()->subDay());
        $this->postJson("/api/payments/{$paid->order_code}/simulate-paid")
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $paidAt = $paid->fresh()->paid_at->toDateTimeString();

        $this->runCron();

        $fresh = $paid->fresh();
        $this->assertSame(Payment::ST_PAID, $fresh->status);
        $this->assertSame($paidAt, $fresh->paid_at->toDateTimeString(), 'paid_at không được dịch');
    }

    public function test_race_paid_giua_chung_lo_qua_ttl_van_paid(): void
    {
        // Webhook/simulate paid ĐẾN TRƯỚC khi cron ghi nhưng đơn đã quá TTL —
        // đúng kịch bản "khách chuyển giây cuối". Cron phải đọc trạng thái hiện
        // tại lúc ghi (điều kiện WHERE status='pending'), không được dẫm lên paid.
        $a = $this->pending(now()->subMinutes(30));
        $b = $this->pending(now()->subMinutes(30));
        $this->postJson("/api/payments/{$a->order_code}/simulate-paid")->assertOk();

        $this->runCron();

        $this->assertSame(Payment::ST_PAID, $a->fresh()->status, 'paid giây cuối không bị cron nuốt');
        $this->assertSame(Payment::ST_EXPIRED, $b->fresh()->status, 'pending thiệt vẫn expire bình thường');
    }

    public function test_cancelled_refunded_khong_bao_gio_bi_cham(): void
    {
        $c = $this->pending(now()->subDay());
        $c->transitTo(Payment::ST_CANCELLED);
        $this->runCron();
        $this->assertSame(Payment::ST_CANCELLED, $c->fresh()->status);
    }

    // ---------- AC-3: webhook TIỀN ĐÚNG đến sau BE expire → revive paid (tiền thật thắng suy đoán) ----------

    public function test_webhook_dung_tien_den_sau_khi_expire_revive_paid(): void
    {
        $p = $this->pending(now()->subMinutes(20));
        $this->runCron();
        $this->assertSame(Payment::ST_EXPIRED, $p->fresh()->status);

        // payOS #8 báo paid đúng số tiền (khách chuyển thật, chỉ là trễ hơn TTL)
        $secret = 'payexpire-secret-0123456789abcdef';
        config(['payos.webhook_secret' => $secret]);
        $raw = json_encode(['data' => [
            'code' => 'PC2608', 'id' => '7654321', 'orderCode' => (int) $p->order_code,
            'amount' => 20000, 'cancelled' => false, 'payDate' => time(),
            'transactionRef' => 'txn-late-'.Str::random(8), 'channel' => 1,
        ]]);
        $this->call('POST', '/api/webhooks/payos', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYOS_SIGNATURE' => hash_hmac('sha256', $raw, $secret)],
            $raw)->assertOk()->assertExactJson(['error' => ['code' => 'OK']]);

        $fresh = $p->fresh();
        $this->assertSame(Payment::ST_PAID, $fresh->status, 'tiền thật về thì đơn phải paid — quyền khách không mất');
        $this->assertNotNull($fresh->paid_at);
        // Đơn donate: hết expired là hết cô đơn — row về trạng thái tiền thật.
    }

    public function test_webhook_sai_tien_den_sau_khi_expire_van_expired(): void
    {
        $p = $this->pending(now()->subMinutes(20));
        $this->runCron();

        $secret = 'payexpire-secret-0123456789abcdef';
        config(['payos.webhook_secret' => $secret]);
        $raw = json_encode(['data' => [
            'code' => 'PC2608', 'id' => '7654322', 'orderCode' => (int) $p->order_code,
            'amount' => 1000, 'cancelled' => false, 'payDate' => time(),
            'transactionRef' => 'txn-bad-'.Str::random(8), 'channel' => 1,
        ]]);
        $this->call('POST', '/api/webhooks/payos', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYOS_SIGNATURE' => hash_hmac('sha256', $raw, $secret)],
            $raw)->assertOk();

        $this->assertSame(Payment::ST_EXPIRED, $p->fresh()->status, 'sai tiền không được revive');
    }

    // ---------- idempotency + scope ----------

    public function test_cron_chay_lap_idempotent_khong_double_write(): void
    {
        $p = $this->pending(now()->subMinutes(20));
        $this->runCron();
        $first = $p->fresh();
        $this->runCron();
        $second = $p->fresh();
        $this->assertSame(Payment::ST_EXPIRED, $second->status);
        $this->assertSame($first->updated_at->toDateTimeString(), $second->updated_at->toDateTimeString(),
            'lần chạy thứ hai không được ghi đè row đã expired');
    }

    public function test_khong_bao_gio_expire_don_loai_khac_status(): void
    {
        //paid từ trước tới giờ không có đường nào vào cron ngoài pending —
        //thêm một đơn paid tạo trực tiếp (không qua API) để khóa đường tắt.
        $p = $this->pending();
        Payment::query()->where('id', $p->id)->update(['status' => 'paid', 'paid_at' => now(), 'created_at' => now()->subYear()]);
        $this->runCron();
        $this->assertSame('paid', $p->fresh()->status);
    }
}
