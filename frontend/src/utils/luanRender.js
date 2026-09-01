// SPEC-LUAN-V2 §6(c) — lane render-chính-thức (card t_dec9349a).
// PromptBuilder (backend/app/Domain/PromptBuilder.php:105-107) bắt bài luận trả về
// kèm 3 marker thô [Hoàn cảnh] / [Vì sao khuyên vậy] / [Việc nên làm cụ thể...].
// Marker là HỢP ĐỒNG hiển thị: FE tách chúng thành heading sạch, người dùng KHÔNG
// bao giờ thấy ngoặc vuông. Nguồn logic hợp nhất 1 chỗ từ patch preview-only
// (outbox/t_c146e45a/preview/topicgate-marker-render.patch, chưa commit) — component
// TopicGate.vue chỉ render, parsing nằm đây theo luật "logic ra util".
// LUAN-V3 (card t_a6a2cba9) đổi heading cổ hóa → chỉ sửa constants dưới này.
//
// BUG-V3-2 (card fix t_127a3094, nguồn QA t_b8a95f0a): 2/6 bài THẬT (ai_jobs 50/51)
// model trả marker kèm prefix markdown — "### [Hoàn cảnh]" / "## [Hoàn cảnh]" —
// regex cũ không khớp → cả bài thành 1 khối heading '' → người dùng thấy nguyên
// văn "### [" (vỡ hợp đồng §6c ~33% lượt). Nay 3 regex chấp nhận prefix
// "#{1,4} + ít nhất 1 space/tab" (buộc space để dòng thường "#10 người hỏi..."
// không bị xử nhầm — hợp đồng "không mất chữ"). Tương thích ngược 100%: marker
// sạch kiểu DB_52..55 giữ nguyên hành vi.
// QUYẾT ĐỊNH "một chỗ duy nhất" (card ý 2): FE CHỈ xử lý marker + `#`; `**` giữ
// NGUYÊN VĂN vì TopicGate render whitespace-pre-wrap — nếu về sau muốn in đậm
// thật thì BE normalize một chỗ trước khi lưu result, CẤM cả hai nơi cùng làm.

const MARKER_PREFIX = /^\s*(?:#{1,4}[ \t]+)?\[?\s*/ // nguồn tách riêng, 3 regex dùng chung

export const LUAN_BLOCKS = [
  { re: new RegExp(`${MARKER_PREFIX.source}Hoàn cảnh\\s*\\]?\\s*[—–-]?\\s*`, 'i'), heading: 'Hoàn cảnh' },
  { re: new RegExp(`${MARKER_PREFIX.source}Vì sao khuyên vậy\\s*\\]?\\s*[—–-]?\\s*`, 'i'), heading: 'Vì sao khuyên vậy' },
  { re: new RegExp(`${MARKER_PREFIX.source}Việc nên làm[^\\]\\n]*\\]?\\s*[—–-]?\\s*`, 'i'), heading: 'Việc nên làm cụ thể tuần này' },
]

export const LUAN_HEADINGS = LUAN_BLOCKS.map((b) => b.heading)

// Dòng đầu block đôi khi vẫn còn "### " nếu model đóng marker kiểu khác biệt nhỏ;
// strip marker xong thì không còn lý do để lộ dấu thăng — đây là phần "strip
// marker ^#{1,4}\s* còn sót đầu dòng heading" của card.
const stripResidualHash = (line) => line.replace(/^#{1,4}[ \t]+/, '')

// text thô từ API → [{ heading, text }] theo thứ tự xuất hiện.
// - Khối không marker (bài trơn / model dẫn thừa trước marker đầu) → heading '' ,
//   KHÔNG được bịa mất chữ.
// - Toàn whitespace/null → [].
export function parseLuan(text) {
  if (!text) return []
  const blocks = []
  let current = { heading: '', lines: [] }
  for (const rawLine of text.split('\n')) {
    const line = stripResidualHash(rawLine)
    const marker = LUAN_BLOCKS.find((m) => m.re.test(line))
    if (marker) {
      if (current.lines.some((l) => l.trim())) blocks.push(current)
      current = { heading: marker.heading, lines: [line.replace(marker.re, '')] }
    } else {
      current.lines.push(line)
    }
  }
  if (current.lines.some((l) => l.trim())) blocks.push(current)
  return blocks.map((b) => ({
    heading: b.heading,
    text: b.lines.join('\n').replace(/^\n+/, '').replace(/\s+$/g, ''),
  }))
}
