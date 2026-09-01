// SPEC-LUAN-V2 §6(c) — lane render-chính-thức (card t_dec9349a).
// PromptBuilder (backend/app/Domain/PromptBuilder.php:105-107) bắt bài luận trả về
// kèm 3 marker thô [Hoàn cảnh] / [Vì sao khuyên vậy] / [Việc nên làm cụ thể...].
// Marker là HỢP ĐỒNG hiển thị: FE tách chúng thành heading sạch, người dùng KHÔNG
// bao giờ thấy ngoặc vuông. Nguồn logic hợp nhất 1 chỗ từ patch preview-only
// (outbox/t_c146e45a/preview/topicgate-marker-render.patch, chưa commit) — component
// TopicGate.vue chỉ render, parsing nằm đây theo luật "logic ra util".
// LUAN-V3 (card t_a6a2cba9) đổi heading cổ hóa → chỉ sửa constants dưới này.

export const LUAN_BLOCKS = [
  { re: /^\s*\[?\s*Hoàn cảnh\s*\]?\s*[—–-]?\s*/i, heading: 'Hoàn cảnh' },
  { re: /^\s*\[?\s*Vì sao khuyên vậy\s*\]?\s*[—–-]?\s*/i, heading: 'Vì sao khuyên vậy' },
  { re: /^\s*\[?\s*Việc nên làm[^\]\n]*\]?\s*[—–-]?\s*/i, heading: 'Việc nên làm cụ thể tuần này' },
]

export const LUAN_HEADINGS = LUAN_BLOCKS.map((b) => b.heading)

// text thô từ API → [{ heading, text }] theo thứ tự xuất hiện.
// - Khối không marker (bài trơn / model dẫn thừa trước marker đầu) → heading '' ,
//   KHÔNG được bịa mất chữ.
// - Toàn whitespace/null → [].
export function parseLuan(text) {
  if (!text) return []
  const blocks = []
  let current = { heading: '', lines: [] }
  for (const line of text.split('\n')) {
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
