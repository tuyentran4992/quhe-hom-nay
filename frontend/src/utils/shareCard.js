// Tiện ích logic thẻ chia sẻ — F7-FE. Nguồn: SPEC-THE §2/§6, MOCKUP-CARD, CAP-THE §2.
// TÁCH NGOÀI component để vitest thuần (không cần DOM). Quy tắc "câu nào được show"
// đặt ở đây = bản sao phía FE của ShareLinkService::buildCardPayload() (BE cùng luật).

/** Disclaimer IN TRÊN THẺ — wording ngắn 04-ui/MOCKUP-CARD (khác DISCLAIMER_TEXT full ở app). */
export const CARD_DISCLAIMER = 'Giải trí · tham khảo văn hoá'

/** Trần ký tự câu chính story 9:16 (SPEC-THE §2) và feed 1:1 (siết hơn — MOCKUP-CARD). */
export const HOOK_MAX_STORY = 80
export const HOOK_MAX_FEED = 60

const PUNCT = [',', '.', ';', ':', '!', '?', '…']

/**
 * Cắt text về ≤ `max` ký tự tại ranh giới CÂU/DAU PHẨY gần nhất, không cắt giữa từ.
 * - ≤max → nguyên văn (trần là ≤, không phải <).
 * - ưu tiên ranh giới chấm/phẩy cuối cùng ≤max (GIỮ dấu câu);
 * - không có dấu câu nào vừa → cắt tại khoảng trắng cuối ≤max (ranh giới từ);
 * - cả 2 đều không có (1 từ dính dài >max) → null = caller về thẻ tối giản (E6).
 */
export function clip80(text, max = HOOK_MAX_STORY) {
  const s = String(text ?? '').trim()
  if (!s) return null
  if (s.length <= max) return s
  // 1) ranh giới câu/dấu phẩy gần nhất (kí tự dấu câu nằm tại index i → giữ nó: slice(0, i+1))
  for (let i = Math.min(max, s.length - 1); i >= 0; i--) {
    if (PUNCT.includes(s[i])) return s.slice(0, i + 1)
  }
  // 2) ranh giới từ (khoảng trắng) gần nhất — không cắt giữa từ
  const cut = s.lastIndexOf(' ', max)
  if (cut > 0) return s.slice(0, cut)
  // 3) hết cách → E6: caller dựng thẻ tối giản symbol+tên
  return null
}

/** Vế ĐẦU của đại ý — trước em dash "—" hoặc dấu phẩy (TH2, SPEC-THE §2). */
export function firstClause(daiCi) {
  const s = String(daiCi ?? '').trim()
  const iDash = s.indexOf('\u2014') // em dash U+2014
  const iComma = s.indexOf(',')
  const cands = [iDash, iComma].filter((i) => i > -1)
  if (!cands.length) return s
  return s.slice(0, Math.min(...cands)).trim()
}

/**
 * Dựng model nội dung thẻ từ dữ liệu S3 hiện hữu (draw #3/#4 + hexagram #2 + hào động #2b).
 * Luật đúng F7-CONTRACT §2: TH1 = `nghia` hào động ĐẦU TIÊN (sơ→thượng); TH2 = vế đầu dai_ci;
 * E6 = không cắt nổi → tối giản (chỉ symbol + tên). KHÔNG BAO GIỜ mang free_content/han/
 * quoc_am/luận sâu vào model (SPEC-THE §2 — chống lộ giá trị).
 */
export function buildCardModel({ draw, hexagram, haoDong, url = '', token = null }) {
  const changing = (draw?.changing_lines || []).map(Number).sort((a, b) => a - b)
  let hook = null
  let source = 'minimal'
  if (changing.length) {
    // hào động ĐẦU TIÊN tính từ sơ (vi nhỏ nhất) — mảng có thể chưa thứ tự
    const first = (haoDong || [])
      .filter((t) => changing.includes(Number(t.vi)))
      .slice()
      .sort((a, b) => Number(a.vi) - Number(b.vi))[0]
    const text = clip80(first?.nghia || '', HOOK_MAX_STORY)
    if (text) {
      hook = { text, text_1x1: clip80(first.nghia, HOOK_MAX_FEED) || text, source: 'hao_dong' }
      source = 'hao_dong'
    }
  }
  if (!hook) {
    const text = clip80(firstClause(hexagram?.dai_ci), HOOK_MAX_STORY)
    if (text) {
      hook = { text, text_1x1: clip80(firstClause(hexagram?.dai_ci), HOOK_MAX_FEED) || text, source: 'dai_ci' }
      source = 'dai_ci'
    }
  }
  return {
    hexagram_id: hexagram?.id ?? draw?.hexagram_id ?? null,
    symbol: hexagram?.symbol || '',
    ten: hexagram?.ten || '',
    drawn_date: fmtDateDDMM(draw?.drawn_date),
    hook,
    hook_text: hook?.text ?? null,
    hook_source: source,
    keywords: (hexagram?.keywords || []).slice(0, 4),
    disclaimer: CARD_DISCLAIMER,
    qr_text: url,
    url,
    token,
    minimal: !hook,
    draw_id: draw?.id ?? null,
    has_dynamic_line: changing.length > 0,
  }
}

/** "YYYY-MM-DD" → "dd/MM/yyyy" (mũi tên ngày theo server VN — reuse logic format.js). */
function fmtDateDDMM(iso) {
  if (!iso) return ''
  const [y, m, d] = String(iso).slice(0, 10).split('-')
  return d && m && y ? `${d}/${m}/${y}` : String(iso)
}

/** Thay placeholder {hexagram_ten} — CAP-THE §4: CHỈ placeholder này, string nguyên ký tự. */
export function renderCaption(template, hexagramTen) {
  return String(template ?? '').replaceAll('{hexagram_ten}', String(hexagramTen ?? ''))
}
