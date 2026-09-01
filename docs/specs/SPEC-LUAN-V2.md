# SPEC-LUAN-V2 — Luật Biện quẻ 7 case + câu hỏi khách + cache key + bố cục 3 phần

- Card: t_125de428 · dev-lead soạn · CEO chốt quyết định (D1–D4) · anh Tuyền đã duyệt hướng (01/09, supervisor note: §4bis ghi rõ CẤM biến case 0/1/2/4/5 — chỉ mở 3+6)
- Repo: `/data/quhe-hom-nay` @ main `7a9e9e9` · bản trong repo: `docs/specs/SPEC-LUAN-V2.md`
- Reader: be-dev (card t_c86f3954), fe-dev (t_b13fd2b9), qa-engineer (t_a79668ec). SPEC này CHỐT — code không cần hỏi lại; hỏi lại = bị trả về.

## 0. CORRECTION so với thẻ card (đọc trước, kẻo code sai đường)

Endpoint thật là **`POST /api/ai/interpretations`** (routes/api.php:34, prefix `/ai`), KHÔNG phải `/api/interpretations` như card CEO ghi tắt. Mọi ví dụ curl/test dưới đây dùng đường thật.

## 1. Phạm vi & không phạm vi

IN:
1. Trường `question` end-to-end: API → DB → cache key → prompt.
2. `App\Domain\BianRule` — luật Biện quẻ Chu Hy 7 case, hàm thuần.
3. Nối biến vào prompt CÓ KIỂM SOÁT (§4bis mở có điều kiện — chỉ case 3 + 6).
4. Bố cục bài luận 3 phần cố định trong prompt + điều CẤM bịa hoàn cảnh.
5. Cache AC-2 siết: chỉ job `question NULL` được ăn cache và được làm nguồn cache.

OUT (card khác, cấm làm kèm):
- UI câu hỏi trên S-detail (fe-dev chỉ làm tối thiểu theo §7.3; màn đẹp đã có lane riêng).
- History trả bài luận (luan-sau-lifecycle.md đã ghi nợ, không phải việc card này).
- Bất kỳ thay đổi nào ở PayOS/donate/wording patterns của Wordguard (chỉ THÊM instruction, không sửa BANNED_PATTERNS).

## 2. QUYẾT ĐỊNH CEO ĐÃ CHỐT (D1–D4 — không đổi, đã anh Tuyền duyệt)

| # | Quyết định |
|---|---|
| D1 | Cache key = hexagram × topic × question-rỗng. Người hỏi KHÔNG ăn cache, KHÔNG làm nguồn cache. `result_key_hash = sha256(draw_id|topic|question)`. |
| D2 | Nội §4bis có kiểm soát: quẻ biến vào prompt CHỈ case 3 + case 6. Các case còn lại (0,1,2,4,5) VẪN CẤM nội dung biến — giữ nguyên test chống leak. |
| D3 | FE chip = gói gợi ý text điền vào ô question, KHÔNG đổi enum topic API. |
| D4 | Question rỗng → FE không gửi field (không gửi chuỗi rỗng, không gửi null). |

## 3. Luật Biện quẻ — 7 case (Chu Hy, Biện luận pháp)

### 3.1 Hàm thuần phải viết

```php
namespace App\Domain;

final class BianRule
{
    /**
     * @param int[] $changingLines vị trí 1-based (1..6), đã sort asc, duy nhất.
     * @return array{
     *   n_dong: int,               // 0..6
     *   chu_tich: ?int,            // hào đứng tên làm chủ; null = không luận theo hào đơn
     *   chu_tich_vi_tri: string,   // 'dong'|'tinh'|'trên'|'dưới'|'' — giải thích trong prompt
     *   can_quese_goc: bool,       // quẻ từ GỐC vào prompt
     *   can_loi_bien: bool,        // quẻ biến (nội dung BIẾN) vào prompt — D2: chỉ case 3+6
     *   loi_luan: string           // chỉ dẫn chọn lời, tiếng Việt không dấu, xem 3.2
     * }
     */
    public static function quiTrinh(array $changingLines): array
}
```

Bất biến: pure PHP, không DB/facade (01 §2 — domain không import framework). Mảng ngoài khoảng 1..6 hoặc trùng nhau → `InvalidArgumentException`. 2 nguyên tắc suy ra mọi case: (1) động ít theo động, động nhiều theo tĩnh; (2) đồng hạng thì TRÊN làm chủ khi đếm hào ĐỘNG, DƯỚI làm chủ khi đếm hào TĨNH.

### 3.2 Bảng 7 case đối chiếu dữ liệu mẫu (đã verify bằng HexagramRoller mask XOR trên dataset seed thật, 01/09)

| Case | Đổi | Ai luận | chủ tích | can_quese_goc | can_loi_bien | `loi_luan` (verbatim vào prompt) | Data mẫu verify (quẻ Càn id1 lines [1,1,1,1,1,1]) |
|---|---|---|---|---|---|---|---|
| 0 | không hào động | quẻ từ GỐC | null | true | **false** | `Luận theo quẻ từ quẻ gốc. Không có hào động — không dẫn lời hào nào.` | biến NULL (HexagramRoller: 0 động → bien null). Test cũ `test_khong_hao_dong_bien_null` giữ nguyên. |
| 1 | 1 hào | hào từ hào động | đúng hào đó ('dong') | false | **false** | `Chỉ luận theo hào từ của hào động duy nhất (hào {vi}). KHÔNG dùng quẻ từ, KHÔNG dẫn lời quẻ biến.` | hào 2 động → biến id13 ䷌ Thiên Hỏa Đồng Nhân NHƯNG id13 KHÔNG vào prompt. |
| 2 | 2 hào | cả 2 hào từ, hào TRÊN làm chủ | max(changing) ('trên') | false | **false** | `Luận theo hào từ của CẢ HAI hào động ({vi_thu1}, {vi_thu2}); hào TRÊN (hào {chu_tich}) làm chủ — nặng ký hơn.` | hào 2+5 động → biến id30 Ly Vi Hỏa ䷝ — CẤM vào prompt (D2). |
| 3 | 3 hào | quẻ từ GỐC + BIẾN, gốc chủ — biến ứng | null | **true** | **true** (mở — D2) | `Luận theo quẻ từ cả GỐC và BIẾN: gốc làm chủ (việc đang hỏi), biến làm ứng (chiều hướng kết cục — không phải định sẵn).` | hào 1+2+3 động → biến id12 ䷋ Thiên Địa Bĩ. CẢ 2 quẻ từ vào prompt. |
| 4 | 4 hào | 2 hào TĨNH còn lại, hào DƯỚI làm chủ | min(tĩnh) ('dưới') | false | **false** | `4 hào động — theo luật động nhiều theo tĩnh: luận theo hào từ 2 hào TĨNH ({vi_tinh_1}, {vi_tinh_2}); hào DƯỚI (hào {chu_tich}) làm chủ. KHÔNG dẫn lời quẻ biến.` | hào 1..4 động → biến id20 Quan ䷓ — CẤM vào prompt. |
| 5 | 5 hào | hào TĨNH duy nhất | phần tử còn lại ('tinh') | false | **false** | `5 hào động — luận theo hào từ của hào TĨNH duy nhất (hào {chu_tich}). KHÔNG dẫn lời quẻ biến.` | 5 động trừ hào 6 → tĩnh = hào 6; biến = id43 Quải ䷪ — CẤM vào prompt. |
| 6 | 6 hào | Càn→'quần long vô thủ'; Khôn→dụng lục; còn lại→quẻ từ QUẺ BIẾN | null | **chỉ khi quẻ không phải Càn/Khôn** (quẻ từ lúc này là của BIẾN, không phải gốc — xem 3.3) | **true** (mở — D2) | Càn/Khôn: `Sáu hào đều động — dùng lời DỤNG của quẻ: {han/quoc_am/nghia dungHao}. Đây là lời luận chính, không dùng quẻ từ gốc.` · Quẻ khác: `Sáu hào đều động — luận theo quẻ từ QUẺ BIẾN ({ten_bien}). Quẻ gốc chỉ nêu tên, không luận.` | Càn 6 động → biến id2 Khôn; prompt dùng `ban_goc->dungHao` của id1: 用九：見羣龍無首，吉。/"Dụng cửu: kiến quần long vô thủ, cát."/"Thấy cả đàn rồng mà không ai tranh làm đầu — tốt." (verify trong hexagrams.json). Khôn 6 động → biến id1 Càn, dùng dụng lục 用六：利永貞。 |

KHÔNG có case "vừa gốc vừa biến" nào ngoài case 3 và 6. Case 6 Càn/Khôn: `can_quese_goc=false` và `can_loi_bien=true` nhưng nội dung biến được TRUYỀN là lời dụng hào, không phải quẻ từ quẻ biến — see 3.3.

### 3.3 Nguồn dữ liệu lời dụng (pitfall — đừng code theo trí nhớ)

Bảng `hexagram_hao_texts` chỉ có vi 1..6 (PK kép `(hexagram_id, vi)`), **không có hàng dùng九/dụng lục**. Nguồn duy nhất: cột `hexagrams.ban_goc` (JSON) → key `dungHao` = `{han, am, nghia}`, CHỈ tồn tại ở id1 (用九 見羣龍無首，吉) và id2 (用六 利永貞). Verify: `grep 用九 backend/database/data/hexagrams.json` → dòng 51; `用六` → dòng 151.

- `Luan::haoTextsForDraw()` KHÔNG đổi — nó vẫn chỉ trả 6 hào thường. Case 6 Càn/Khôn đọc `ban_goc['dungHao']` từ row hexagram (bổ sung helper `Luan::dungHaoFor(int $hexagramId): ?array` parse JSON, null nếu quẻ thường).
- Quẻ thường 6 động: cần quẻ từ quẻ BIẾN → fetch `Hexagram::find($draw->bien_hexagram_id)` — đây là LẦN ĐẦU TIÊN `bien_hexagram_id` được đọc ra ngoài nội bộ DB, hợp lệ theo D2.

## 4. `question` — schema end-to-end

### 4.1 API `POST /api/ai/interpretations` (contract — fe+qa đọc dòng này)

Body hiện tại + field mới:

| field | type | rule |
|---|---|---|
| `draw_id` | int | như cũ |
| `topic` | string | như cũ (C-02, không đổi enum) |
| `idempotency_key` | string(8-64) | như cũ |
| `question` | string, **tùy chọn** | `trim()` trước khi dùng; tối đa **200 ký tự SAU trim** (đếm unicode — `mb_strlen`); chuỗi rỗng hoặc whitespace-only → **null**; thiếu field → null. Vi phạm độ dài → 422 validation `{"question":["Câu hỏi tối đa 200 ký tự."]}`. |

- Response 202/200 + payload #6 poll: KHÔNG đổi shape (question không lọt ra API — ẩn với device khác, cùng nguyên tắc F7).
- Idempotency: `hash = hash('sha256', $draw->id . '|' . $topic->value . '|' . ($question ?? ''))` tại InterpretationService.php:51. Đổi hash là ĐÚNG: cùng key cũ + body khác (thêm question) → `result_key_hash` lệch → **409 IDEMPOTENCY_CONFLICT** giữ nguyên hành vi. `question` gửi lại trùng khít (sau trim) → same job 200.
- Lưu ý normalize: DB lưu `question` ĐÃ trim (null nếu rỗng). Hash tính trên giá trị đã normalize — hai client gửi `" abc "` và `"abc"` là CÙNG body.

### 4.2 DB migration mới

`database/migrations/2026_09_01_000009_add_question_to_ai_jobs_table.php`:

```php
$table->string('question', 200)->nullable()->after('topic');
```

- varchar(200) NULL — không default, không backfill (job cũ = question NULL = đúng ngữ nghĩa "không hỏi").
- Không index mới: cache lookup đã đi theo `(status, topic, draw_id-in-subquery)`; điều kiện question thêm chỉ là bộ lọc trên tập đã nhỏ.

Model `AiJob`: thêm `'question'` vào `$fillable`. KHÔNG thêm vào `toApi()` (§4.1).

### 4.3 Luồng

Controller validate → Service normalize question (trim→''→null, mb_strlen gate 200) → hash 3 thành phần → INSERT `ai_jobs.question` → cache lookup (§5) → RunAiBoxJob. Job worker: `$job->question` → `Luan`+`PromptBuilder::userPrompt($hex, $topic, $changing, $haoTexts, $question, $rule)` (§6). Draw không đổi schema (question gắn với job luận, không gắn với phiên gieo — 1 draw có thể luận nhiều topic, mỗi lần hỏi khác).

## 5. Cache AC-2 — điều kiện mới, viết chính xác bằng SQL

`findCacheHit` (InterpretationService.php:124) thêm 2 chỗ `question IS NULL` — ứng viên ăn cache là job mới (đã biết question null khi điều kiện bật), còn NGUỒN cache phải lọc trong subquery-less WHERE hiện có:

```php
// $question === null mới được gọi vào đây; có question → BỎ QUA cache luôn (§5.2)
public function findCacheHit(int $hexagramId, string $topic, int $excludeJobId): ?AiJob
{
    return AiJob::query()
        ->where('status', AiJob::ST_DONE)
        ->where('topic', $topic)
        ->whereNull('question')          // << NEW: nguồn cache phải là bài luận không hỏi
        ->where('id', '!=', $excludeJobId)
        ->whereIn('draw_id', function ($q) use ($hexagramId) {
            $q->select('id')->from('draws')->where('hexagram_id', $hexagramId);
        })
        ->latest('finished_at')
        ->first();
}
```

Tương đương SQL:

```sql
SELECT * FROM ai_jobs
WHERE status='done' AND topic=? AND question IS NULL AND id<>?
  AND draw_id IN (SELECT id FROM draws WHERE hexagram_id=?)
ORDER BY finished_at DESC LIMIT 1;
```

### 5.2 Ma trận hành vi (QA lấy dòng này viết test)

| Job mới question | Nguồn cache done question | Kết quả |
|---|---|---|
| NULL | NULL | HIT — copy result, 0 call provider (AC-2 cũ giữ nguyên) |
| NULL | có text | KHÔNG lấy job đó làm nguồn (whereNull loại) — vẫn có thể hit job khác NULL |
| có text | bất kỳ | LUÔN MISS — dispatch provider, 202, bất kể job trước đó |

Pseudo trong `request()`:

```php
$cached = $question === null
    ? $this->findCacheHit($draw->hexagram_id, $topic->value, $job->id)
    : null;
```

### 5.3 Chi phí C-06 — CEO đã tính, KHÔNG đổi code cap

Cap C-06 (InterpretationService.php:88-91) đếm **mọi job tạo mới** trong 60', kể cả cache-hit → siết cache làm call THẬT tăng nhưng không đụng gate:

- Worst-case mục tiêu 800 user/tuần: thêm ~560 call/tuần (mỗi người-hỏi một lần call thật). vẫn DƯỚI cap 90/h vì hỏi có question là minority hành vi.
- Burst giờ đỉnh kịch bản 800-user: ~140 job/h > cap 90 → 429 CHẬM DÒNG, không mất tiền; ở mốc kill-gate 190 user thì an toàn (burst 33/h).
- KHÔNG sửa `Rules::AI_GLOBAL_CAP_PER_HOUR`, KHÔNG sửa đoạn đếm cap.

## 6. PromptBuilder::userPrompt — chữ ký mới + 4 khối

```php
public static function userPrompt(
    array $hex, string $topic, array $changingLines, array $haoTexts = [],
    ?string $question = null, array $rule = null   // rule null → tự tính BianRule::quiTrinh($changingLines)
): string
```

Tham số sau thêm → mọi call cũ (RunAiBoxJob.php:63 cập nhật truyền đủ) không vỡ test cũ nếu rule optional. Giữ nguyên 6 dòng header hiện tại + `$yaoBlock`; THAY dòng `... — ưu tiên luận theo tượng hào động.` (PromptBuilder.php:57) bằng 3 khối:

**(a) Dòng hoàn cảnh** — chỉ khi `mb_strlen($question) > 0`:

```
Khách đang vướng: "{question}"
```

Câu question vào prompt NGUYÊN VĂN đã trim, bọc trong ngoặc kép — không sanitize thêm (Wordguard output filter vẫn đứng cuối chuỗi). Khi question null: KHÔNG có dòng này.

**(b) Khối chỉ dẫn chọn lời** — thay hẳn câu "ưu tiên luận theo hao dong" hiện tại, dựng từ `$rule`:

```
Luật Biện quẻ (số hào động: {n_dong}): {loi_luan}
```

Kèm theo khối dữ liệu được quyền dẫn, đúng D2:
- Case 0: đại ý + từ khóa quẻ gốc (header hiện có). KHÔNG yaoBlock (đang đúng vì `$haoTexts=[]`).
- Case 1,2,4,5: `$yaoBlock` như hiện tại (chỉ các hào được rule chọn — case 4/5 là hào TĨNH, xem 6.1 pitfall). KHÔNG truyền block biến.
- Case 3: thêm block:

```
Quẻ biến (Hán: {han_bien}, tên: {ten_bien}): {dai_ci_bien}
```
- Case 6 Càn/Khôn: block `Lời dụng ({han} | {am} | {nghia})` từ §3.3.
- Case 6 quẻ khác: block quẻ biến như case 3 nhưng chỉ dẫn là LUẬN theo nó, quẻ gốc chỉ nêu tên.

**(c) Lệnh bố cục 3 phần cố định** — thay dòng kết 'Viết bài luận sâu...' hiện tại:

```
Bố cục BẮT BUỘC 3 phần, đúng thứ tự, không thêm phần ngoài 3 phần:
[Hoàn cảnh] — khung tình huống quẻ chỉ ra cho chủ đề {topicLabel}.
[Vì sao khuyên vậy] — dẫn lời quẻ/hào từ mà luật Biện quẻ ở trên đã chọn, giải thích lý do.
[Việc nên làm cụ thể tuần này — tối đa 3 gạch đầu dòng] — hành động đời thường, không nghi lễ.
Giữ 200–400 từ, tiếng Việt, văn phong tham khảo văn hoá.
```

Ba marker `[Hoàn cảnh]` / `[Vì sao khuyên vậy]` / `[Việc nên làm cụ thể tuần này` là HỢP ĐỒNG hiển thị: QA grep 3 marker này trong mock provider prompt; FE render theo marker đã có lane riêng (không phải card này). Disclaimer: system prompt Wordguard::SYSTEM_PROMPT đã nhúng "chỉ mang tính tham khảo giải trí về văn hoá" (dòng 31) → bài ngắn giữ nguyên; nếu bài DÀI tràn Wordguard thì appended bản DÀI chuẩn PA1 — implementation note: giữ instruction kết bài trong system prompt, không lặp ở user prompt (đỡ phung phí token, Wordguard vẫn chặn).

**(d) Điều CẤM khi question rỗng** — thêm vào user prompt khi `$question` null|'':

```
CẤM bịa hoặc đoán hoàn cảnh riêng của khách. Không có câu hỏi nào được nêu — chỉ luận thế quẻ chung cho chủ đề.
```

### 6.1 Pitfall case 4/5 — $haoTexts phải là hào TĨNH

`Luan::haoTextsForDraw()` hiện lọc theo `changing_lines`. Case 4/5 luật chọn lời là hào TĨNH → RunAiBoxJob phải đổi thành:

```php
$rule = BianRule::quiTrinh($draw->changing_lines ?? []);
$viChon = match (true) {
    $rule['n_dong'] <= 3 || $rule['n_dong'] === 6 => $draw->changing_lines ?? [],
    default => array_values(array_diff([1,2,3,4,5,6], $draw->changing_lines ?? [])),
};
$haoTexts = (new Luan())->haoTextsForPositions($draw->hexagram_id, $viChon);
```

(Bổ sung `Luan::haoTextsForPositions(int $hexagramId, array $vi): array` — tách từ hàm cũ, giữ `haoTextsForDraw` delegate cho test cũ không vỡ. Nếu giữ nguyên hàm cũ mà không tách, case 4/5 prompt dẫn sai hào = fail review.)

## 7. FE (card t_b13fd2b9 — tối thiểu theo D3/D4)

1. Ô textarea `question` (maxlength 200, counter nhỏ) cạnh nút "Luận sâu" trong TopicGate.vue — 3 topic dùng chung 1 ô.
2. Chips gợi ý (D3): mỗi topic 3 gói text bấm là điền vào ô; chip KHÔNG đổi topic API.
3. Gửi: question trống sau trim → **KHÔNG gửi key `question`** (D4). Có text → gửi nguyên văn.
4. Kết quả hiển thị: render bài luận theo marker §6(c) thành 3 khối; câu hỏi của khách lặp lại 1 dòng nhỏ "Bạn hỏi: ..." trên đầu bài.
5. Cấm: đưa bất kỳ nội dung biến/quẻ biến nào lên UI (test chống leak cũ vẫn chạy — D2).

## 8. Mục NỢ §4bis + danh sách test phải đổi (be-dev cập nhật cả doc-comment)

Nợ cũ §4bis: "CẤM quẻ biến vào prompt/UI". Nợ mới (ghi vào docblock PromptBuilder + comment card merge): **quẻ biến vào prompt ĐƯỢC PHÉP duy nhất khi BianRule trả `can_loi_bien=true` (case 3, 6 — theo lệnh anh Tuyền P2, D2). Case 0/1/2/4/5 VẪN CẤM tuyệt đối — test chống leak phải giữ nguyên độ nghiêm ngặt: leak khi rule không đòi = fail.**

Test hiện có cần đổi (đã đọc file, số dòng chính xác):

| File | Test | Việc |
|---|---|---|
| `tests/Feature/Api/BienLuuVaPromptTest.php` | `test_que_bien_duoc_luu_dung_va_khong_lo_api` | API payload check giữ nguyên; nếu test này assert "khong lo" trên prompt thì tách: thêm điều kiện chỉ đúng cho case 1 (hào 2 động) — vẫn đúng vì case 1 không mở biến. KHÔNG cần sửa nếu nó chỉ soi payload — xác nhận bằng chạy test. |
| `tests/Feature/Api/BienLuuVaPromptTest.php` | `test_prompt_hao_dong_theu_thu_tu_so_thuong` | gọi `userPrompt` 4 tham số cũ → vẫn chạy được (rule tự tính), case 1 không đổi hành vi → giữ. |
| `tests/Feature/Api/HaoTextsApiTest.php` | `test_f9_draw_payload_has_no_bien_hexagram_leak` | payload #3 — không đổi gì (question không vào draw). Chạy lại confirm xanh. |
| `tests/Feature/Api/AiCacheTest.php` | `test_ac2_cung_que_cung_topic_chi_goi_provider_dung_mot_lan` + 3 test cache khác | nguồn cache job cũ question NULL → vẫn hit; thêm fixture question có text (§9 T11–T12). |
| `tests/Feature/Api/InterpretationGateTest.php` | `test_f6_idempotency_same_key` | thêm nhánh same-key-different-question → 409 (§9 T10). |
| `tests/Feature/Api/AiWorkerTest.php` | các test dựng prompt qua worker | chạy lại — case <3 động prompt không đổi; nếu golden-string assert từng chữ, cập nhật chuỗi theo §6(b). |

## 9. Ca test TDD be-dev phải viết (VIẾT TEST TRƯỚC CODE — red-green; nộp [REVIEW] thiếu test = trả về)

Unit (tests/Unit/Domain/BianRuleTest.php — ≥8):

| # | Test | Assertion chốt |
|---|---|---|
| T1 | `test_0_dong_quyse_tu_goc_khong_bien` | quiTrinh([]) → n_dong=0, chu_tich=null, can_quese_goc=true, can_loi_bien=false |
| T2 | `test_1_dong_hao_tu_hao_dong` | [3] → chu_tich=3, chu_tich_vi_tri='dong', can_loi_bien=false |
| T3 | `test_2_dong_hao_tren_lam_chu` | [2,5] → chu_tich=5, can_loi_bien=false |
| T4 | `test_3_dong_can_goc_va_bien` | [1,2,3] → can_quese_goc=true, can_loi_bien=TRUE (case duy nhất <6 mở) |
| T5 | `test_4_dong_hao_tinh_duoi_lam_chu` | [1,2,3,4] → tĩnh={5,6}, chu_tich=5, can_loi_bien=false |
| T6 | `test_5_dong_hao_tinh_duy_nhat` | [1,2,3,4,5] → chu_tich=6, can_loi_bien=false |
| T7 | `test_6_dong_can_dung_cua_khon_riac_lai_quese_bien` | [1..6] + quẻ id1 → loi_luan chứa 'quần long', Càn/Khôn không dùng quẻ từ; id30 → can_loi_bien=true, loi_luan dẫn id2 Khôn |
| T8 | `test_bian_rule_reject_vi_ngoai_khoang` | [0] hoặc [7] hoặc [2,2] → InvalidArgumentException |

Feature (tests/Feature/Api/ — ≥9):

| # | Test | Assertion |
|---|---|---|
| T9 | `test_question_trim_200_va_rong_hoa_null` | gửi "  " → DB question NULL; gửi 201 ký tự → 422; " abc " và "abc" → cùng hash |
| T10 | `test_hash_idempotency_co_question_409` | same key, body cũ không question → gửi kèm question → 409 IDEMPOTENCY_CONFLICT |
| T11 | `test_cache_chi_an_job_question_null` (nguồn) | job done CÓ question không được làm nguồn: device B question NULL, cùng hexagram+topic → MISS, provider call lần 2 |
| T12 | `test_job_co_question_khong_bao_gio_an_cache` | có job NULL-done trước; job mới question "x" → dispatch, status queued 202 |
| T13 | `test_cache_traditional_ac2_van_xanh_question_null` | giữ nguyên hành vi AC-2 cũ (regression) |
| T14 | `test_prompt_co_dong_3_phan_marker` | capture messages qua mock: chứa `[Hoàn cảnh]`, `[Vì sao khuyên vậy]`, `[Việc nên làm cụ thể tuần này` |
| T15 | `test_prompt_cam_doan_khi_question_trong` | question null → prompt chứa 'CẤM bịa hoặc đoán hoàn cảnh riêng'; có question → chứa `Khách đang vướng: "..."` |
| T16 | `test_prompt_khong_con_cau_uu_tien_luan_theo_hao_dong` | grep chuỗi cũ 'ưu tiên luận theo tượng hào động' phải KHÔNG còn |
| T17 | `test_prompt_case_3_va_6_duoc_quese_bien` | draw hào 1,2,3 động: prompt chứa 'Quẻ biến' + đại ý id12; draw 6 động id1: chứa 'Dụng cửu'/'quần long vô thủ'; (đối chứng) draw hào 2 đơn động case 1: KHÔNG chứa '䷌'/'Đồng Nhân' |
| T18 | `test_prompt_case_4_5_dan_hao_tinh` | 4 động [1,2,3,4]: yaoBlock dẫn hào 5,6 (TĨNH), không dẫn hào động |

Đủ ≥12 theo acceptance. QA (t_a79668ec) chạy nguyên bộ + đối chiếu ma trận §5.2.

## 10. Thứ tự nộp & tiêu chí merge

1. be-dev branch `card/t_c86f3954`: migration → BianRuleTest đỏ → code xanh → PromptBuilder + RunAiBoxJob → InterpretationService/Controller → [REVIEW] kèm UT/FT pass log (`php artisan test`).
2. fe-dev branch `card/t_b13fd2b9`: §7, dựa trên contract §4.1 — KHÔNG chờ BE merge, mock theo §4.1.
3. dev-lead merge BE trước FE (FE chỉ đụng payload). Gate: TDD-first (test đi trước code trong history commit), 4 trục, diff thiếu test = trả về.
4. Cấm đụng: `Rules.php`, `Wordguard::BANNED_PATTERNS`, cap đoạn 88-91, 06 cost.

Estimate đã ghi card: 45' — SPEC nộp đúng hạn theo phê duyệt anh Tuyền.
