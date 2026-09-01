# SPEC-LUAN-V3 — Cổ hóa văn phong (giữ 3 khối, đổi giọng cổ nhân) + Router danh mục từ câu hỏi (KHONG_THUOC_NAO)

- Card: t_a6a2cba9 · dev-lead soạn · 01/09/2026
- Repo: `/data/quhe-hom-nay` @ main `eaced06` (LUAN-V2 đã merge — điều kiện vào ĐẠT)
- Kế thừa trực tiếp: SPEC-LUAN-V2 (c1d879a) — mọi điều khoản V2 GIỮ NGUYÊN trừ những dòng V3 sửa, ghi từng dòng tại §2/§5.
- Reader: be-dev, fe-dev, qa-engineer. SAU card này: gate t_91b1cac2 (V3-HEAD-GATE) — anh Tuyền duyệt mắt heading + bài mẫu + router TRƯỚC khi lane BE/FE động code. SPEC này CHỐT phần kỹ thuật; phần nhãn 3 khối là DUYỆT MẮT, có 2 phương án A/B.

## 0. Đối chiếu source — file:line ĐÃ ĐỌC trên main eaced06 (bắt buộc, cấm code theo trí nhớ)

| Nguồn | Dòng đã đọc | Nội dung chốt cho V3 |
|---|---|---|
| `backend/app/Domain/PromptBuilder.php` | 33-42 | chữ ký `userPrompt(hex, topic, changingLines, haoTexts, question, rule, bien, dungChan)` — 8 tham số |
| " | 43-48 | map `$topicLabel`: duyen→'tình duyên', tai_loc→'tài lộc', xuat_hanh→'xuất hành' |
| " | 60-69 | header 8 dòng: `Chủ đề luận sâu: {label}.` (61) · `Quẻ gốc (Hán:…, tên:…)` (62) · `Đại ý:` (63) · `Từ khóa:` (64) · `Luận hôm nay:` (65) · `Góc nhìn sẵn có về {label}:` (66) · hào động (67) · `Luật Biện quẻ (số hào động: N):` (68) |
| " | 72-74 | (a) `Khách đang vướng: "{question}"` — chỉ khi có question |
| " | 79-90 | yaoBlock: `Hào động vi%d (%s…) — Hán: %s \| Quốc âm: %s \| Nghĩa: %s` — MẪU ĐÚNG cho rule "Hán phải kèm quốc âm + nghĩa" |
| " | 93-100 | bienBlock — chỉ khi `can_loi_bien` (case 3/6, D2) |
| " | 103-109 | tail: `Bố cục BẮT BUỘC 3 phần` (104) + 3 marker `[Hoàn cảnh]` (105) `[Vì sao khuyên vậy]` (106) `[Việc nên làm cụ thể tuần này` (107) + `Giữ 200–400 từ…` (108) |
| " | 110-112 | (d) question null → `CẤM bịa hoặc đoán hoàn cảnh riêng của khách…` |
| " | 117-124 | `freeKey()`: duyen→tinhDuyen, tai_loc→taiLoc, **xuat_hanh→congViec** — chứng cứ xuat_hanh phủ cả công việc |
| `backend/app/Domain/Wordguard.php` | 14-17 | `BANNED_PATTERNS` đúng 8 mẫu (hòa giải/cúng/giải hạn/bùa/thay đổi vận mệnh/tâm linh/thỉnh/cốt) — V3 KHÔNG đổi |
| " | 23-32 | `SYSTEM_PROMPT`: dòng 26 "giọng điệu điềm đạm", dòng 30 `200–400 từ`, dòng 31 kết bài "chỉ mang tính tham khảo giải trí về văn hoá" — V3 sửa dòng 26+30 (§4) |
| `backend/app/Domain/BianRule.php` | 34-73 | `quiTrinh(changingLines, hexagramId)` 7 case; loi_luan verbatim 49-70 |
| `backend/app/Jobs/RunAiBoxJob.php` | 44-88 | handle: claim (47-53) → build rule (66) → positions 4/5 tĩnh (69-71) → bien fetch (72-74) → question normalize (76) → messages system+user (77-86) → `$client->complete` (88) |
| " | 90-100 | Wordguard output filter → AI_FILTERED failed, không lưu bài bẩn |
| `backend/app/Services/AiBoxClient.php` | 20-56 | `complete(messages)`: model `config('aibox.model')` (32), `temperature 0.7` (34), timeout `Rules::AI_TIMEOUT_SECONDS=120` (29), log `aibox.request.sent` (53) |
| `backend/config/aibox.php` | 9-11 | env `AIBOX_API_KEY / AIBOX_BASE_URL / AIBOX_MODEL` — 1 provider, OpenAI-compatible `/chat/completions` |
| `backend/app/Services/InterpretationService.php` | 53-59 | normalize question (trim→null) · hash D1 `sha256(draw\|topic\|question)` :59 |
| " | 114-117 | question != null → BỎ QUA cache luôn |
| " | 138-143 | `findCacheHit` + `whereNull('question')` :143 |
| `backend/app/Http/Controllers/InterpretationController.php` | 39-50 | validate question: strip control chars, `mb_strlen > 200` → 422 'Câu hỏi tối đa 200 ký tự.' |
| `backend/database/data/hexagrams.json` | 34-54 | `banGoc.quaTu/thoanTruyen/tuongTruyen/dungHao` = object `{han, am, nghia}`; `dungHao` chỉ id1 dòng 50-54 (用九) + id2 dòng 151 (用六) |
| " | 2863-2955 | id31 Trạch Sơn Hàm 咸: `daiCI` 2878, `quaTu` 2896 (咸：亨。利貞。取女吉。 / "Hàm: hanh. Lợi trinh. Thủ nữ cát."), hào 4 `九四：貞吉悔亡，憧憧往來，朋從爾思。` dòng 2935, `luanNay` 2955 — nguồn bài mẫu §7 |
| " | 18 | key JSON `daiCI` (KHÔNG phải dai_ci) — seeder map `daiCI→dai_ci` tại `HexagramSeeder.php:16,79`; `free→free_content` :80; `luanNay→luan_nay` :85 |
| `frontend/src/components/TopicGate.vue` | 12 | props `drawId + topic` — topic bị đóng cứng theo TAB lúc bấm |
| " | 35-40 | D3 chips `QUESTION_SUGGESTIONS[topic]` chỉ điền text, KHÔNG đổi topic API; `normalizedQuestion` (40) |
| " | 60-66 | payload gửi: `topic: props.topic` + `question` (undefined khi rỗng — D4) |
| " | 213-221 | render result: `{{ result }}` thô trong `.prose-quhe.whitespace-pre-wrap` + dòng `Bạn hỏi:` (218) — marker KHÔNG được FE parse trên main (lane render §6c xử lý ở preview) |
| `frontend/src/constants.js` | 13 | `TOPIC_LABELS = { duyen:'Tình duyên', tai_loc:'Tài lộc', xuat_hanh:'Xuất hành' }` — ranh giới danh mục cho router prompt §5.1 |
| " | 18-22 | chips 3 topic = kho mẫu câu hỏi thật của khách cho từng danh mục |
| Test | `PromptLuanV2Test.php:84-86` | grep 3 marker `[Hoàn cảnh]/[Vì sao khuyên vậy]/[Việc nên làm cụ thể tuần này` + `Bố cục BẮT BUỘC 3 phần` — V3 đổi marker = T14 PHẢI red trước |
| " | :95,99 | grep `Khách đang vướng` hai chiều — giữ nguyên cho T-A/T-B |
| " | :107 | `ưu tiên luận theo tượng hào động` phải KHÔNG còn |
| `AiWorkerTest.php:67` | grep `Chủ đề luận sâu: tình duyên` trong prompt — V3 chỉ mất dòng này ở nhánh T-C (job không question → không vỡ) |
| `QuestionCacheTest.php:45-147` | T9-T13 + chống leak question ra payload #6 — hash D1 không đổi thì toàn bộ giữ nguyên |

Không có test nào assert chuỗi `200–400` (đã grep toàn bộ `backend/tests` — 0 hit): đổi độ dài KHÔNG vỡ red cũ, chỉ thêm instruction.

## 1. Phạm vi

IN:
1. V1 — giọng cổ nhân cho bài luận: rewrite SYSTEM_PROMPT + dòng tail bố cục + block 3 hành động đo được + hard rule "Hán phải kèm cặp quốc âm–nghĩa" QA-check được.
2. V2 — router danh mục: bước phân loại NHỎ chạy trong worker TRƯỚC bước luận, 4 template prompt (§3), 4 nhánh (matched / cross-tab / KHONG_THUOC_NAO / fallback).
3. Wordguard + rule độ dài: diff tường minh §4.

OUT (cấm làm kèm):
- Sửa `BANNED_PATTERNS` (8 mẫu — luật sản phẩm 01 §1), `Rules.php`, cap C-06, cache key D1 (§6.5), schema `draws`, luồng payment/entitlement.
- FE đổi tab theo router (§5.5: UI đứng yên, zero code FE cho lane router).
- History/replay bài luận (nợ cũ luan-sau-lifecycle).

## 2. V1 — Cổ hóa văn phong (KHÔNG cổ hóa cấu trúc)

### 2.1 Bất biến cấu trúc

3 khối giữ nguyên CHỨC NĂNG và THỨ TỰ: Hoàn cảnh → Vì sao → Việc nên làm. Không thêm/bớt khối. Marker mới là ĐỔI NHÃN, chờ anh Tuyền chốt A/B:

**Phương án A (dev-lead khuyến nghị)** — cặp "Thì – Vị – Biện", thuần Việt cổ, không Hán tự trong nhãn:

```
[Thì] — thì của chuyện
[Vị] — xét cho ra nhẽ
[Biện] — liệu mà làm
```

Lý do A chọn chữ Việt thay "Thời–Vị–Ứng" ghi trên card: (1) nhãn Hán tự 3 chữ đơn sẽ khiêu khích đúng cái rule "Hán phải kèm giải" mà ta vừa đặt — bài chứa Hán ngay ở heading; (2) "Thì/Vị/Biện" là bộ ba có thật trong lối bình Dịch Nho gia (hào từ xét thì & vị, quái từ xét biện — cách ứng xử), người nghe hiểu được mà không cần học; (3) FE render không đụng bảng mã. Nếu anh Tuyền vẫn thích "Thời–Vị–Ứng" nguyên bản thì chỉ đổi 3 chuỗi marker, mọi thứ khác y nguyên.

**Phương án B (an toàn zero-red)** — GIỮ 3 marker Việt V2, cổ hóa bằng subtitle:

```
[Hoàn cảnh] — thì của chuyện
[Vì sao khuyên vậy] — xét cho ra nhẽ
[Việc nên làm cụ thể tuần này] — liệu mà làm
```

B không đổi marker gốc → T14 (`PromptLuanV2Test.php:84-86`) và lane render FE giữ nguyên 100%, chỉ thêm substring. Rủi ro thấp nhất, chất cổ mờ hơn A một bậc.

Cả A và B dùng CHUNG khối lệnh tail (§2.2). Verdict gate t_91b1cac2 chọn 1; BE code theo đúng bảng marker chốt.

### 2.2 tail block mới (thay PromptBuilder.php:103-109, cả 4 template dùng)

```
Bố cục BẮT BUỘC 3 khối, đúng thứ tự, không thêm khối ngoài 3 khối:
[Thì] — thì của chuyện: khung tình huống quẻ chỉ ra{(topic != null ? ' cho chủ đề '.$topicLabel : '')}.
[Vị] — xét cho ra nhẽ: dẫn lời quẻ/hào từ mà luật Biện quẻ ở trên đã chọn, giải thích vì trời đất xếp thế ấy; câu Hán trích xong PHẢI có đúng 2 dòng liền dưới: dòng quốc âm mở đầu bằng tên hào, dòng nghĩa nôm na.
[Biện] — liệu mà làm: tối đa 3 gạch đầu dòng; MỖI gạch có mốc thời gian cụ thể (today/tomorrow/3-5 ngày tới/tuần này/15 phút mỗi tối) + một hành động đời thường đo được, không nghi lễ, không lời khuyên chung chung kiểu 'cứ bình tĩnh'.
Giữ 240–420 từ (đếm theo ô whitespace, khối trích 3 dòng tính nguyên văn). Kết bài đúng một câu: bài này chỉ mang tính tham khảo giải trí về văn hoá.
```

(Phương án B: thay 3 dòng marker bằng 3 dòng B ở §2.1; phần còn lại giữ nguyên chữ.)

Diff từng dòng vs V2: dòng "Bố cục BẮT BUỘC 3 phần"→"3 khối" (substring test cũ `Bố cục BẮT BUỘC 3` vẫn khớp nếu test cập nhật theo — xem §8 red-list); dòng [Hoàn cảnh]/[Vì sao]/[Việc] → 3 dòng A/B mới + thêm 2 ràng buộc MỚI: cặp quốc âm–nghĩa (§2.4) và mốc thời gian block 3 (§2.5); `Giữ 200–400 từ` → `Giữ 240–420 từ` (§4.2); dòng kết disclaimer từ SYSTEM_PROMPT (Wordguard.php:31) được NÂNG vào user prompt vì bản cổ dễ quên; dòng (d) CẤM bịa (PromptBuilder.php:111) giữ nguyên.

### 2.3 Luật giọng (rewrite Wordguard::SYSTEM_PROMPT dòng 26 — §4.1)

Thay dòng `Giọng điệu điềm đạm, thiên về suy ngẫm.` bằng khối giọng:

```
Giọng người kể: cụ đồ bình giải sách cổ trước ấm trà — chậm rãi, ấm, chữ có cân có đong.
- Xưng hô với khách: 'nhà mình' hoặc 'mình'; KHÔNG 'bạn' kiểu blog, không 'quý khách'.
- Câu văn có vần nhịp, ưu tiên câu ngắn 2 vế đối ('thương thì hỏi, không thì đừng đoán'); không sáo ngữ hiện đại: tối ưu, hiệu suất, kết nối sâu, năng lượng, vũ trụ, mindset, healing.
- Nói chuyện đời bằng vật thật: bữa cơm, ngọn đèn, con đường đêm, ấm trà. KHÔNG nói bằng khái niệm.
- Cổ văn phong CHỈ ở giọng; tuyệt đối không bịa điển cố, không dẫn 'sách xưa nói' mà sách đó không có trong dữ liệu quẻ đưa ra.
```

### 2.4 Hard rule "Hán ⇒ cặp quốc âm + nghĩa" (QA check được)

Bất biến trích dẫn (nguồn format chuẩn đã có sẵn: yaoBlock `PromptBuilder.php:82`, dataset `{han, am, nghia}` hexagrams.json:34-54):

> Bài luận CẤM có dòng chứa Hán tự (U+4E00–U+9FFF) mà 2 dòng liền dưới không phải (1) dòng quốc âm, (2) dòng nghĩa thuần Việt.

Ràng buộc bằng prompt: dòng [Vị] §2.2 đã ghi. Kiểm bằng test (không bằng output filter — xem §4.3): mock provider trả bài cố tình thiếu dòng quốc âm → golden test regex:

```
/^.*[\x{4E00}-\x{9FFF}].*$/mu từng dòng: nếu match thì 2 dòng sau KHÔNG được chứa [\x{4E00}-\x{9FFF}] và dòng kế phải có dấu ':' (quốc âm mở đầu kiểu 'Cửu tứ:').
```

Nếu mô hình vẫn sót cặp, QA đo bằng bộ 20 bài generate thật (lane QA), không chặn job — cùng triết lý output filter: chỉ BANNED_PATTERNS mới giết bài.

### 2.5 Block 3 không được thành sẩm trạng

Ràng buộc "mốc thời gian + hành động đo được" đã nằm trong dòng [Biện] §2.2. Golden test T21 (§8): assert prompt chứa `mốc thời gian` + `hành động đời thường đo được`. Bài mẫu §7 chứng minh giọng cổ vẫn ra gạch "Ba bữa tới / Năm hôm nữa / Mười lăm phút mỗi tối".

## 3. V1+nhánh — Bảng 4 prompt template (diff từng dòng so với prompt V2)

Chữ ký `userPrompt` GIỮ NGUYÊN 8 tham số (PromptBuilder.php:33-42); THÊM 1 tham số cuối `?string $routedTopic = null` (null = router không chạy/không tin). `topicLabel` và `freeKey` dựng từ `effectiveTopic = routedTopic ?? topic` — ngoại lệ duy nhất: T-C xóa hẳn 2 dòng đó.

Header chung các template = PromptBuilder.php:60-69 (8 dòng) trừ ghi chú; yaoBlock/bienBlock giữ y nguyên cho MỌI template (luật BianRule không phụ thuộc danh mục); dòng (a) `Khách đang vướng:` (:73) có ở T-A/T-B/T-D.

| Template | Chạy khi | Diff từng dòng vs prompt V2 hiện hành |
|---|---|---|
| **T-A matched** | question != null, router trả topic == topic tab | **0 dòng khác.** Prompt V2 nguyên văn (header 61-68 + :73 + yao + bien + tail mới §2.2 + không có dòng :111). Đây là regression baseline. |
| **T-B cross-tab** | router trả topic khác tab (bấm 'tình duyên' hỏi chuyện tiền) | Khác 2 dòng trong header: dòng 61 → `Chủ đề luận sâu: {label_của_routedTopic}.`; dòng 66 → `Góc nhìn sẵn có về {label_của_routedTopic}: {free[freeKey(routedTopic)]}`. +1 dòng mới NGAY SAU :61: `Khách hỏi thẳng điều này — luận đúng chuyện khách hỏi, đừng lái về chủ đề khác.` Các dòng còn lại y nguyên. Field `ai_jobs.topic` KHÔNG đổi (§5.3). |
| **T-C KHONG_THUOC_NAO** | router trả KHONG_THUOC_NAO (hỏi sức khỏe/legal/chuyện không thuộc 3 mục) | **XÓA dòng 61** (`Chủ đề luận sâu…`) và **XÓA dòng 66** (`Góc nhìn sẵn có về…`) — không còn trục danh mục nào để trôi bài/định hướng. THAY bằng 2 dòng: `Việc khách hỏi: "{question}" — hỏi gì đáp nấy.` và `Ràng buộc: mọi điều khuyên phải bám đúng lời quẻ/hào từ đã dẫn ở trên; cấm suy diễn sang chuyện tài chính, tình cảm, xuất hành nếu khách không hỏi.` +1 dòng tròng vào khối (d)-cấm: giữ `CẤM bịa…` của :111 NHƯNG đổi đuôi thành `Chỉ luận đúng điều khách hỏi, bám lời quẻ.` (question CÓ tồn tại nên nhánh :110 điều kiện phải viết lại — điều kiện mới: thêm dòng cấm khi `questionForPrompt === null`, xem bảng §5.4). Dòng [Thì] tail bỏ hậu tố `cho chủ đề {label}` (§2.2 đã inline điều kiện). yaoBlock/bienBlock/`Luật Biện quẻ` y nguyên. Vẫn đúng 3 khối [Thì][Vị][Biện]. |
| **T-D fallback** | router LỖI (mạng/timeout/JSON rác) hoặc trả UNCLEAR-mơ-hồ-nghiêng-về-lỗi | = T-A nguyên văn + 1 dòng mới cuối tail: `Nếu câu hỏi của khách không thuộc chủ đề đã nêu, cứ thẳng thắn đáp đúng câu hỏi ấy theo lời quẻ; không cứng nhắc kéo về chủ đề.` TUYỆT ĐỐI không im lặng, không fail job vì router — bài luận vẫn chạy đủ. |

Câu hỏi UNCLEAR "quá mơ hồ" (đơn tự, 'abc', '???') KHÔNG vào T-D mà về luồng cũ: prompt = T-A nhưng question coi như null → giữ dòng :111 `CẤM bịa…`, FE vẫn hiện `Bạn hỏi:` (TopicGate.vue:218 display-only, không sao). Phân biệt UNCLEAR→luồng-cũ vs lỗi-router→T-D tại §5.4.

## 4. Wordguard & độ dài: hiện trạng → thay đổi → test red

### 4.1 SYSTEM_PROMPT (Wordguard.php:23-32)

- Hiện trạng: dòng 26 "giọng điềm đạm"; dòng 30 `200–400 từ`; dòng 31 disclaimer cuối bài.
- Thay: dòng 26 → khối giọng §2.3 (6 dòng); dòng 30 → `240–420 từ`; dòng 31 giữ.
- Diff +7 dòng, net ~+90 token/bài — chấp nhận (một nguồn định nghĩa giọng, chống lệch 2 đường user/system).

### 4.2 Độ dài 200–400 → 240–420

Lý do: thể cổ + cặp trích 3 dòng (Hán/quốc âm/nghĩa ~25 từ một cặp, bài chuẩn trích 1-2 cặp) nở hơn văn nói V2. Nền 240 chống bài cụt, trần 420 chống sẩm trạng dài. Đếm: `str_word_count` kiểu explode(' ') trên text đã strip markdown marker (định nghĩa 1 chỗ trong helper test, KHÔNG có enforcement runtime — chỉ instruction + QA đo mẫu). PromptBuilder.php:108 + Wordguard.php:30 đổi ĐỒNG BỘ 2 chuỗi — 1 bất biến 1 nơi: chuỗi `Giữ 240–420 từ` define `PromptBuilder::LENGTH_LINE` const, SYSTEM_PROMPT không lặp con số (bỏ `200–400 từ` khỏi dòng 30, giữ `đúng 1 chủ đề được yêu cầu`→`đúng theo bố cục khối`).

### 4.3 BANNED_PATTERNS — giữ nguyên 8 mẫu (Wordguard.php:14-17)

Cấm thêm từ vào filter: mỗi mẫu mới = xác suất AI_FILTERED giết bài của khách đã trả 29k tăng. Sáo ngữ hiện đại (§2.3) chỉ là instruction + golden test, không phải output filter. Nếu pilot ghi nhận drift, mở card riêng đo tỉ lệ trước/sau.

## 5. V2 — Router danh mục từ câu hỏi (hướng anh Tuyền chốt, thay phương án (a))

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
Chỉ in ra ĐÚNG một từ trong sáu khả năng: duyen | tai_loc | xuat_hanh | KHONG_THUOC_NAO | UNCLEAR. Không giải thích, không dấu câu.
Câu hỏi: "{question}"
```

- Model: env MỚI `AIBOX_ROUTER_MODEL` (config/aibox.php thêm key `router_model`, default `''` → fallback về `AIBOX_MODEL` hiện hành — cùng 1 base_url/key, OpenAI-compatible). Temperature `0` (khác 0.7 của bước luận, AiBoxClient.php:34), `max_tokens: 8`, không retry riêng.
- Parse: `trim` + uppercase-insensitive match whitelist đúng 6 giá trị; DƯ THỪA văn bản → coi như UNCLEAR. Không dùng JSON mode (ràng buộc vào tính năng provider, model nhỏ không ổn định bằng plain token).
- Chi phí: input ~180 token + output ~1 token/câu. Câu có question là minority (§5.3 của V2 đo ~70% người KHÔNG hỏi, mục tiêu 800 user/tuần → ~560 router calls/tuần ≈ 105K token input/tuần — nhỏ hơn 1 bài luận, bỏ qua được trong C-06 vì cap đếm theo JOB tạo mới, router không tạo job). Log riêng `aibox.router.sent` để AC-1 đếm được từng loại call.

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

KHÔNG đổi tab, không đổi payload (TopicGate.vue:60-66 gửi topic của tab như cũ). `Bạn hỏi:` giữ nguyên (§7.4.4 bugfix 12f28e3). Lane FE duy nhất của V3: cập nhật regex render 3 khối theo BẢNG MARKER chốt ở gate (§2.1) — nếu A, FE + QA phải đổi grep `[Hoàn cảnh]`→`[Thì]` đồng loạt; nếu B, FE zero-change.

## 6. Quyết định kiến trúc (dev-lead chốt, ghi ADR-inline)

1. **ADR-V3-01 — Router là bước trong worker, không phải endpoint HTTP mới.** Trade-off: +1 provider call trong 1 job (latency giờ đỉnh +≤10s) đổi lấy 0 surface API mới, 0 migration, entitlement sạch. Đảo ngược được: tách sang service đồng bộ nếu về sau cần hiển thị route trước khi poll.
2. **ADR-V3-02 — Cổ hóa = tầng prompt + hằng số chuỗi, KHÔNG đụng domain data.** `BianRule`, `Luan`, `hexagrams.json`, cache, hash: zero diff. Toàn bộ delta nằm trong 3 file `Wordguard.php`, `PromptBuilder.php`, `AiBoxClient.php` (+ `Rules.php` 1 const timeout).
3. **ADR-V3-03 — Kiểm chất lượng giọng bằng golden test + mẫu duyệt mắt, không bằng output filter.** Filter giết bài = mất tiền khách; instruction + đo mẫu = chỉnh được từng câu.
4. Prompt layout bất biến: mọi chuỗi instruction mới đặt trong 2 hằng số (RouterPrompt, PromptBuilder) — file nào quá 250 dòng thì tách `PromptBuilderSections` (PromptBuilder hiện 127 dòng, còn xa ngưỡng).
5. `userPrompt` thêm tham số cuối optional → chữ ký tương ngược về trước (mọi call cũ 8 tham số vẫn hợp lệ).

## 7. Bài mẫu giọng cổ — CHO ANH TUYỀN DUYỆT MẮT (không code)

Bản đầy đủ: `docs/specs/SPEC-LUAN-V3-mau.md` (commit cùng file này) — case dựng sẵn: tab Tài lộc, hỏi 'người ấy nghĩ gì về em', router → duyen (T-B), quẻ id31 Hàm hào 4 động (case 1). Trích đúng dữ liệu thật: hào 4 `九四：貞吉悔亡，憧憧往來，朋從爾思。` (hexagrams.json:2935), quốc âm + nghĩa theo `{am, nghia}` cùng object. ~300 từ, đủ cặp Hán→quốc âm→nghĩa, 3 gạch có mốc thời gian, kết disclaimer.

## 8. Test TDD — danh sách RED trước (be-dev viết đỏ → xanh; nộp [REVIEW] thiếu test = trả về)

Đổi màu test cũ (chỉ khi verdict = phương án A; B thì dòng này trống):
- `PromptLuanV2Test.php::test_prompt_co_dong_3_phan_marker` (:82-88) — red trước, đổi 3 chuỗi marker sang §2.1-A.
- `test_prompt_cam_doan_khi_question_trong` (:90-102) — giữ T-A/T-B; tách assertion nhánh T-C sang T23 dưới.

Unit mới (RouterPromptTest / PromptBuilder template — ≥4):
| # | Test | Assertion |
|---|---|---|
| T19 | `test_promptbuilder_routed_topic_doi_label_va_free` | routed 'tai_loc' trên job topic 'duyen': chứa `Chủ đề luận sâu: tài lộc` + free `taiLoc`, KHÔNG chứa `tinhDuyen` |
| T20 | `test_prompt_khong_thuoc_nao_xoa_2_dong_danh_muc` | T-C: NOT contains `Chủ đề luận sâu`, NOT contains `Góc nhìn sẵn có`, contains `Việc khách hỏi: "…"` + `Ràng buộc:`, still contains 3 marker + `Luật Biện quẻ` |
| T21 | `test_prompt_tail_co_moc_thoi_gian_va_dan_chung` | mọi template chứa `mốc thời gian`, `hành động đời thường đo được`, `quốc âm`, `240–420 từ` |
| T22 | `test_router_prompt_parse_6_gia_tri` | helper parse: 'duyen\n', ' DUYEN ', cả 6 từ khóa whitelist → match; 'có lẽ là duyen' → UNCLEAR |

Feature mới (worker mock — ≥4):
| # | Test | Assertion |
|---|---|---|
| T23 | `test_worker_co_question_goi_router_truoc_luan` | Http fake 2 calls: call 1 model router, temperature 0, body chứa đúng question; call 2 luận; job có question vẫn 1 job, hash D1 không đổi (QuestionCacheTest chạy xanh giữ nguyên) |
| T24 | `test_worker_question_chung_chi_khong_goi_router` | question null → ĐÚNG 1 call provider (luồng V2 nguyên trạng, cost gate AC-1 không đổi màu) |
| T25 | `test_worker_cross_tab_luan_dung_muc_khong_doi_job_topic` | router trả 'tai_loc', DB `ai_jobs.topic` vẫn 'duyen', prompt theo T-B |
| T26 | `test_worker_router_loi_khong_bao_gio_lam_fail_job` | mock call 1 ném ConnectionException → job vẫn done với prompt T-D; attempts không +1 thêm vì router |
| T27 | `test_worker_unclear_ve_luong_cu_cam_bia` | router trả 'UNCLEAR' → prompt chứa `CẤM bịa hoặc đoán hoàn cảnh riêng` + không chứa `Khách đang vướng` |
| T28 | `test_cache_key_khong_doi_du_co_router` | golden: 2 request cùng draw+topic+question → cùng `result_key_hash` (sha256 3 thành phần) |

QA (độc lập, không đỏ hộ): chạy bộ + generate mẫu 20 bài thật chấm 3 mục: đúng template theo bảng §5.4, đủ cặp Hán→2 dòng, block 3 có mốc. Đối chiếu regex §2.4.

## 9. Thứ tự nộp & gate

1. **GATE t_91b1cac2 TRƯỚC TIÊN**: anh Tuyền chốt (a) marker A hay B, (b) bài mẫu §7 đạt chưa, (c) OK router T-B "tin câu hỏi, luận đúng mục, không đổi tab" + T-C bỏ dòng danh mục. CEO chuyển tiếp. Không có verdict = lane BE/FE đứng, SPEC chưa chốt §2.1.
2. be-dev branch `card/<id>`: RouterPrompt+parse (T19/T22 red) → AiBoxClient::routeTopic+Rules const (T23/T24/T26) → PromptBuilder effectiveTopic+4 template (T19-T21,T25,T27) → Wordguard/PromptBuilder tail+length (đổi marker nếu A) → [REVIEW] kèm `php artisan test` log.
3. fe-dev (chỉ khi A): regex render marker mới + TEST-FIELDS.md cập nhật. B → không có lane FE.
4. dev-lead merge BE trước (FE chỉ đụng render string). Gate: TDD-first, 4 trục + thị giác khối bài, thiếu test = trả về.
5. Cấm đụng: `BANNED_PATTERNS`, `Rules::AI_GLOBAL_CAP_PER_HOUR`, hash D1, `findCacheHit`, schema.

Estimate card: 60' — SPEC nộp đủ 5 acceptance, mọi số dòng đối chiếu main eaced06.
