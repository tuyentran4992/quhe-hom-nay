# SPEC-VS1-L1 — Attribution token cho vòng chia sẻ (V7 props + devices.referred_token) — data-only, zero UX

Card: t_de942565 (dev-lead, SPEC — KHÔNG code). Ngày: 02/09/2026.
Cơ sở phê duyệt: CEO VERDICT /data/agents/ceo/outbox/t_57246e37/VERDICT-VIRAL-SWEEP.md — VS1 DUYỆT CHẸC Layer 1, HOÀN Layer 2.
Nguồn đề xuất: /data/agents/viral-designer/outbox/t_fd6ddd7e/PROPOSAL-VS1.md §2 (L1) + SWEEP-FINDINGS §1 REWARD.
Điều kiện đo của growth-lead: /data/agents/growth-lead/outbox/t_cfd374d1/KPI-GHEP-VIRAL-F8.md §MẪU 2 (ĐK-1, ĐK-2, ĐK-4) + comment 07:48 trên card.
Mọi dẫn chứng code dev-lead TỰ ĐỌC trên main @ 8a47694 tại thời điểm viết (không tin mô tả suông).

## 0. Mục tiêu 1 câu + phạm vi

Cho phép truy vấn "ai được mời từ THẺ NÀO" sau khi vòng share chạy: device mới bấm CTA
`/s/{token}/cta` lưu token nguồn vào `devices.referred_token` (first-touch, ghi 1 lần), và
sự kiện V7 `share_referred_draw` mang token đó trong props → tổng hợp `draws_per_token`.

PHẠM VI DUYỆT = L1 data-only:
- 1 migration (cột mới, không回填), 1 cột, 2 hàm service sửa, props V7 thêm 1 khóa.
- FE: 0 dòng. UX: 0 thay đổi (token đã sẵn trong URL server-side). Event names: 0 thay đổi.
- LAYER 2 (UI "N mầm" + chip S5): HOÀN — không có mặt trong SPEC này. Điều kiện mở lại
  (≥50 device V4 thật HOẶC D14 scorecard có dữ liệu + số i vòng-lặp đầu) ghi trong VERDICT.

## 1. Bất biến (đến dòng code nào cũng phải giữ)

1. **Tên event V1–V7 BẤT BIẾN** (METRICS §1, `Event::NAME_WHITELIST` backend/app/Models/Event.php:18-31).
   Không thêm/bớt/sửa tên. CHỈ thêm khóa `token` vào props của V7 (props là JSON tự do,
   TrackService::normalizeProps không validate schema props — đã kiểm backend/app/Services/TrackService.php:96-106).
2. **Backward compat**: event V7 cũ chỉ có `{draw_id}` vẫn hợp lệ mãi mãi. Mọi query trong §4
   phải chạy đúng trên dữ liệu trộn cũ+mới (group theo token NULL riêng, không crash, không loại dòng).
   Chuỗi trace sweep t_fd6ddd7e (probe 4/4 PASS) không được đứt — test hồi quy §6 T5.
3. **First-touch-khóa**: `referred_token` chỉ ghi khi cột ĐANG NULL, cùng bất biến utm_* của
   06-mkt §2 / TrackService::applyFirstTouch:55-77. Sự kiện sau không đè.
4. **CONFIG-FIRST**: hàng mục đổi được (ngưỡng, cột) → `backend/config/project.php`.
   L1 KIỂM TRA: không có tham số nghiệp vụ nào đổi được → **0 key config mới, 0 hardcode số**.
   (Nếu tương lai cần công tắc tắt ghi token: thêm `share.attribution_token` vào project.php
   theo đúng protocol FA-VS3-CONFIG của SPEC-VS3 — không làm bây giờ, đừng thêm knob không ai vặn.)
5. Cấm đụng: pricing/paywall, popup nag, auto-post, account ảo. SPEC này không mở bất kỳ surface nào.

## 2. Migration (BE duy nhất, DDL theo lệ raw SQL của 000007)

File MỚI: `backend/database/migrations/2026_09_02_000010_add_referred_token_to_devices.php`
(SỐ THỰC: impl cắt branch từ main mới nhất và đánh số tiếp theo sau migration cuối trên main
tại lúc đó — số 10 là số hiện hành trên main @8a47694, 13 file, đầu cuối `..._000009_...`).

```php
return new class extends Migration {
    public function up(): void {
        DB::statement(<<<'SQL'
ALTER TABLE devices
  ADD COLUMN referred_token CHAR(10) NULL AFTER utm_campaign,
  ADD KEY idx_devices_referred_token (referred_token)
SQL);
    }
    public function down(): void {
        DB::statement('ALTER TABLE devices DROP COLUMN referred_token');
    }
};
```

- Kiểu: `CHAR(10)` — khớp `ShareToken::isValid` `^[0-9A-Za-z]{10}$` (backend/app/Domain/ShareToken.php:44-47).
  KHÔNG phải FK: token có thể失效 nếu bảng share_links đổi; cột này là dấu vết attribution thô, không ràng buộc toàn vẹn.
- Index `idx_devices_referred_token`: phục vụ GROUP BY của query §4-Q3 (bản từ cột).
- **Không backfill**: prod = 0 device (KPI-W1 §0) — không có gì để回填; device cũ có utm_medium=share
  từ trước (chuỗi trace QA) để NULL, query §4 vẫn tính chúng vào nhóm "token NULL" — đúng bản chất
  "trước khi L1 live, không đo được từ thẻ nào".
- `Device` model: KHÔNG cần thêm vào `$fillable` (Device.php:26 chỉ có device_id/session_id —
  utm_* cũng không có; mọi write đi qua conditional update như applyFirstTouch). Không sửa model.

## 3. Nghiệp vụ — đúng 2 hàm sửa, 0 file khác

### 3.1 Capture: `ShareLinkService::recordCtaClick` (hiện :232-238)

Token đã nằm sẵn trong `$link` (route GET `/s/{token}/cta` → controller `cta()` :99-114 đã
resolve + validate `ShareToken::isValid`). Chỉ thêm capture vào CỘT, cùng pattern race-safe
của `applyFirstTouch` (update có `whereNull` trong WHERE):

```php
public function recordCtaClick(ShareLink $link, Device $viewer): void
{
    $this->track->track($viewer, self::CTA_EVENT, self::CTA_UTM, [
        'token' => $link->token,
        'utm_medium' => self::CTA_UTM['medium'],
    ]);

    // VS1-L1: first-touch-khóa token nguồn, chống self-referral (chủ thẻ bấm lại link mình
    // không được tính "được mời từ thẻ của chính mình"). Cột NULL mới ghi; không đè.
    if ($viewer->device_id !== $link->device_id) {
        $affected = Device::query()
            ->where('device_id', $viewer->device_id)
            ->whereNull('referred_token')
            ->update(['referred_token' => $link->token]);
        if ($affected === 1) {
            $viewer->referred_token = $link->token;
        }
    }
}
```

- V6 event giữ NGUYÊN props (`token`, `utm_medium`) — thêm cột không đổi event.
- Lý do đặt ở ShareLinkService chứ không phải TrackService: TrackService sở hữu bất biến
  utm_* (3 cột whitelist UTM_COLUMNS); `referred_token` là nghiệp vụ share-link, 1 service
  1 trách nhiệm — token chỉ có ở đây (đối số `$link`), không đẩy sang TrackService qua ngả
  "utm giả". Ghi chú doc-block cập nhật: hàm giờ có 2 việc liên quan (đo V6 + capture
  attribution) — vẫn 1 trách nhiệm "ghi dấu vết CTA".

### 3.2 Props V7: `ShareLinkService::maybeFireReferredDraw` (hiện :255-263)

```php
public function maybeFireReferredDraw(Device $device, Draw $draw): void
{
    if ($device->utm_medium !== 'share') {
        return;
    }
    $props = ['draw_id' => (int) $draw->id];
    if ($device->referred_token !== null) {
        $props['token'] = $device->referred_token;   // VS1-L1 — event CŨ không có key này, vẫn hợp lệ
    }
    $this->track->track($device, self::REFERRED_EVENT, [], $props);
}
```

- DrawController KHÔNG đổi: hook đã defer() sau 201 (DrawController.php:67-73), `$device`
  là model load mới mỗi request qua EnsureDeviceSession → cột `referred_token` tự có trên
  instance, không sửa controller/middleware.
- Điều kiện fire KHÔNG đổi (vẫn `utm_medium === 'share'`, đọc CỘT — bất biến BUG-F7-QA1
  đã test trong ShareCtaReferredDrawRealChainTest). Token chỉ là props thêm khi có;
  V7 vẫn fire khi referred_token NULL (device share-referred đời cũ, hoặc self-click bị
  chặn ở 3.1) → props `{draw_id}` đơn = đúng format cũ → **zero regression cho chuỗi trace sweep**.

### 3.3 Tính nhất quán utm_medium ↔ referred_token (lý do không cần guard thêm)

Cả hai cột first-touch được ghi trong CÙNG request `/s/{token}/cta`: utm qua
track()→applyFirstTouch, token qua 3.1. V7 chỉ fire khi `utm_medium='share'`, mà giá trị
'share' chỉ có thể vào cột từ CTA (CTA_UTM là bộ utm duy nhất — comment :34-38) → mọi
device có V7 là device đã bấm CTA ít nhất 1 lần. Nếu utm_medium='share' mà token NULL
(thiết bị self-click, hoặc đời trước L1) → props không token, nhóm NULL của §4. Không có
kịch bản token của thẻ X nhưng utm khóa từ thẻ Y KHÁC qua đường CTA — vì cả 2 write nằm
trong cùng một cú bấm đầu tiên (cột nào đã có giá trị thì cả hai đều là của cú đó... NGOẠI
TRỪ race: device bấm CTA thẻ A đúng lúc 2 tab cùng ghi — whereNull update ở tầng câu lệnh
đảm bảo mỗi cột lấy người thắng; cột utm và cột token có thể khác thẻ trong 1ms race.
Chấp nhận: tỷ lệ ~0, funnel theo tuần không đổi; ghi ở §7 Rủi ro, không phức tạp hóa bằng transaction).

## 4. Ba query tổng hợp (nghiệm thu ĐK-2 growth — phải chạy trên DB smoke lane)

Nguồn CHUẨN = cột `devices.referred_token` (bền, có index); props V7 là bản ảnh để đối chiếu chéo.

Q1 — share_rate (METRICS §2):
```sql
SELECT COUNT(DISTINCT CASE WHEN e.name='share_card_done' THEN e.device_id END) AS v4_devices,
       COUNT(DISTINCT d.device_id) AS draw_devices,
       ROUND(100 * COUNT(DISTINCT CASE WHEN e.name='share_card_done' THEN e.device_id END)
             / NULLIF(COUNT(DISTINCT d.device_id),0), 1) AS share_rate_pct
FROM draws d
LEFT JOIN events e ON e.device_id = d.device_id
WHERE d.drawn_date BETWEEN ? AND ?;
```

Q2 — k (device MỚI có V7 / device có V4; "mới" = first_seen trong kỳ, không phải draw đầu):
```sql
SELECT COUNT(DISTINCT CASE WHEN e.name='share_referred_draw' AND dv.first_seen BETWEEN ? AND ?
                           THEN e.device_id END) AS new_v7_devices,
       COUNT(DISTINCT CASE WHEN e.name='share_card_done' THEN e.device_id END) AS v4_devices
FROM devices dv LEFT JOIN events e ON e.device_id = dv.device_id;
```

Q3 — draws_per_token (vòng lặp, THEO COLLECT CARD GỐC):
```sql
-- bản cột (canonical):
SELECT referred_token, COUNT(*) AS referred_devices
FROM devices WHERE referred_token IS NOT NULL
GROUP BY referred_token ORDER BY referred_devices DESC;

-- bản đối chiếu props V7 (chứng minh backward compat: dòng cũ gộp vào token NULL, không mất):
SELECT JSON_UNQUOTE(JSON_EXTRACT(e.props,'$.token')) AS token,
       COUNT(DISTINCT e.device_id) AS referred_devices
FROM events e WHERE e.name='share_referred_draw'
GROUP BY token ORDER BY referred_devices DESC;
-- MySQL: JSON_EXTRACT(props,'$.token') trên row props=NULL trả NULL → NULLIF mới tính; không crash.
```

- Impl không phải viết API/command mới: growth chạy SQL thủ công + BE-VS1L1 phải **chứng minh
  3 query chạy được** bằng test T4 (§6) trên seed thật (2 thẻ, 3 device, 1 event cũ không token).
  Đưa 3 query vào file `docs/specs/QUERIES-VS1-L1.sql` (commit cùng PR impl) để growth copy-paste.
- Anti-rule D14 (growth ghi chú): dòng `k` trên scorecard chỉ tô màu khi V4 ≥50 device —
  trách nhiệm bảng đo, không phải code; SPEC ghi để impl khỏi "tự tin" với mẫu 3 device smoke.

## 5. Xung đột file với lane đang chạy — ràng buộc cứng

State kiểm bằng `git worktree list` + dirty status worktree CFG-BE tại 02/09 ~07:55 UTC:

| Lane | File chạm | Xung đột với VS1-L1 |
|---|---|---|
| CFG-BE t_ce2a6834 (dirty: Rules, project.php, DrawService, 22 file) | config/*, Rules, services AI/pay | **KHÔNG** — VS1-L1 không đụng Rules/DrawService/project.php; §0.4 chốt 0 key config |
| ANIM-LUAN t_7b296305 | frontend | Không (VS1-L1 zero-FE) |
| DONATE-V2 merge chain (t_ea138b84/t_0bf58fd6) | constants.js, PaywallView | Không |
| VS3 impl stack (SPEC-VS3) | ShareCardView.vue, clipboard.js, blade, project.php block `share` | **GIÁN TIẾP**: không chung file, nhưng cả hai queue sau CFG — tránh 2 migration+1 config edit cùng bay |
| BE-PAY-EXPIRE (queue) | PaymentService | Không |

Ràng buộc impl (dev-lead thực thi tại merge gate, worker không tự ý):
1. **CẤM sửa `backend/config/project.php` trên branch impl** — file đang untracked-dirty
   trong worktree CFG-BE (owner: chain t_ce2a6834). VS1-L1 không cần nó (§0.4) → xung đột = 0.
2. Branch impl cắt từ main SAU khi chain CFG-BE khép (t_ce2a6834 → QA-CONFIG t_bd934876 →
   CEO-VERIFY) và xếp SAU toàn bộ lane hiện hành: ANIM→QA→MERGE; DONATE-V2 merge; VS3 stack
   (FA-VS3-CONFIG). Không cắt ngang — đúng chỉ đạo VERDICT §"Kỷ luật & xếp hàng".
3. Migration: đặt số theo main lúc cắt branch (rebase verify); không sửa migration cũ.
4. Nếu tại thời điểm fan impl main đã có `config('project.share.*')` (VS3 vào trước) — không
   liên quan, VS1-L1 không đọc config nào.

## 6. Acceptance đo được (TDD — test đi trước code)

Giữ + mở rộng `backend/tests/Feature/Api/ShareCtaReferredDrawRealChainTest.php` (chỉ sửa
thêm assertion, KHÔNG viết lại test cũ — nó là chứng nhân trace sweep):

- T1 (migration): `SHOW COLUMNS FROM devices LIKE 'referred_token'` → có, NULL được;
  insert device không có token → hợp lệ (backward compat schema).
- T2 (chuỗi thật, mở rộng test hiện có): B mới → /s/{token} → CTA → draw 201 →
  V7 props = `{draw_id, token}` với token === token thẻ A. (`$v7->props['token']`.)
- T3 (first-touch-khóa): B bấm CTA thẻ A rồi thẻ B' → `devices.referred_token` vẫn = thẻ A.
- T4 (query growth): seed 2 share_links + 3 device referred (1 device event cũ không token
  simulate pre-L1: insert Event tay props `{draw_id}`) → Q3 cả 2 bản chạy, trả đúng
  {tokenA: n1, tokenB: n2, NULL: 1}; không exception.
- T5 (regression): suite cũ xanh nguyên — `ShareReferredDrawTest`,
  `ShareCtaReferredDrawRealChainTest` (assertion props cũ chỉ kiểm draw_id → vẫn pass khi
  có thêm token: sửa sole() assertion thành assertArraySubset-style, KHÔNG đổi tên event),
  toàn bộ `FREE_DEEP_PREVIEW=false php artisan test` (footgun lane: env máy dev có true →
  4 test F8 đỏ oan — chạy đúng lệnh).
- T6 (zero-UX): `git diff --name-only` của branch impl ⊆ {migration, ShareLinkService.php,
  ShareCtaReferredDrawRealChainTest.php (+1 test mới attribution), docs/specs/QUERIES-VS1-L1.sql}
  — không file FE, không config, không controller. Merge gate từ chối nếu diff vượt danh sách.
- FE: không có gì để test (không đổi dòng nào).

## 7. Rủi ro đã cân nhắc (ghi để không phải họp lại)

- **Self-click không chặn được V7**: chủ thẻ bấm CTA link mình → referred_token KHÔNG ghi (3.1),
  nhưng utm_medium='share' vẫn khóa theo hành vi BUG-F7-QA1 hiện hữu → V7 fire không token
  (nhóm NULL). Đếm vòng lặp dùng bản CỘT (§4) nên không nhiễm self-referral. Chặn cả utm
  = đổi hành vi V6/utm hiện hữu = ngoài phạm vi L1. Nếu data launch cho thấy nhiễu đáng kể
  → card quan sát riêng, không vá顺手 ở đây.
- **Race 2 CTA cùng ms** (§3.3): utm và token có thể khác thẻ. Tỷ lệ ~0 với mẫu launch;
  không đóng transaction (đừng hy sinh tính đơn giản của 2 first-touch độc lập vì 1 race
  không đổi quyết định).
- **Token trong props = public string** (đã nằm trong URL /s/, OG, event V4/V5/V6 hiện hữu)
  — không PII mới, không credential. Prop 2KB cap của TrackService không bị chạm.
- **Đo muộn**: referred_at không có cột riêng (first_seen của device là proxy đủ cho tuần);
  thêm cột `referred_at` = scope creep, chưa có query nào cần. Ghi chú cho L2.
- Device xóa/cookies mới = mất attribution — vốn là giới hạn model device-based của 02-db §8,
  không phải regression của L1.

## 8. Phân rã card impl + ước lượng (CEO fan qua t_0561ded9 sau khi duyệt SPEC này)

| Card | Gán | Nội dung | Parents (xếp hàng) | Ước lượng |
|---|---|---|---|---|
| BE-VS1L1 | be-dev | §2 migration + §3 hai hàm + §6 T1–T6 + QUERIES-VS1-L1.sql — TDD: nộp test đỏ trước, code sau | chain CFG-BE khép + t_0561ded9 (duyet SPEC) + queue VS3/DONATE | 45' |
| QA-VS1L1 | qa-engineer | Chạy T1–T6 trên DB lane độc lập + verify diff ⊆ §6.T6 + re-run 3 query §4 trên seed smoke, dán output SQL thật vào report | BE-VS1L1 | 25' |
| (FE) | — | KHÔNG có card FE — zero-diff là thiết kế, không phải thiếu | — | 0 |

Sau QA-PASS: dev-lead tự merge theo merge gate (không chờ sếp), báo CEO khép chain kèm SHA + số test.
Tổng chi phí BE ước lượng: 45' (1 migration nhỏ, 2 hàm, 0 API mới, 0 FE) — khớp "Chi phí S" VERDICT.
