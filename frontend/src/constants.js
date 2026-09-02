// ════════════════════════════════════════════════════════════════════════════
// CFG-FE (t_130d6f4b) — CẤU HÌNH NGHIỆP VỤ FE: MỘT SURFACE DUY NHẤT.
// Đổi số ở đây, không sửa component. Sau khi đổi: build lại `npm run build`
// ra backend/public/app.
// Quy ước comment mỗi dòng đổi được: ý nghĩa / đơn vị / giá trị an toàn.
// Đối xung BE: mọi giá trị trùng tên với BE (unlock price, donate min/max,
// question max) có NGUỒN THẬT là BE `config('project.php')` — FE chỉ hiển thị.
// LỆCH GIÁ TRỊ: BE LÀ CHUẨN (FE đổi theo BE, không ngược lại).
// Nguồn spec: 03-api.md §0 + mockup v2 (DESIGN-NOTES) + boss 02/09.
// ════════════════════════════════════════════════════════════════════════════
import { fmtVnd } from './utils/format.js'

// ── GIÁ / ĐƠN HÀNG ──────────────────────────────────────────────────────────
export const PRICE_UNLOCK_VND = 29000
// ý nghĩa: giá mở khóa luận sâu 1 chủ đề (C-05), one-time theo device
// đơn vị: VND (integer, không xu lẻ) | nguồn thật: BE config('project.php') — BE chuẩn
// giá trị an toàn: 1000..500000; đổi ở ĐÂY + build lại, UI (Paywall, TopicGate,
// Home chip) đổi theo vì tất cả đọc PRICE_LABEL bên dưới

// ── DONATE (lễ tùy tâm — KHÔNG mở khóa gì) ──────────────────────────────────
// [SPEC-CHANGE boss 02/09, giữa DEV-DONATE-QR]: 4 mức gợi ý 10k/20k/50k/100k; nhập tay
// sàn 5k (chặt hơn BE 1k — FE hiển thị, BE enforce). Tag Hán + ghi chú + layout chờ
// MOCKUP-DONATE-V2 (t_1a2d3a1e) boss duyệt rồi mới chèn — tạm để trống, không tự chế.
export const DONATE_OPTIONS = [10000, 20000, 50000, 100000]
// đơn vị: VND | giá trị an toàn: tăng dần, mỗi mức trong DONATE_MIN..DONATE_MAX
export const DONATE_TIERS = [
  { amount: 10000, han: '', note: 'một chút tâm ý' },
  { amount: 20000, han: '', note: 'tâm ý vừa đủ' },
  { amount: 50000, han: '', note: 'lòng thành hơn' },
  { amount: 100000, han: '', note: 'trọn mâm lễ' },
]
export const DONATE_DEFAULT_VND = DONATE_TIERS[1].amount
// ý nghĩa: mức lễ được chọn sẵn khi mở màn donate | đơn vị: VND
// giá trị an toàn: phải là một mức trong DONATE_TIERS (mức 2 theo SHOT1 mockup)
export const DONATE_MIN = 5000
// ý nghĩa: sàn nhập tay (boss 02/09; BE vẫn 1k — Rules::DONATE_MIN_VND, BE chuẩn)
// đơn vị: VND | giá trị an toàn: ≥ BE min, ≤ DONATE_MAX
export const DONATE_MAX = 500000
// ý nghĩa: trần lễ | đơn vị: VND | nguồn thật: BE config('project.php') donate max — BE chuẩn
// giá trị an toàn: ~500k; cao hơn = bất thường với payOS/channel

// ── NHỊP THỜI GIAN (polling — hợp đồng API #6/#9) ───────────────────────────
export const AI_POLL_MS = 2000
// ý nghĩa: chu kỳ poll #6 (trạng thái bài luận) | đơn vị: ms | an toàn: ≥1000 (tránh spam BE)
export const AI_POLL_MAX_MS = 130000
// ý nghĩa: trần chờ bài luận rồi hiện "thử lại" | đơn vị: ms | an toàn: ≥ AI_POLL_MS*10
export const PAY_POLL_MS = 3000
// ý nghĩa: chu kỳ poll #9 (trạng thái thanh toán) | đơn vị: ms | an toàn: ≥2000
export const PAY_POLL_TIMEOUT_MS = 300000
// ý nghĩa: timeout phiên QR (hết hạn hiển "quá hạn") | đơn vị: ms | an toàn: ≥ PAY_POLL_MS*20
export const TOAST_TTL_MS = 4000
// ý nghĩa: thời gian toast tự biến mất (useToasts) | đơn vị: ms | an toàn: 3000..8000 (đủ đọc)

// ── NHỊP NGHI THỨC (UX bất biến — C-08/C-09, gate t_04394e77) ───────────────
export const MAGIC_SEQUENCE_MS = 1500
// ý nghĩa: SÀN reveal kết quả gieo (C-08) | đơn vị: ms | BẤT BIẾN UX — không được ngắn hơn
export const LINE_STAGGER_MS = 250
// ý nghĩa: nhịp xuất hiện từng hào ở bảng giải (04-ui §2.S2 B2) | đơn vị: ms | an toàn: 150..400
export const COIN_LAND_SWITCH_MS = 300
// ý nghĩa: MagicSequence — mốc chuyển cụm xu từ class 'fly' sang 'land' sau khi bay
// đơn vị: ms | BẮM CỨNG animation 'ms-fly-up' 0.3s trong <style> của MagicSequence —
// đổi 1 mình số này là lệch nhịp nhìn; sửa phải soi cả CSS. An toàn: = duration fly-up.
export const AUTO_PUSH_S3_MS = 600
// ý nghĩa: DrawView B3 — độ trễ auto-push S3 sau reveal (giữ nhịp nhìn symbol)
// đơn vị: ms | an toàn: 400..1000 (dài quá = khách mắc kẹt ở S2)

// ── HIỂN THỊ QR ─────────────────────────────────────────────────────────────
export const QR_SIZE_PX = 240
// ý nghĩa: cạnh ảnh QR VietQR render (PayQr) | đơn vị: px CSS | an toàn: ≥200 (quét ổn định
// trên camera điện thoại phổ thông); đổi = đổi cả attr width/height lẫn output qrcode

// ── CHỦ ĐỀ / CÂU HỎI ────────────────────────────────────────────────────────
export const TOPICS = ['duyen', 'tai_loc', 'xuat_hanh'] // C-02 — khóa theo BE enum
export const TOPIC_LABELS = { duyen: 'Tình duyên', tai_loc: 'Tài lộc', xuat_hanh: 'Xuất hành' }
// ── LUAN-V2 (SPEC §7, D3/D4, card t_b13fd2b9) — ô "Bạn đang vướng chuyện gì?" ──
export const QUESTION_MAX = 200
// ý nghĩa: trần câu hỏi SAU trim đếm unicode | đơn vị: ký tự
// nguồn thật: validation BE §4.1 (mb_strlen → 422) — BE chuẩn, FE chỉ chặn sớm
// giá trị an toàn: ≤ BE trần; đổi phải đổi cả BE cùng lúc
// D3: chip là GÓI GỢI Ý TEXT điền vào ô — KHÔNG đổi topic API (topic vẫn theo tab).
export const QUESTION_SUGGESTIONS = {
  duyen: ['chuyện tình cảm của em', 'bao giờ em có người', 'người ấy nghĩ gì về em'],
  tai_loc: ['chuyện tiền bạc của em dạo này', 'em có nên đầu tư lúc này', 'khi nào tài chính đỡ hơn'],
  xuat_hanh: ['em có nên đổi việc', 'chuyện công việc đang vướng', 'đi xa tuần này có ổn không'],
}
export const DISCLAIMER_TEXT =
  'Sản phẩm giải trí, tham khảo văn hoá — không phải nghiên cứu hay tư vấn số mệnh.'

// Nhãn giá hiển thị — SUY RA từ PRICE_UNLOCK_VND, đổi giá ở trên là đổi khắp UI.
// Đừng trả lại literal '29.000đ' (bài học drift CFG-FE t_130d6f4b).
export const PRICE_LABEL = `${fmtVnd(PRICE_UNLOCK_VND)}đ` // vd "29.000đ" — kiểu VN

// ── F7 Share-card (CAP-THE §2 BẢN CHỐT — copywriter-vn t_97365736, CEO DUYET 5/31/08) ──
// CẤM sửa wording: đổi chuỗi = đổi lever 4 SPEC-THE §7, phải quay lại card CAP-THE.
// Dấu "—" là em dash U+2014; placeholder duy nhất {hexagram_ten} (hexagram.ten, 03-api §2).
export const CAPTION_NATIVE = 'Hôm nay tôi là {hexagram_ten} — bạn là quẻ nào?' // Web Share (nút Chia sẻ)
export const CAPTION_1X1 = '{hexagram_ten} — bạn là quẻ nào?' // dòng phụ 1:1 + clipboard 1:1
