# SPEC-LUAN-V3 (amended) — Router danh mục từ câu hỏi (4 template T-A/B/C/D, KHONG_THUOC_NAO) — VĂN PHONG GIỮ NGUYÊN V2

- Card: t_c9b6055f (amend bản t_a6a2cba9 @ 056beba theo verdict) · dev-lead · 01/09/2026
- Repo: `/data/quhe-hom-nay` @ main; chuẩn code đối chiếu = **eaced06** (LUAN-V2 merge).
- **VERDICT 01/09 16:0x (anh Tuyền qua supervisor, HEAD-GATE t_91b1cac2):** "thôi giữ cách viết cũ, đọc bản này a ko hiểu gì" → giọng cổ **BỊ LOẠI**. Văn phong = **nguyên V2** (PromptBuilder + Wordguard @ eaced06). LUAN-V3 còn lại = **chỉ phần ROUTER**.
- Kế thừa trực tiếp: SPEC-LUAN-V2 (c1d879a) — mọi điều khoản V2 **hiệu lực nguyên văn**; SPEC này chỉ THÊM router.
- Reader: be-dev, fe-dev, qa-engineer. Gate văn phong ĐÃ ĐÓNG. Gate duy nhất còn lại: preview + 4 bài mẫu router (t_06244320) — không chặn BE khởi công.

> **Luật lịch sử của bản amend này:** mọi khoản bị verdict hủy KHÔNG xóa dòng — đánh dấu `[HỦY theo verdict 01/09 16:0x]` tại chỗ để giữ dấu vết quyết định. Nội dung trong vùng đã hủy **cấm đưa vào code, test, hay bài mẫu**.

## 0. Đối chiếu source — file:line ĐÃ ĐỌC trên main eaced06 (bắt buộc, cấm code theo trí nhớ)

| Nguồn | Dòng đã đọc | Nội dung chốt cho V3 = router |
|---|---|---|
| `backend/app/Domain/PromptBuilder.php` | 33-42 | chữ ký `userPrompt(hex, topic, changingLines, haoTexts, question, rule, bien, dungChan)` — 8 tham số; V3 thêm 1 optional cuối `?string $routedTopic = null` (§3) |
| " | 43-48 | map `$topicLabel`: duyen→'tình duyên', tai_loc→'tài lộc', xuat_hanh→'xuất hành' |
| " | 60-69 | header 8 dòng: `Chủ đề luận sâu: {label}.` (61) · `Quẻ gốc (Hán:…, tên:…)` (62) · `Đại ý:` (63) · `Từ khóa:` (64) · `Luận hôm nay:` (65) · `Góc nhìn sẵn có về {label}:` (66) · hào động (67) · `Luật Biện quẻ (số hào động: N):` (68) — đối tượng chỉnh duy nhất của T-B/T-C |
| " | 72-74 | (a) `Khách đang vướng: "{question}"` — chỉ khi có question |
| " | 79-90 | yaoBlock `Hào động vi%d (%s…) — Hán: %s \| Quốc âm: %s \| Nghĩa: %s` — V3 giữ NGUYÊN (không có luật trích nào mới) |
| " | 93-100 | bienBlock — chỉ khi `can_loi_bien` (case 3/6, D2) — V3 giữ NGUYÊN |
| " | 103-109 | tail `Bố cục BẮT BUỘC 3 phần` (104) + 3 marker `[Hoàn cảnh]` (105) `[Vì sao khuyên vậy]` (106) `[Việc nên làm cụ thể tuần này` (107) + `Giữ 200–400 từ…` (108) — **V2 nguyên văn, GIỮ Y NGUYÊN** ([HỦY theo verdict 01/09 16:0x] bản tail cổ hóa §2) |
| " | 110-112 | (d) question null → `CẤM bịa hoặc đoán hoàn cảnh riêng của khách…` — giữ; T-C viết lại ĐIỀU KIỆN dòng này (§3), không đổi chuỗi gốc |
| " | 117-124 | `freeKey()`: duyen→tinhDuyen, tai_loc→taiLoc, **xuat_hanh→congViec** — chứng cứ xuat_hanh phủ cả công việc (ranh giới §5.1) |
| `backend/app/Domain/Wordguard.php` | 14-17 | `BANNED_PATTERNS` đúng 8 mẫu — V3 KHÔNG đổi |
| " | 23-32 | `SYSTEM_PROMPT` — **V2 nguyên văn, V3 zero diff** ([HỦY theo verdict 01/09 16:0x] khoản sửa dòng 26 giọng cổ + dòng 30 240–420 từng ghi ở bản 056beba) |
| `backend/app/Domain/BianRule.php` | 34-73 | `quiTrinh(changingLines, hexagramId)` 7 case; loi_luan verbatim 49-70 — V3 zero diff |
| `backend/app/Jobs/RunAiBoxJob.php` | 44-88 | handle: claim (47-53) → build rule (66) → positions 4/5 tĩnh (69-71) → bien fetch (72-74) → question normalize (76) → messages system+user (77-86) → `$client->complete` (88) — router cài xen TRƯỚC bước complete (§5.3) |
| " | 90-100 | Wordguard output filter → AI_FILTERED failed, không lưu bài bẩn — giữ nguyên |
| `backend/app/Services/AiBoxClient.php` | 20-56 | `complete(messages)`: model `config('aibox.model')` (32), `temperature 0.7` (34), timeout `Rules::AI_TIMEOUT_SECONDS=120` (29), log `aibox.request.sent` (53) — thêm method thứ 2 `routeTopic()` (§5.2/§5.3) |
| `backend/config/aibox.php` | 9-11 | env `AIBOX_API_KEY / AIBOX_BASE_URL / AIBOX_MODEL` — 1 provider, OpenAI-compatible `/chat/completions` |
| `backend/app/Services/InterpretationService.php` | 53-59 | normalize question (trim→null) · hash D1 `sha256(draw\|topic\|question)` :59 — **KHÔNG ĐỔI** |
| " | 114-117 | question != null → BỎ QUA cache luôn |
| " | 138-143 | `findCacheHit` + `whereNull('question')` :143 |
| `backend/app/Http/Controllers/InterpretationController.php` | 39-50 | validate question: strip control chars, `mb_strlen > 200` → 422 'Câu hỏi tối đa 200 ký tự.' |
| `backend/database/data/hexagrams.json` | 34-54 | `banGoc.quaTu/thoanTruyen/tuongTruyen/dungHao` = object `{han, am, nghia}`; `dungHao` chỉ id1 dòng 50-54 (用九) + id2 dòng 151 (用六) |
| " | 2863-2955 | id31 Trạch Sơn Hàm 咸: `daiCI` 2878, `quaTu` 2896, hào 4 dòng 2935, `luanNay` 2955 — ([HỦY theo verdict 01/09 16:0x] vai trò "nguồn bài mẫu giọng cổ §7"; dữ liệu vẫn dùng được cho bài mẫu router) |
| " | 18 | key JSON `daiCI` (KHÔNG phải dai_ci) — seeder map `daiCI→dai_ci` tại `HexagramSeeder.php:16,79`; `free→free_content` :80; `luanNay→luan_nay` :85 |
| `frontend/src/components/TopicGate.vue` | 12 | props `drawId + topic` — topic bị đóng cứng theo TAB lúc bấm |
| " | 35-40 | D3 chips `QUESTION_SUGGESTIONS[topic]` chỉ điền text, KHÔNG đổi topic API; `normalizedQuestion` (40) |
| " | 60-66 | payload gửi: `topic: props.topic` + `question` (undefined khi rỗng — D4) |
| " | 213-221 | render result + dòng `Bạn hỏi:` (218) — lane render 6c đã merge @ 686a4f1, V3 không đụng |
| `frontend/src/constants.js` | 13 | `TOPIC_LABELS = { duyen:'Tình duyên', tai_loc:'Tài lộc', xuat_hanh:'Xuất hành' }` — ranh giới danh mục cho router prompt §5.1 |
| " | 18-22 | chips 3 topic = kho mẫu câu hỏi thật của khách cho từng danh mục |
| Test | `PromptLuanV2Test.php:84-86` | grep 3 marker `[Hoàn cảnh]/[Vì sao khuyên vậy]/[Việc nên làm cụ thể tuần này` + `Bố cục BẮT BUỘC 3 phần` — marker V3 KHÔNG đổi → **test cũ SỐNG** ([HỦY theo verdict 01/09 16:0x] kế hoạch đổi marker làm đỏ T14) |
| " | :95,99 | grep `Khách đang vướng` hai chiều — giữ nguyên cho T-A/T-B |
| " | :107 | `ưu tiên luận theo tượng hào động` phải KHÔNG còn — giữ nguyên |
| `AiWorkerTest.php:67` | grep `Chủ đề luận sâu: tình duyên` trong prompt — V3 chỉ mất dòng này ở nhánh T-C (job không question → không vỡ) |
| `QuestionCacheTest.php:45-147` | T9-T13 + chống leak question ra payload #6 — hash D1 không đổi thì toàn bộ giữ nguyên |

V3 amended KHÔNG đổi độ dài, KHÔNG đổi giọng, KHÔNG đổi marker → không có test cũ nào vỡ theo kế hoạch; test nào vỡ do router = bug, không phải red được dự kiến.

## 1. Phạm vi (amended — verdict 01/09 16:0x)

**VĂN PHONG = V2 @ eaced06, chuẩn duy nhất.** Marker 3 khối `[Hoàn cảnh] / [Vì sao khuyên vậy] / [Việc nên làm cụ thể tuần này]` + tail `Bố cục BẮT BUỘC 3 phần` + `Giữ 200–400 từ` = PromptBuilder.php:103-109 nguyên văn. `SYSTEM_PROMPT` = Wordguard.php:23-32 nguyên văn. Mọi chếch khỏi hai nguồn này trong diff BE = trả về ở merge gate.

IN (chỉ còn phần router):
1. **Router danh mục**: bước phân loại NHỎ chạy trong worker TRƯỚC bước luận (ADR-V3-01), model `AIBOX_ROUTER_MODEL` fallback `AIBOX_MODEL`, temperature 0, timeout riêng, lỗi → fallback không im lặng (§5).
2. **4 prompt template T-A/T-B/T-C/T-D** (§3) — khác nhau ở DÒNG DANH MỤC (thêm/bớt/tráo label), **toàn bộ wording chọn bằng PromptBuilder V2 hiện hành**; không template nào sửa giọng.
3. `RouterPrompt` hằng số + parse whitelist + `AiBoxClient::routeTopic()` + const timeout trong Rules (§5.2–5.3).

OUT (cấm làm kèm):
- [HỦY theo verdict 01/09 16:0x] toàn bộ mục "V1 — giọng cổ nhân": rewrite `SYSTEM_PROMPT`, đổi tail bố cục, heading Thì–Vị–Biện (hoặc Thời–Vị–Ứng), đổi `200–400`→`240–420`, hard rule cặp Hán–quốc âm–nghĩa, block 3 mốc thời gian. (Từng là IN mục 1+3 bản 056beba.)
- Sửa `BANNED_PATTERNS` (8 mẫu — luật sản phẩm 01 §1), `Rules::AI_GLOBAL_CAP_PER_HOUR`, cap C-06, cache key D1 (§5.3), schema `draws`, luồng payment/entitlement.
- FE đổi tab theo router (§5.5: UI đứng yên, zero code FE cho lane router).
- History/replay bài luận (nợ cũ luan-sau-lifecycle).

## 2. [HỦY theo verdict 01/09 16:0x] V1 — Cổ hóa văn phong — KHÔNG VÀO CODE

> Vùng lịch sử: nguyên văn các khoản §2 bản 056beba để giữ dấu vết quyết định. CẤM implement. Wording thật = V2 (§1).

### 2.1 [HỦY theo verdict 01/09 16:0x] Bất biến cấu trúc + 2 phương án nhãn

3 khối giữ nguyên CHỨC NĂNG và THỨ TỰ: Hoàn cảnh → Vì sao → Việc nên làm. Không thêm/bớt khối. Marker mới là ĐỔI NHÃN, chờ anh Tuyền chốt A/B:

**Phương án A (dev-lead khuyến nghị — đã bị bác)** — cặp "Thì – Vị – Biện", thuần Việt cổ, không Hán tự trong nhãn:

```
[Thì] — thì của chuyện
[Vị] — xét cho ra nhẽ
[Biện] — liệu mà làm
```

Lý do A (dấu vết lịch sử — vùng HỦY, không hiệu lực) chọn chữ Việt thay "Thời–Vị–Ứng" ghi trên card: (1) nhãn Hán tự 3 chữ đơn sẽ khiêu khích đúng cái rule "Hán phải kèm giải" mà ta vừa đặt — bài chứa Hán ngay ở heading; (2) "Thì/Vị/Biện" là bộ ba có thật trong lối bình Dịch Nho gia (hào từ xét thì & vị, quái từ xét biện — cách ứng xử), người nghe hiểu được mà không cần học; (3) FE render không đụng bảng mã. Nếu anh Tuyền vẫn thích "Thời–Vị–Ứng" nguyên bản thì chỉ đổi 3 chuỗi marker, mọi thứ khác y nguyên.

**Phương án B (an toàn zero-red — toàn bộ mục này bị hủy)** — GIỮ 3 marker Việt V2, cổ hóa bằng subtitle:

```
[Hoàn cảnh] — thì của chuyện
[Vì sao khuyên vậy] — xét cho ra nhẽ
[Việc nên làm cụ thể tuần này] — liệu mà làm
```

Cả A và B dùng CHUNG khối lệnh tail (§2.2). Verdict gate t_91b1cac2 01/09 16:0x: HỦY CẢ A LẪN B — marker giữ nguyên V2, không thêm subtitle cổ hóa nào.

### 2.2 [HỦY theo verdict 01/09 16:0x] tail block cổ hóa (thay PromptBuilder.php:103-109)

KHÔNG hiệu lực — khối dưới chỉ là dấu vết (mọi chuỗi trong này cấm vào code/test):

```
Bố cục BẮT BUỘC 3 khối, đúng thứ tự, không thêm khối ngoài 3 khối:
[Thì] — thì của chuyện: khung tình huống quẻ chỉ ra{(topic != null ? ' cho chủ đề '.$topicLabel : '')}.
[Vị] — xét cho ra nhẽ: dẫn lời quẻ/hào từ mà luật Biện quẻ ở trên đã chọn, giải thích vì trời đất xếp thế ấy; câu Hán trích xong PHẢI có đúng 2 dòng liền dưới: dòng quốc âm mở đầu bằng tên hào, dòng nghĩa nôm na.
[Biện] — liệu mà làm: tối đa 3 gạch đầu dòng; MỖI gạch có mốc thời gian cụ thể (today/tomorrow/3-5 ngày tới/tuần này/15 phút mỗi tối) + một hành động đời thường đo được, không nghi lễ, không lời khuyên chung chung kiểu 'cứ bình tĩnh'.
Giữ 240–420 từ (đếm theo ô whitespace, khối trích 3 dòng tính nguyên văn). Kết bài đúng một câu: bài này chỉ mang tính tham khảo giải trí về văn hoá.
```

Diff từng dòng vs V2 ghi ở bản cũ ("3 phần"→"3 khối", `Giữ 200–400 từ` → `Giữ 240–420 từ`, nâng disclaimer vào user prompt): TẤT CẢ HỦY. Tail thật khi code = PromptBuilder.php:103-109 @ eaced06 nguyên văn (trích ở §3).

### 2.3 [HỦY theo verdict 01/09 16:0x] Luật giọng — rewrite Wordguard::SYSTEM_PROMPT dòng 26

```
Giọng người kể: cụ đồ bình giải sách cổ trước ấm trà — chậm rãi, ấm, chữ có cân có đong.
- Xưng hô với khách: 'nhà mình' hoặc 'mình'; KHÔNG 'bạn' kiểu blog, không 'quý khách'.
- Câu văn có vần nhịp, ưu tiên câu ngắn 2 vế đối ('thương thì hỏi, không thì đừng đoán'); không sáo ngữ hiện đại: tối ưu, hiệu suất, kết nối sâu, năng lượng, vũ trụ, mindset, healing.
- Nói chuyện đời bằng vật thật: bữa cơm, ngọn đèn, con đường đêm, ấm trà. KHÔNG nói bằng khái niệm.
- Cổ văn phong CHỈ ở giọng; tuyệt đối không bịa điển cố, không dẫn 'sách xưa nói' mà sách đó không có trong dữ liệu quẻ đưa ra.
```

HỦY TOÀN BỘ. Dòng 26 giữ `Giọng điệu điềm đạm, thiên về suy ngẫm.` như V2.

### 2.4 [HỦY theo verdict 01/09 16:0x] Hard rule "Hán ⇒ cặp quốc âm + nghĩa"

Quy tắc dòng Hán tự + 2 dòng quốc âm/nghĩa và regex golden test tương ứng: HỦY. Dataset `{han, am, nghia}` và yaoBlock format `Hán: … | Quốc âm: … | Nghĩa: …` (PromptBuilder.php:82) vẫn tồn tại NHƯ V2 — chỉ là không có ràng buộc trình bày mới.

### 2.5 [HỦY theo verdict 01/09 16:0x] Block 3 mốc thời gian + hành động đo được

Ràng buộc "mốc thời gian + hành động đo được" trong dòng [Biện]: HỦY. Block việc nên làm giữ đúng dòng V2 :107 (`tối đa 3 gạch đầu dòng — hành động đời thường, không nghi lễ`).

## 3. 4 prompt template router — cơ chế giữ NGUYÊN, wording 100% PromptBuilder V2 @ eaced06

Chữ ký `userPrompt` GIỮ 8 tham số (PromptBuilder.php:33-42); THÊM 1 tham số cuối `?string $routedTopic = null` (null = router không chạy/không tin). `topicLabel` và `freeKey` dựng từ `effectiveTopic = routedTopic ?? topic` — ngoại lệ duy nhất: T-C xóa hẳn 2 dòng danh mục.

Header chung các template = PromptBuilder.php:60-69 (8 dòng) trừ ghi chú; yaoBlock/bienBlock/**tail** giữ NGUYÊN VĂN cho MỌI template (luật BianRule không phụ thuộc danh mục; độ dài vẫn `Giữ 200–400 từ, tiếng Việt, văn phong tham khảo văn hoá.` — PromptBuilder.php:108); dòng (a) `Khách đang vướng:` (:73) có ở T-A/T-B/T-D.

Tail chuẩn mọi template dùng (PromptBuilder.php:104-108 @ eaced06, **nguyên văn V2**):

```
Bố cục BẮT BUỘC 3 phần, đúng thứ tự, không thêm phần ngoài 3 phần:
[Hoàn cảnh] — khung tình huống quẻ chỉ ra cho chủ đề {label}.
[Vì sao khuyên vậy] — dẫn lời quẻ/hào từ mà luật Biện quẻ ở trên đã chọn, giải thích lý do.
[Việc nên làm cụ thể tuần này — tối đa 3 gạch đầu dòng] — hành động đời thường, không nghi lễ.
Giữ 200–400 từ, tiếng Việt, văn phong tham khảo văn hoá.
```

(riêng T-C: dòng `[Hoàn cảnh]` bỏ hậu tố `cho chủ đề {label}` vì không còn trục danh mục — biến điều kiện trong chain, không phải đổi wording.)

| Template | Chạy khi | Diff từng dòng vs prompt V2 hiện hành |
|---|---|---|
| **T-A matched** | question != null, router trả topic == topic tab | **0 dòng khác.** Prompt V2 nguyên văn (header 61-68 + :73 + yao + bien + tail :104-108 + điều kiện dòng :111). Đây là regression baseline. |
| **T-B cross-tab** | router trả topic khác tab (bấm 'tình duyên' hỏi chuyện tiền) | Khác 2 dòng trong header: dòng 61 → `Chủ đề luận sâu: {label_của_routedTopic}.`; dòng 66 → `Góc nhìn sẵn có về {label_của_routedTopic}: {free[freeKey(routedTopic)]}`. +1 dòng mới NGAY SAU :61: `Khách hỏi thẳng điều này — luận đúng chuyện khách hỏi, đừng lái về chủ đề khác.` Các dòng còn lại y nguyên. Field `ai_jobs.topic` KHÔNG đổi (§5.3). |
| **T-C KHONG_THUOC_NAO** | router trả KHONG_THUOC_NAO (hỏi sức khỏe/legal/chuyện không thuộc 3 mục) | **XÓA dòng 61** (`Chủ đề luận sâu…`) và **XÓA dòng 66** (`Góc nhìn sẵn có về…`) — không còn trục danh mục nào để trôi bài/định hướng. THAY bằng 2 dòng: `Việc khách hỏi: "{question}" — hỏi gì đáp nấy.` và `Ràng buộc: mọi điều khuyên phải bám đúng lời quẻ/hào từ đã dẫn ở trên; cấm suy diễn sang chuyện tài chính, tình cảm, xuất hành nếu khách không hỏi.` +1 dòng tròng vào khối (d)-cấm: giữ `CẤM bịa…` của :111 NHƯNG đổi đuôi thành `Chỉ luận đúng điều khách hỏi, bám lời quẻ.` (question CÓ tồn tại nên nhánh :110 điều kiện phải viết lại — điều kiện mới: thêm dòng cấm khi `questionForPrompt === null`, xem bảng §5.4). yaoBlock/bienBlock/`Luật Biện quẻ` y nguyên. **Vẫn đúng 3 khối marker V2** `[Hoàn cảnh]/[Vì sao khuyên vậy]/[Việc nên làm cụ thể tuần này]` (danh sách 3 khối không mất khối nào — ràng buộc bám quẻ/hào đã nằm trong dòng Ràng buộc + `Luật Biện quẻ` :68). |
| **T-D fallback** | router LỖI (mạng/timeout/JSON rác) hoặc trả UNCLEAR-mơ-hồ-nghiêng-về-lỗi | = T-A nguyên văn + 1 dòng mới cuối tail: `Nếu câu hỏi của khách không thuộc chủ đề đã nêu, cứ thẳng thắn đáp đúng câu hỏi ấy theo lời quẻ; không cứng nhắc kéo về chủ đề.` TUYỆT ĐỐI không im lặng, không fail job vì router — bài luận vẫn chạy đủ. |

Câu hỏi UNCLEAR "quá mơ hồ" (đơn tự, 'abc', '???') KHÔNG vào T-D mà về luồng cũ: prompt = T-A nhưng question coi như null → giữ dòng :111 `CẤM bịa…`, FE vẫn hiện `Bạn hỏi:` (TopicGate.vue:218 display-only, không sao). Phân biệt UNCLEAR→luồng-cũ vs lỗi-router→T-D tại §5.4.

Ghi chú lịch sử: bản 056beba đính 2 dòng [Thì]/[Biện] cổ hóa vào bảng này — [HỦY theo verdict 01/09 16:0x]; cột diff trên là bản chốt.

## 4. Wordguard & độ dài — trở về NGUYÊN V2

### 4.1 SYSTEM_PROMPT (Wordguard.php:23-32) — V2 nguyên văn, V3 zero diff

[HỦY theo verdict 01/09 16:0x] khoản bản cũ: sửa dòng 26 "giọng điềm đạm" → khối giọng cụ đồ (§2.3), sửa dòng 30 → `240–420 từ`, diff +7 dòng/~+90 token. Không còn hiệu lực: dòng 26 và dòng 30 giữ nguyên như eaced06.

### 4.2 Độ dài — giữ `Giữ 200–400 từ`

[HỦY theo verdict 01/09 16:0x] khoản đổi 200–400 → 240–420 và const `PromptBuilder::LENGTH_LINE` kéo theo. PromptBuilder.php:108 và Wordguard.php:30 KHÔNG sửa; bất biến "1 chuỗi định nghĩa 1 chỗ" của V2 giữ nguyên hiện trạng.

### 4.3 BANNED_PATTERNS — giữ nguyên 8 mẫu (Wordguard.php:14-17) — khoản này VẪN HIỆU LỰC

Cấm thêm từ vào filter: mỗi mẫu mới = xác suất AI_FILTERED giết bài của khách đã trả 29k tăng. ([HỦY theo verdict 01/09 16:0x] vế "sáo ngữ hiện đại §2.3" vì mục §2.3 đã bị hủy; nếu pilot ghi nhận drift wording, mở card riêng đo tỉ lệ trước/sau.)

## 5. ROUTER — V3 lõi (toàn bộ khoản này GIỮ NGUYÊN từ bản 056beba, không sửa)

### 5.1 Định nghĩa 3 mục cho prompt phân loại (trích nguồn: TopicGate hiện hành)

Ranh giới lấy từ `TOPIC_LABELS` (constants.js:13) + kho chips thật (constants.js:18-22) + ánh xạ free (`xuat_hanh→congViec`, PromptBuilder.php:117-124):

- `duyen` — tình cảm đôi lứa, hôn nhân, người ấy, hợp tan, tình thân trong nhà. Mẫu: 'bao giờ em có người', 'người ấy nghĩ gì về em'.
- `tai_loc` — tiền bạc, của, nợ, đầu tư, mua bán, tài chính cá nhân. Mẫu: 'em có nên đầu tư lúc này', 'khi nào tài chính đỡ hơn'.
- `xuat_hanh` — đi lại, đổi việc, công việc đang làm, khởi sự, chuyện xa gần trong tuần. Mẫu: 'em có nên đổi việc', 'đi xa tuần này có ổn không'.
- `KHONG_THUOC_NAO` — sức khỏe/bệnh tật, học hành/thi cử (không phải đổi việc), pháp lý/tranh chấp, tang hỉ, xem số tổng quát, chuyện vật nuôi, câu không phải việc của mình.
- `UNCLEAR` — không có nội dung hỏi thực: đơn tự, 'abc', '???', '?', toàn chữ thừa lịch sự ('chào', 'cảm ơn').

### 5.2 Prompt router hoàn chỉnh (hằng số mới `App\Domain\RouterPrompt::PROMPT`)

```
Bạn là bộ phân loại câu hỏi cho web Chiêm nghiệm phương Đông.
Ba danh mục và định nghĩa:
- duyen: {định nghĩa §5.1}
- tai_loc: {…}
- xuat_hanh: {…}
KHONG_THUOC_NAO: việc không rơi vào đúng 3 danh mục trên (sức khỏe, học hành, pháp lý, xem số tổng quát…).
UNCLEAR: câu không chứa một việc cụ thể nào để hỏi.
Quy tắc: cân nhắc danh mục theo ĐIỀU VIỆC KHÁCH MUỐN BIẾT, không theo từ khóa bề mặt ('tiền thách cưới' vẫn là duyen? — KHÔNG: 'bao giờ cưới' hỏi việc cưới→duyen; 'cưới tốn bao nhiêu tiền' hỏi túi tiền→tai_loc). Nếu do giữa hai danh mục hoặc do giữa danh mục và KHONG_THUOC_NAO, chọn KHONG_THUOC_NAO.
Chỉ in ra ĐÚNG một từ trong năm khả năng: duyen | tai_loc | xuat_hanh | KHONG_THUOC_NAO | UNCLEAR. Không giải thích, không dấu câu.
Câu hỏi: "{question}"
```

- Model: env MỚI `AIBOX_ROUTER_MODEL` (config/aibox.php thêm key `router_model`, default `''` → fallback về `AIBOX_MODEL` hiện hành — cùng 1 base_url/key, OpenAI-compatible). Temperature `0` (khác 0.7 của bước luận, AiBoxClient.php:34), `max_tokens: 8`, không retry riêng.
- Sửa lỗi đếm ở bản 056beba: prompt ghi "sáu khả năng" nhưng whitelist liệt kê 5 giá trị — bản amended chốt **5** (`test_router_prompt_parse_5_gia_tri`). Đây là bug số học, không phải thay đổi hành vi router.
- Parse: `trim` + uppercase-insensitive match whitelist đúng 5 giá trị §5.2; DƯ THỪA văn bản → coi như UNCLEAR. Không dùng JSON mode (ràng buộc vào tính năng provider, model nhỏ không ổn định bằng plain token).
- Chi phí: input ~180 token + output ~1 token/câu. Câu có question là minority (§5.3 của V2 đo ~70% người KHÔNG hỏi, mục tiêu 800 user/tuần → ~560 router calls/tuần ≈ 105K token input/tuần — nhỏ hơn 1 bài luận, bỏ qua được trong C-06 vì cap đếm theo JOB tạo mới, router không tạo job). Log riêng `aibox.router.result` (BUG-V3-3, card t_05d92158: ghi SAU parse, chứa giá trị route) để AC-1 đếm được từng loại call.

### 5.3 Vị trí kiến trúc: chạy TRONG worker, trước bước luận (không đổi API, không sync HTTP)

Bất biến 01 §2 giữ nguyên: provider chỉ được gọi từ `RunAiBoxJob` (RunAiBoxJob.php:20-24). Luồng handle() mới:

```php
$route = null;
if ($question !== null) {
    $route = (new AiBoxClient)->routeTopic($question);   // method MỚI: cùng client, model router, 10s timeout
    if ($route === 'UNCLEAR') { $questionForPrompt = null; } // §5.4 về luồng cũ
}
$effectiveTopic = ($route !== null && $route !== 'KHONG_THUOC_NAO' && $route !== 'UNCLEAR') ? $route : $job->topic;
// $route === 'KHONG_THUOC_NAO' → template T-C; null/lỗi → T-D
```

- `routeTopic()` là method thứ 2 của `AiBoxClient` (file này hiện 57 dòng — thêm ~25 dòng, dưới ngưỡng 250): timeout RIÊNG 10s (`Rules::AI_ROUTER_TIMEOUT_SECONDS = 10` const mới — Rules không đổi logic cap/cooldown), exception/mạng → trả `null` nội bộ (KHÔNG throw, KHÔNG làm fail job luận).
- Cross-tab KHÔNG đụng entitlement: khách mua tab nào trả tiền tab đó, bài luận đúng thì vẫn nằm trong gate đã mở. `ai_jobs.topic` giữ nguyên giá trị tab — router chỉ đổi prompt content. Ghi thẳng vào docblock: đây là quyết định nghiệp vụ anh Tuyền chốt, không phải bug.
- Cache: job có question không bao giờ ăn cache (InterpretationService.php:114-117) và không làm nguồn (:143) → router result không cần vào cache key. `result_key_hash = sha256(draw|topic|question)` (InterpretationService.php:59) **KHÔNG ĐỔI** — `ai_jobs.result` chứa nguyên văn bài luận, bài đã sinh là bài đã hiển thị, cache không liên quan router.
- Determinism của router vào prompt: `temperature 0` + input chỉ là question đã normalize BẤT BIẾN trong DB (normalize một lần ở controller, lưu DB) → retry của queue worker (attempts ≤ 3, RunAiBoxJob.php:105) chạy lại router cho ra cùng kết quả → cùng template. Nợ ghi nhận (không làm): nếu model router đổi sau khi có bài done, replay lý thuyết lệch — MVP không replay nên chấp nhận, TODO docblock.

### 5.4 Cạnh: 'quá mơ hồ' vs lỗi router — 2 cửa khác nhau

| Đầu vào router | Kết quả | Template | Dòng hoàn cảnh |
|---|---|---|---|
| question rõ, khớp tab | duyen/tai_loc/xuat_hanh == topic | T-A | có `Khách đang vướng:` |
| question rõ, lệch tab | route != topic | T-B | có |
| hỏi việc khác 3 mục | KHONG_THUOC_NAO | T-C | xóa 2 dòng danh mục, thế bằng `Việc khách hỏi: "…"` |
| question mơ hồ | UNCLEAR | luồng cũ = T-A với `questionForPrompt=null` → có dòng :111 CẤM bịa | câu hỏi vẫn lưu DB, FE vẫn `Bạn hỏi:` |
| mạng/timeout/parse rác | null (lỗi) | T-D | có `Khách đang vướng:` + dòng tự xử cuối tail |

### 5.5 FE

KHÔNG đổi tab, không đổi payload (TopicGate.vue:60-66 gửi topic của tab như cũ). `Bạn hỏi:` giữ nguyên (§7.4.4 bugfix 12f28e3). **V3 amended không có lane FE nào**: marker 3 khối giữ nguyên V2 nên lane render 6c (@ 686a4f1) không phải đụng. ([HỦY theo verdict 01/09 16:0x] nhánh "nếu A, FE + QA đổi grep `[Hoàn cảnh]`→`[Thì]`".)

## 6. Quyết định kiến trúc (dev-lead chốt, ghi ADR-inline)

1. **ADR-V3-01 — Router là bước trong worker, không phải endpoint HTTP mới.** Trade-off: +1 provider call trong 1 job (latency giờ đỉnh +≤10s) đổi lấy 0 surface API mới, 0 migration, entitlement sạch. Đảo ngược được: tách sang service đồng bộ nếu về sau cần hiển thị route trước khi poll. temp 0, `AIBOX_ROUTER_MODEL` fallback `AIBOX_MODEL`, cache key D1 không đổi, lỗi → fallback T-D không im lặng.
2. [HỦY theo verdict 01/09 16:0x] **ADR-V3-02 cũ** ("Cổ hóa = tầng prompt + hằng số chuỗi" — delta gồm Wordguard): bản amended thay bằng:
   **ADR-V3-02' — Delta V3 đóng trong 4 file:** `PromptBuilder.php` (tham số `$routedTopic` + 2 dòng T-B/T-C + điều kiện dòng cấm), `AiBoxClient.php` (`routeTopic`), `Domain/RouterPrompt.php` (hằng số mới), `config/aibox.php` + `Rules.php` (router_model + timeout const). **`Wordguard.php`: zero diff.** `BianRule`, `Luan`, `hexagrams.json`, cache, hash: zero diff. Toàn bộ văn phong nằm ngoài phạm vi V3.
3. [HỦY theo verdict 01/09 16:0x] **ADR-V3-03 cũ** ("kiểm chất lượng giọng bằng golden test + mẫu duyệt mắt") — không còn thay đổi giọng nào để kiểm; QA chỉ chấm hành vi router (§8).
4. Prompt layout bất biến: mọi chuỗi instruction mới đặt trong 2 hằng số (RouterPrompt, PromptBuilder) — file nào quá 250 dòng thì tách `PromptBuilderSections` (PromptBuilder hiện 127 dòng, còn xa ngưỡng).
5. `userPrompt` thêm tham số cuối optional → chữ ký tương thích ngược (mọi call cũ 8 tham số vẫn hợp lệ).

## 7. Bài mẫu — [HỦY theo verdict 01/09 16:0x] bài giọng cổ; chuẩn mới = 4 bài mẫu router

Bài mẫu giọng cổ `docs/specs/SPEC-LUAN-V3-mau.md` (case T-B quẻ id31) bị anh Tuyền loại ở HEAD-GATE — file được đánh dấu `[BỊ LOẠI 01/09 — lưu tham khảo, không phải chuẩn]` ở đầu file, không xóa. Chuẩn duyệt mắt MỚI: **4 bài mẫu router** (mỗi nhánh T-A/B/C/D một bài, văn phong V2) trên card preview t_06244320. Case id31 Hàm hào 4 (hexagrams.json:2878/2896/2935/2955) vẫn là dữ liệu tham chiếu tốt cho bài T-B mới.

## 8. Test TDD — danh sách RED trước (be-dev viết đỏ → xanh; nộp [REVIEW] thiếu test = trả về)

**Test cũ: KHÔNG có đổi màu kế hoạch nào.** [HỦY theo verdict 01/09 16:0x] T14 (red marker) — marker V2 giữ nguyên → `PromptLuanV2Test.php::test_prompt_co_dong_3_phan_marker` (:82-88), `test_prompt_cam_doan_khi_question_trong` (:90-102), `AiWorkerTest.php:67`, toàn bộ `QuestionCacheTest.php` **SỐNG**. Suite V2 (Feature + Unit) phải xanh 100% ở mọi commit V3; đỏ = bug router.

Unit mới (RouterPromptTest / PromptBuilder template — ≥4):
| # | Test | Assertion (toàn bộ dùng chuỗi V2) |
|---|---|---|
| T19 | `test_promptbuilder_routed_topic_doi_label_va_free` | routed 'tai_loc' trên job topic 'duyen': chứa `Chủ đề luận sâu: tài lộc` + free `taiLoc`, KHÔNG chứa `tinhDuyen`; prompt đồng thời vẫn chứa tail V2 `Giữ 200–400 từ` |
| T20 | `test_prompt_khong_thuoc_nao_xoa_2_dong_danh_muc` | T-C: NOT contains `Chủ đề luận sâu`, NOT contains `Góc nhìn sẵn có`, contains `Việc khách hỏi: "…"` + `Ràng buộc:`; still contains 3 marker V2 `[Hoàn cảnh]/[Vì sao khuyên vậy]/[Việc nên làm cụ thể tuần này]` + `Bố cục BẮT BUỘC 3 phần` + `Luật Biện quẻ` (đủ 3 khối, ràng buộc bằng mệnh đề quẻ/hào) |
| T21 | `test_4_template_giu_writing_V2` (thay test giọng văn bản cũ — [HỦY theo verdict 01/09 16:0x]) | MỌI template T-A..T-D chứa `Bố cục BẮT BUỘC 3 phần` + `Giữ 200–400 từ`; T-A KHÔNG chứa bất kỳ dòng mới nào của §3 (0-diff vs V2 — regression baseline); T-B chứa `Khách hỏi thẳng điều này`; không template nào chứa `[Thì]`/`[Vị]`/`[Biện]`/`240–420` (chuỗi đã hủy phải vắng mặt) |
| T22 | `test_router_prompt_parse_5_gia_tri` | helper parse: 'duyen\n', ' DUYEN ', cả 5 từ khóa whitelist → match; 'có lẽ là duyen' → UNCLEAR |

Feature mới (worker mock — ≥4):
| # | Test | Assertion |
|---|---|---|
| T23 | `test_worker_co_question_goi_router_truoc_luan` | Http fake 2 calls: call 1 model router, temperature 0, body chứa đúng question; call 2 luận (nhiệt độ 0.7, model luận); job có question vẫn 1 job, hash D1 không đổi (QuestionCacheTest chạy xanh giữ nguyên) |
| T24 | `test_worker_question_chung_chi_khong_goi_router` | question null → ĐÚNG 1 call provider (luồng V2 nguyên trạng, cost gate AC-1 không đổi màu) |
| T25 | `test_worker_cross_tab_luan_dung_muc_khong_doi_job_topic` | router trả 'tai_loc', DB `ai_jobs.topic` vẫn 'duyen', prompt theo T-B |
| T26 | `test_worker_router_loi_khong_bao_gio_lam_fail_job` | mock call 1 ném ConnectionException → job vẫn done với prompt T-D; attempts không +1 thêm vì router |
| T27 | `test_worker_unclear_ve_luong_cu_cam_bia` | router trả 'UNCLEAR' → prompt chứa `CẤM bịa hoặc đoán hoàn cảnh riêng` + không chứa `Khách đang vướng` |
| T28 | `test_cache_key_khong_doi_du_co_router` | golden: 2 request cùng draw+topic+question → cùng `result_key_hash` (sha256 3 thành phần) |

QA (độc lập, không đỏ hộ): chạy bộ + generate mẫu 20 bài thật chấm 3 mục HÀNH VI ROUTER theo bảng §5.4 (đúng template; T-C không trôi sang danh mục; cache/D1 không đổi). **Không chấm giọng văn** — văn phong là V2, benchmark là chính nó.

## 9. Thứ tự nộp & gate (amended)

1. Gate văn phong ĐÃ ĐÓNG bởi verdict 01/09 16:0x qua t_91b1cac2 — §1 là chốt: wording = V2 eaced06. Gate còn lại duy nhất trước anh Tuyền: preview + 4 bài mẫu router (t_06244320) — chạy song song, không chặn BE.
2. be-dev branch `card/<id>`: RouterPrompt+parse (T22) → AiBoxClient::routeTopic+Rules const (T23/T24/T26) → PromptBuilder effectiveTopic+4 template (T19-T21, T25, T27) → [REVIEW] kèm `php artisan test` log (suite V2 phải còn xanh toàn tập). ([HỦY theo verdict 01/09 16:0x] bước "Wordguard/PromptBuilder tail+length".)
3. fe-dev: không có lane V3 (§5.5). TEST-FIELDS.md không đổi.
4. dev-lead merge BE. Gate: TDD-first, 4 trục + thị giác khối bài, thiếu test = trả về; diff đụng `Wordguard.php` hoặc chuỗi tail/độ dài V2 = trả về tức khắc.
5. Cấm đụng: `BANNED_PATTERNS`, `SYSTEM_PROMPT`, tail 3 phần + `200–400 từ`, `Rules::AI_GLOBAL_CAP_PER_HOUR`, hash D1, `findCacheHit`, schema.

Estimate card amend: 15' — amend tại chỗ trên bản 056beba, không viết lại từ đầu; mọi khoản hủy có marker, mọi khoản router/đối chiếu file:line còn đúng được giữ nguyên.
