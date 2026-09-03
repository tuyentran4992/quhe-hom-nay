<?php

namespace Tests\Feature\Api;

use App\Models\AiJob;
use App\Models\Device;
use App\Models\Draw;
use Illuminate\Support\Str;

/**
 * RL-BE (card t_0e5c0eb9, D1/D2) — GET /api/draws/{draw_id}/luans: danh sách
 * bài đã luận theo quẻ, device-scope, slim+toàn văn gộp 1 khối.
 *
 * Contract chốt (RL-SYN #529): filter done+from_cache=0, dedupe result_key_hash
 * giữa finished_at mới nhất (PHP, 0 query thêm), sort NULL-cuối/DESC/id DESC,
 * label tính sẵn, excerpt ≤120 code-point, draw lạ → 404 ẩn tồn tại (khuôn #6/#5b),
 * #4 history KHÔNG đổi một dòng (A5 chứng minh bằng git diff ở closeout).
 */
class DrawLuansTest extends Be2TestCase
{
    /** Seed thẳng 1 job done (không qua worker — endpoint chỉ đọc DB). */
    private function doneJob(Device $d, Draw $draw, array $over = []): AiJob
    {
        $marker = $over['marker'] ?? 'nội dung '.Str::random(8);
        unset($over['marker']);
        $result = $over['result'] ?? ("Đây là luận giải chuyên sâu dành cho bạn, dựa trên quẻ hôm nay.\n\n---\n\n### [Hoàn cảnh]\n$marker");

        $job = AiJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'device_id' => $d->device_id,
            'draw_id' => $draw->id,
            'topic' => $over['topic'] ?? 'duyen',
            'question' => $over['question'] ?? null,
            'router_category' => $over['router_category'] ?? null,
            'status' => AiJob::ST_QUEUED,
            'requested_at' => now()->subHour(),
            'idempotency_key' => 'lu-'.Str::random(16),
            'result_key_hash' => $over['result_key_hash'] ?? hash('sha256', $draw->id.'|seed|'.Str::random(8)),
        ]);
        $job->forceFill(array_merge([
            'status' => $over['status'] ?? AiJob::ST_DONE,
            'from_cache' => $over['from_cache'] ?? false,
            'result' => $result,
            'finished_at' => $over['finished_at'] ?? now(),
        ], array_diff_key($over, array_flip(['status', 'from_cache', 'result', 'finished_at', 'topic', 'question', 'router_category', 'result_key_hash']))))->save();

        return $job->refresh();
    }

    /** A1 — 3 job done → đủ 3, mới nhất đầu; job from_cache=1 copy result bài nguồn → không bóng ma. */
    public function test_a1_du_3_bai_moi_nhat_dau_khong_dong_nhan_ban(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);

        $old = $this->doneJob($d, $draw, ['marker' => 'bai cu nhat', 'finished_at' => now()->subDays(2)]);
        $mid = $this->doneJob($d, $draw, ['marker' => 'bai giua', 'finished_at' => now()->subDay()]);
        $new = $this->doneJob($d, $draw, ['marker' => 'bai moi nhat', 'finished_at' => now()]);
        // bản sao DU_GIONG: from_cache=1, COPY nguyên result của bài mới → phải bị loại
        $this->doneJob($d, $draw, [
            'result' => $new->result, 'result_key_hash' => $new->result_key_hash,
            'from_cache' => true, 'finished_at' => now(),
        ]);
        // job failed + job queued → không bao giờ hiện
        $this->doneJob($d, $draw, ['status' => AiJob::ST_FAILED, 'result' => null]);
        $this->doneJob($d, $draw, ['status' => AiJob::ST_RUNNING, 'result' => null]);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();

        $res->assertJsonPath('meta.count', 3);
        $ids = array_column($res->json('data'), 'id');
        $this->assertSame([$new->id, $mid->id, $old->id], $ids, 'thứ tự finished_at DESC, mới nhất đầu');

        // toàn văn nằm trong cùng khối payload
        $first = $res->json('data.0');
        $this->assertSame($new->result, $first['result']);
        $this->assertSame($new->job_uuid, $first['job_uuid']);
        $this->assertArrayHasKey('excerpt', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertIsString($first['label']);
    }

    /** A1 (vế sau) — 2 job from_cache=0 TRÙNG result (kiểu seed quhe_uxrqa id3/4) → dedupe còn 1, giữ bản mới nhất. */
    public function test_a1_trung_result_khong_cache_dedupe_giu_moi_nhat(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $shared = "### [Hoàn cảnh]\nBài trùng văn bản từ đường seed.";

        $dup1 = $this->doneJob($d, $draw, ['result' => $shared, 'result_key_hash' => hash('sha256', 'a'), 'finished_at' => now()->subDays(3)]);
        $dup2 = $this->doneJob($d, $draw, ['result' => $shared, 'result_key_hash' => hash('sha256', 'b'), 'finished_at' => now()->subDays(1)]);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();
        $res->assertJsonPath('meta.count', 1);
        $this->assertSame($dup2->id, $res->json('data.0.id'), 'giữ row finished_at mới nhất');
        unset($dup1);
    }

    /** A1 (vế 3) — dedupe theo result khi result_key_hash KHÁC nhau vẫn bắt được (hash là idempotency, không phải nội dung). */
    public function test_a1_hai_hash_khac_cung_result_van_con_1(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $shared = '### [Hoàn cảnh] Cùng văn bản, hash khác nhau (idempotency hash = sha(draw|topic|question)).';

        $this->doneJob($d, $draw, ['result' => $shared, 'result_key_hash' => hash('sha256', 'x|duyen|')]);
        $this->doneJob($d, $draw, ['result' => $shared, 'result_key_hash' => hash('sha256', 'y|duyen|q')]);

        $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk()
            ->assertJsonPath('meta.count', 1);
    }

    /** A2 — question null thật vs có: payload phân biệt được, không chuỗi rỗng giả. */
    public function test_a2_question_null_khong_bien_thanh_chuoi_rong(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $noQ = $this->doneJob($d, $draw, ['question' => null, 'marker' => 'khong hoi']);

        $hasQ = $this->doneJob($d, $draw, ['question' => 'Em có nên nói thẳng không?', 'marker' => 'co hoi']);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();
        $byId = collect($res->json('data'))->keyBy('id');

        $this->assertNull($byId[$noQ->id]['question'], 'null thật, không chuỗi rỗng giả');
        $this->assertArrayHasKey('question', $byId[$noQ->id]);
        $this->assertSame('Em có nên nói thẳng không?', $byId[$hasQ->id]['question']);
    }

    /** A3 — bảng nhãn: router NULL + topic → nhãn topic; cả hai NULL → Điều cần bàn. */
    public function test_a3_label(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $jDuyen = $this->doneJob($d, $draw, ['topic' => 'duyen', 'router_category' => null, 'marker' => 'a']);
        $jXuat = $this->doneJob($d, $draw, ['topic' => 'xuat_hanh', 'router_category' => null, 'marker' => 'b']);
        $jRouter = $this->doneJob($d, $draw, ['topic' => 'tai_loc', 'router_category' => 'di_lich', 'marker' => 'c']);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();
        $labels = collect($res->json('data'))->pluck('label', 'id');

        $this->assertSame('Tình duyên', $labels[$jDuyen->id]);
        $this->assertSame('Xuất hành', $labels[$jXuat->id], 'CEO chốt: KHÔNG để "Công việc · đi lại"');
        $this->assertSame('Đi lại', $labels[$jRouter->id], 'router_category thắng topic');
    }

    /** A3 — cả router + topic đều trống trên HÀNG (topic NOT NULL nên chỉ phủ được router lạ). */
    public function test_a3_router_la_khong_ro_topic(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $j = $this->doneJob($d, $draw, ['router_category' => 'khong_co_trong_enum', 'topic' => 'tai_loc']);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();
        $this->assertSame('Tài lộc', collect($res->json('data'))->firstWhere('id', $j->id)['label']);
    }

    /** A4 — draw của device khác → 404 ẩn tồn tại (khuôn #6, không 403, message không lộ). */
    public function test_a4_thiet_bi_khac_404_an_ton_tai(): void
    {
        $owner = $this->device();
        $draw = $this->drawFor($owner);
        $this->doneJob($owner, $draw, ['marker' => 'bai nguoi khac']);

        $intruder = $this->device();
        $res = $this->cookieFor($intruder)->getJson("/api/draws/{$draw->id}/luans")->assertStatus(404);
        $this->assertSame('NOT_FOUND', $res->json('error.code'));
        $this->assertStringNotContainsString('luans', (string) $res->json('error.message'));

        // draw id không tồn tại cũng y hệt 404 (ẩn cả hai đường)
        $this->cookieFor($intruder)->getJson('/api/draws/999999/luans')->assertStatus(404);
    }

    /** Quẻ của mình nhưng chưa có bài → 200 data=[] + count 0 (FE ẩn nút khi rỗng). */
    public function test_que_chua_co_bai_tra_rong(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);

        $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.count', 0);
    }

    /** Chỉ trả bài CỦA ĐÚNG quẻ yêu cầu (draw khác cùng device không lẫn). */
    public function test_khong_chan_que_khac(): void
    {
        $d = $this->device();
        $draw1 = $this->drawFor($d, 11);
        // ngày khác để né unique device+date
        $draw2 = Draw::query()->create([
            'device_id' => $d->device_id, 'hexagram_id' => 12,
            'drawn_date' => now()->timezone('Asia/Ho_Chi_Minh')->subDay()->format('Y-m-d'),
            'lines_rolled' => [7, 8, 9, 6, 7, 8], 'changing_lines' => [1],
        ]);
        $in = $this->doneJob($d, $draw1, ['marker' => 'trong']);
        $out = $this->doneJob($d, $draw2, ['marker' => 'ngoai']);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw1->id}/luans")->assertOk();
        $ids = array_column($res->json('data'), 'id');
        $this->assertSame([$in->id], $ids);
        $this->assertNotContains($out->id, $ids);
    }

    /** Xếp hạng: finished_at NULL xuống CUỐI, không mất row (D2 an toàn). */
    public function test_sort_null_finished_at_xuong_cuoi(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $has = $this->doneJob($d, $draw, ['marker' => 'co gio', 'finished_at' => now()->subWeek()]);
        $null = $this->doneJob($d, $draw, ['marker' => 'khong gio']);
        $null->forceFill(['finished_at' => null])->save();

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();
        $ids = array_column($res->json('data'), 'id');
        $this->assertSame([$has->id, $null->id], $ids);
        $this->assertNull($res->json('data.1.finished_at'));
    }

    /** A7 privacy: job của draw này nhưng device_id KHÁC (hàng tồn seed) → không lộ sang device owner? Không —
     *  scope là DRAW thuộc device; job ghi device_id của request. Test: job device khác cùng draw (idempotency
     *  khác) → endpoint chỉ đọc theo draw_id, nên vẫn hiện? CHỐT: đọc theo draw_id (draw đã sở hữu-check),
     *  khớp D2 "status=done AND from_cache=0" — spec không thêm điều kiện device trên row.
     *  Test khóa hành vi để QA khỏi lệch: 1 quẻ = danh sách bài của quẻ đó. */
    public function test_job_other_device_same_draw_va_draw_khac_device(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $other = $this->device();
        $j = $this->doneJob($other, $draw, ['marker' => 'row device khac cung draw']);

        $res = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk();
        $this->assertSame([$j->id], array_column($res->json('data'), 'id'));
    }

    /** Contract shape: đủ 9 field, không thừa SELECT * (prompt/prompt_raw không được rò). */
    public function test_payload_dung_9_field(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->doneJob($d, $draw, ['marker' => 'shape']);

        $row = $this->cookieFor($d)->getJson("/api/draws/{$draw->id}/luans")->assertOk()->json('data.0');
        $this->assertSame(
            ['id', 'job_uuid', 'topic', 'router_category', 'label', 'question', 'excerpt', 'finished_at', 'result'],
            array_keys($row)
        );
        $this->assertIsInt($row['id']);
        $this->assertRegExpOrString($row['finished_at']);
    }

    private function assertRegExpOrString(string $iso): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $iso, 'ISO8601 Z như #5b/#6');
    }

    /** draw_id không phải số → 404 (khuôn route, không 500). */
    public function test_draw_id_khong_phai_so_404(): void
    {
        $d = $this->device();
        $this->cookieFor($d)->getJson('/api/draws/abc/luans')->assertStatus(404);
    }

    /** A5 (một phần, self-check): #4 history KHÔNG đổi shape — payload cũ còn nguyên, không có key luans. */
    public function test_a5_history_khong_dong(): void
    {
        $d = $this->device();
        $draw = $this->drawFor($d);
        $this->doneJob($d, $draw, ['marker' => 'van deo lien quan']);

        $res = $this->cookieFor($d)->getJson('/api/draws/history')->assertOk();
        $row = $res->json('data.0');
        $this->assertArrayNotHasKey('luans', (array) $row, '#4 phải byte-parity: không thêm field');
    }
}
