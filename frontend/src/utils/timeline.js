// timeline.js — FE-3XU: LỚP DỮ LIỆU THUẦN của nghi thức PA1 "Một chạm · Xu quyết từng hào".
// Nguồn duy nhất: mockup đã duyệt /data/agents/ux-ui/outbox/t_029084d1/mockup-3xu.html
// (playPA1 + notes pa1 trong de-xuat.md §2). Tách lớp data/lớp render theo 04-ui §3:
// MagicSequence chỉ render theo mốc ở đây, đổi animation không đụng logic.
//
// BẤT BIẾN (card FE-3XU + gate t_04394e77):
// - C-08: reveal KHÔNG trước 1500ms kể từ chạm — lịch PA1 (3060ms) đã xa sàn;
//   durationMs props vẫn bị kẹp max(durationMs, MAGIC_SEQUENCE_MS) như bản cũ.
// - 6 hào vẽ DƯỚI→TRÊN, mỗi hào đúng 1 cú gieo 3 xu (cụm i bay tại flyAt[i]).
// - Hào động (rolled 6|9 — C-09) nháy son 2 nhịp + dấu 動 tại dynoAt, TRƯỚC reveal.
// - prefers-reduced-motion: GIỮ nguyên mọi mốc thời gian, chỉ bỏ chuyển động thừa.
import { MAGIC_SEQUENCE_MS } from '../constants.js'

// Lịch PA1 gốc từ mockup (ms, tính từ chạm),节拍 360ms/hào:
// fly 0.26; fly_i = draw_{i-1}; draw_i = 0.56 + 0.36*i; dyno 2.56; reveal 3.06; S3 3.60.
// ── NHÓM TIMELINE (CFG-FE t_130d6f4b, cách 2 CEO duyệt): các PA1_* CỐ Ý Ở LẠI ĐÂY —
// chúng là MỘT LỊCH đồng bộ theo công thức (fly_i = draw_{i-1}, reveal = dyno+500…),
// tính toán dây chuyền lẫn nhau từ mockup-3xu đã gate t_04394e77 chốt; đổi lẻ 1 số =
// breaks bất biến C-08/C-09 đã test, không phải knob nghiệp vụ. Sàn thời gian thật
// (C-08) đã nằm ở constants.MAGIC_SEQUENCE_MS — chỗ đó mới là surface đổi được.
export const PA1_FLY0_MS = 260
export const PA1_DRAW0_MS = 560
export const PA1_BEAT_MS = 360
export const PA1_DYNO_OFFSET_MS = 200 // dynoAt = drawAt[5] + 200 = 2560
export const PA1_REVEAL_OFFSET_MS = 500 // revealAt = dynoAt + 500 = 3060
export const PA1_HOLD_MS = 540 // s3At = revealAt + 540 ≈ 3600 (auto-push giữ nhịp đọc)

/**
 * Xây toàn bộ mốc nghi thức từ durationMs (sàn C-08 = MAGIC_SEQUENCE_MS).
 * Lịch PA1 chuẩn khi durationMs ≤ 3060 (mọi giá trị thật dùng hiện nay).
 * Nếu caller đẩy sàn LỚN hơn lịch (vd 4000) → reveal trễ đúng bằng sàn, các nhịp
 * trước vẫn nguyên — nguyên tắc "nối mạch không nhảy cỡ", không bao giờ sớm hơn.
 */
export function pa1Timeline(durationMs) {
  const floor = Math.max(Number(durationMs) || 0, MAGIC_SEQUENCE_MS) // kẹp cứng C-08
  const flyAt = Array.from({ length: 6 }, (_, i) =>
    i === 0 ? PA1_FLY0_MS : PA1_DRAW0_MS + PA1_BEAT_MS * (i - 1),
  )
  const drawAt = Array.from({ length: 6 }, (_, i) => PA1_DRAW0_MS + PA1_BEAT_MS * i)
  const dynoAt = drawAt[5] + PA1_DYNO_OFFSET_MS // 2560
  const revealAt = Math.max(dynoAt + PA1_REVEAL_OFFSET_MS, floor) // 3060
  return { flyAt, drawAt, dynoAt, revealAt, s3At: revealAt + PA1_HOLD_MS, floorMs: floor }
}

/** rolled value → có phải hào động không (C-09: 6 = Lão Âm động, 9 = Lão Dương động). */
export function isChanging(rolled) {
  return rolled === 6 || rolled === 9
}

/** lines_rolled (dưới→trên) → danh sách vị trí 1-based của hào động, giữ thứ tự dưới→trên. */
export function dynoLabels(lines) {
  return (lines || []).map((v, i) => (isChanging(v) ? i + 1 : 0)).filter(Boolean)
}

/**
 * Nhãn draw-status theo beat — copy bám sát mockup (không tự sáng tạo).
 * @param t ms kể từ chạm · @param ten tên quẻ (rỗng khi API chưa về) · @param symbol
 * @param dynos vị trí hào động 1-based (rỗng = chưa biết/quẻ tĩnh)
 */
export function statusAt(t, ten, symbol, dynos = []) {
  const tl = pa1Timeline(MAGIC_SEQUENCE_MS)
  if (t >= tl.revealAt) return ten ? `${ten} ${symbol || ''}`.trim() : 'Đang mở quẻ…'
  if (t >= tl.dynoAt && dynos.length) return `Hào ${dynos.join('·')} động — dấu 動`
  // draw thắng fly ở cùng timestamp (mockup đăng beat draw trước; fly cụm ≥2 không đổi status)
  for (let i = 5; i >= 0; i--) if (t >= tl.drawAt[i]) return i === 5 ? 'Hào 6 · 6 — đủ quẻ' : `Hào ${i + 1} · 6`
  if (t >= tl.flyAt[0]) return 'Tung xu — hào 1'
  return 'Vê nhẹ bó xu…'
}
