// Hằng số FE — nguồn: 03-api.md §0 + mockup v2 (DESIGN-NOTES). FE hiển thị, BE enforce.
export const PRICE_UNLOCK_VND = 29000 // C-05
export const DONATE_OPTIONS = [1000, 2000, 5000, 50000] // C-07 khoảng 1000..500000
export const DONATE_MIN = 1000
export const DONATE_MAX = 500000
export const MAGIC_SEQUENCE_MS = 1500 // C-08 — bất biến UX, không được ngắn hơn
export const LINE_STAGGER_MS = 250 // 04-ui §2.S2 B2: mỗi hào ~250ms
export const AI_POLL_MS = 2000 // #6 poll 2s
export const AI_POLL_MAX_MS = 130000 // tối đa 130s rồi hiện thử-lại
export const PAY_POLL_MS = 3000 // #9 poll 3s
export const PAY_POLL_TIMEOUT_MS = 300000 // timeout 5 phút
export const TOPICS = ['duyen', 'tai_loc', 'xuat_hanh'] // C-02
export const TOPIC_LABELS = { duyen: 'Tình duyên', tai_loc: 'Tài lộc', xuat_hanh: 'Xuất hành' }
// ── LUAN-V2 (SPEC §7, D3/D4, card t_b13fd2b9) — ô "Bạn đang vướng chuyện gì?" ──
// 200 = trần SAU trim đếm unicode, khớp validation BE §4.1 (mb_strlen → 422).
export const QUESTION_MAX = 200
// D3: chip là GÓI GỢI Ý TEXT điền vào ô — KHÔNG đổi topic API (topic vẫn theo tab).
export const QUESTION_SUGGESTIONS = {
  duyen: ['chuyện tình cảm của em', 'bao giờ em có người', 'người ấy nghĩ gì về em'],
  tai_loc: ['chuyện tiền bạc của em dạo này', 'em có nên đầu tư lúc này', 'khi nào tài chính đỡ hơn'],
  xuat_hanh: ['em có nên đổi việc', 'chuyện công việc đang vướng', 'đi xa tuần này có ổn không'],
}
export const DISCLAIMER_TEXT =
  'Sản phẩm giải trí, tham khảo văn hoá — không phải nghiên cứu hay tư vấn số mệnh.'
export const PRICE_LABEL = '29.000đ' // format vn của C-05

// ── F7 Share-card (CAP-THE §2 BẢN CHỐT — copywriter-vn t_97365736, CEO DUYET 5/31/08) ──
// CẤM sửa wording: đổi chuỗi = đổi lever 4 SPEC-THE §7, phải quay lại card CAP-THE.
// Dấu "—" là em dash U+2014; placeholder duy nhất {hexagram_ten} (hexagram.ten, 03-api §2).
export const CAPTION_NATIVE = 'Hôm nay tôi là {hexagram_ten} — bạn là quẻ nào?' // Web Share (nút Chia sẻ)
export const CAPTION_1X1 = '{hexagram_ten} — bạn là quẻ nào?' // dòng phụ 1:1 + clipboard 1:1
