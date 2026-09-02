<?php

namespace Tests\Feature\Api;

use App\Domain\DeviceIdentity;
use App\Http\Middleware\EnsureDeviceSession;
use App\Models\Device;
use App\Models\Draw;
use App\Models\Payment;
use Database\Seeders\HexagramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BE-2 — feature tests 05-testplan F4/F5/F6/F7/F8 + AC-2 cache (Http::fake đếm
 * số lần gọi provider). Queue sync (phpunit.xml) → dispatch chạy inline, worker
 * thật (RunAiBoxJob::handle) được duyệt toàn bộ, chỉ provider là fake.
 */
abstract class Be2TestCase extends TestCase
{
    use RefreshDatabase;

    protected string $cleanMd = "Quẻ hôm nay nhắc mình chậm lại. Duyên không phải thứ săn được, mà là khoảng lặng đủ dài để nghe nhau. Bài viết chỉ mang tính tham khảo giải trí về văn hoá.";

    /** Hàng đợi nội dung provider trả về qua fakeAi()/fakeAiSeq(). */
    protected array $aiQueue = [];

    /**
     * LUAN-V3 (SPEC §5.2/§8) — hàng đợi riêng cho bước ROUTER. Nhận diện call
     * router trong fake: body có `max_tokens` (chỉ router gửi, =8) — KHÔNG
     * phân biệt theo model vì router_model mặc định fallback đúng model luận.
     * Hết hàng đợi → trả 'duyen': khớp tab duyen của mọi test cũ → router ra
     * T-A, prompt y nguyên V2 → các assertion cũ không đổi màu (regression baseline).
     */
    protected array $routerQueue = [];

    protected function setUp(): void
    {
        parent::setUp();
        (new HexagramSeeder())->run();
        // 01 §2: queue DATABASE thật — dispatch CHỈ ghi pending (không inline),
        // worker RunAiBoxJob::handle() được gọi thủ công trong test → exception
        // provider không nổ ngược vào request #5.
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'jobs']);
        // Fake MỘT lần duy nhất: closure đọc hàng đợi nội dung — nhiều lần fakeAi
        // không thể đè stub cũ vì Factory::fake chỉ merge stubCallbacks (pitfall BE-2).
        Http::fake(['*chat/completions' => function ($request) {
            $body = json_decode((string) $request->body(), true) ?: [];
            if (array_key_exists('max_tokens', $body)) { // call ROUTER (LUAN-V3 §5.2, max_tokens=8)
                $next = array_shift($this->routerQueue) ?? 'duyen';
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $next]]]]);
            }
            $next = array_shift($this->aiQueue) ?? $this->cleanMd;
            if ($next instanceof \Throwable) {
                throw $next;
            }
            if (is_int($next)) {
                return Http::response(null, $next);
            }

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $next]]]]);
        }]);
    }

    /** Device mới + cookie header cho mọi request kế tiếp. */
    protected function device(): Device
    {
        return Device::forceCreate([
            'device_id' => DeviceIdentity::generate(),
            'first_seen' => now(),
            'last_seen' => now(),
        ]);
    }

    /**
     * JSON request chỉ gửi cookie khi withCredentials() (prepareCookiesForJsonRequest);
     * unencrypted vì api group không chạy EncryptCookies — cookie thô là đúng bản thật.
     */
    protected function cookieFor(Device $d): static
    {
        return $this->withCredentials()->withUnencryptedCookies([
            EnsureDeviceSession::COOKIE => $d->device_id,
        ]);
    }

    /** Quẻ của device hôm nay (đủ điều kiện #5). */
    protected function drawFor(Device $d, int $hexagramId = 11): Draw
    {
        return Draw::query()->create([
            'device_id' => $d->device_id,
            'hexagram_id' => $hexagramId,
            'drawn_date' => now()->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d'),
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => [2, 4],
        ]);
    }

    /** paid entitlement qua bảng (đường #7+#7b được test riêng ở F8). */
    protected function payUnlock(Device $d, string $topic): void
    {
        Payment::query()->create([
            'order_code' => random_int(1_000_000_000_0, 9_999_999_999_9),
            'device_id' => $d->device_id,
            'kind' => 'unlock',
            'topic' => $topic,
            'amount_vnd' => 29000,
            'status' => Payment::ST_PAID,
            'paid_at' => now(),
            'idempotency_key' => 'seed-'.\Illuminate\Support\Str::random(24),
        ]);
    }

    /**
     * Đặt nội dung provider trả cho lần call KẾ TIẾP (queue FIFO, hết queue → bài sạch).
     *
     * CANH BAO (FIX-LUAN-SAU 02/09, Rules::AI_FILTER_REGENERATIONS=1): noi dung
     * PHAM wordguard tinh gio = HAI luot call — luot 1 ban → RunAiBoxJob tu
     * regenerate, queue rong thi fallback cleanMd trong fake o tren → regen "thanh
     * cong" gia, job DONE thay vi failed AI_FILTERED. Muon test kich ban
     * that bai boc: fakeAi(dirty) HAI lan lien tiep (hoac 1 lan dirty + 1 lan
     * cleanMd neu kich ban la regen thanh cong nhu AiWorkerTest).
     */
    protected function fakeAi(string $content): void
    {
        $this->aiQueue[] = $content;
    }

    /** Lần call kế tiếp trả HTTP lỗi (vd 500) — AI_UPSTREAM. */
    protected function fakeAiStatus(int $status): void
    {
        $this->aiQueue[] = $status;
    }

    /** Lần call kế tiếp ném exception (vd ConnectionException timeout). */
    protected function fakeAiThrow(\Throwable $e): void
    {
        $this->aiQueue[] = $e;
    }
}
