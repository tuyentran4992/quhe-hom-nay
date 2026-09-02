// F7-FE utils/shareCardCanvas.js — renderer canvas TỰ VẼ 2 lớp theo MOCKUP-CARD
// (KHÔNG html2canvas — SPEC-THE §3). An toàn scale: mọi toạ độ tính ở ĐƠN VỊ rộng 1080
// cho KHUNG 1:1 và 1080×1920 cho 9:16; nếu canvas thật cỡ khác → ctx.scale() đúng 1 lần
// rồi vẽ nguyên đơn vị (không nhân tay toạ độ — tránh double-scale).
// Tokens 04-ui §1: paper #F7F2E7 · paper2 #EFE6D3 · ink #1E1B18 · cinnabar #B33A2B
// · bamboo #3E5C48 · muted #5C554A. Font symbol: "Noto Serif TC" (self-host main.js).
// Story safe-area: đỉnh 250 · đáy 310 → text quan trọng nằm giữa 250..1610;
// dưới đáy CHỈ QR + URL (muted). 1:1: không QR, 3 chips (drop chip tràn), dòng phụ caption.
//
// ── NHÓM CANVAS (CFG-FE t_130d6f4b, cách 2 CEO duyệt): các số px DƯỚI ĐÂY CỐ Ý Ở LẠI
// ĐÂY thay vì về constants.js — chúng LÀ KIẾN TRÚC PIXEL CỦA BÀI VẼ: mọi toạ độ trong
// các hàm draw* tính tương đối lẫn nhau theo đơn vị UNIT=1080 (đổi 1 số mà không vẽ lại
// cả bố cục = vỡ layout canvas, không phải "đổi cấu hình kinh doanh"). constants.js chỉ
// chứa số NGHIỆP VỤ đổi được; đây là số HÌNH HỌC bám mockup đã duyệt — sửa kèm mockup.
export const TOKENS = {
  paper: '#F7F2E7',
  paper2: '#EFE6D3',
  ink: '#1E1B18',
  cinnabar: '#B33A2B',
  bamboo: '#3E5C48',
  muted: '#5C554A',
}
export const UNIT = 1080 // đơn vị thiết kế (chiều ngang mọi khung)
export const FRAME_9X16 = { key: '9x16', w: 1080, h: 1920 }
export const FRAME_1X1 = { key: '1x1', w: 1080, h: 1080 }
const SAFE_TOP = 250
const SAFE_BOTTOM = 310 // story: 1920-310 = 1610 là mép trên vùng đáy
const PAD_X = 84

const SERIF = '"Noto Serif TC", "Be Vietnam Pro", serif'
const SANS = '"Be Vietnam Pro", sans-serif'

/** Đo rộng text qua ctx đang-set font (đơn vị px). */
function textW(ctx, s) {
  return ctx.measureText(s).width
}

/**
 * Wrap theo word-boundary (không cắt giữa từ). maxLines vượt → dòng cuối thay '…'.
 * Dùng cho hook (story ≤4 dòng, feed ≤2 dòng — MOCKUP-CARD).
 */
export function wrapText(text, { maxWidth, measure, maxLines = 4 }) {
  const words = String(text ?? '').split(/\s+/).filter(Boolean)
  const lines = []
  let cur = ''
  for (const w of words) {
    const cand = cur ? `${cur} ${w}` : w
    if (measure(cand) <= maxWidth || !cur) {
      cur = cand
    } else {
      lines.push(cur)
      cur = w
      if (lines.length === maxLines - 1) break // phần còn lại dồn vào dòng cuối
    }
  }
  if (cur) lines.push(cur)
  if (lines.length > maxLines) lines.length = maxLines
  const consumed = lines.join(' ')
  if (consumed.length < String(text ?? '').trim().length && lines.length) {
    lines[lines.length - 1] = `${lines[lines.length - 1]}…`
  }
  return lines
}

/** Nhét chips trái→phải tới maxWidth; chạm tràn thì DỪNG (không nhảy con sau). */
export function pickChips(keywords, { maxWidth, measure, gap = 16, pad = 22 }) {
  const out = []
  let used = 0
  for (const k of keywords || []) {
    const w = measure(String(k)) + pad * 2
    if (used && used + gap + w >= maxWidth) break
    out.push(k)
    used = used ? used + gap + w : w
  }
  return out
}

/** Line kẻ ngang quanh ngày "─ 30/08/2026 ─" bằng 2 nét mực ngắn (không phải text box). */
function rule(ctx, x1, x2, y) {
  ctx.beginPath()
  ctx.moveTo(x1, y)
  ctx.lineTo(x2, y)
  ctx.stroke()
}

function roundRect(ctx, x, y, w, h, r) {
  if (typeof ctx.roundRect === 'function') {
    ctx.beginPath()
    ctx.roundRect(x, y, w, h, r)
    return
  }
  // fallback path (jsdom ctx cũ) — cùng hình dạng
  ctx.beginPath()
  ctx.moveTo(x + r, y)
  ctx.arcTo?.(x + w, y, x + w, y + h, r)
  ctx.lineTo(x + w, y + h - r)
  ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h)
  ctx.lineTo(x + r, y + h)
  ctx.quadraticCurveTo(x, y + h, x, y + h - r)
  ctx.lineTo(x, y + r)
  ctx.quadraticCurveTo(x, y, x + r, y)
  ctx.closePath()
}

/** Lớp 1+2: nền paper + gradient texture nhẹ (vân giấy, không ảnh nặng). */
function paintPaper(ctx, W, H) {
  ctx.fillStyle = TOKENS.paper
  ctx.fillRect(0, 0, W, H)
  const g = ctx.createLinearGradient(0, 0, W, H)
  g.addColorStop(0, 'rgba(255,255,255,0.10)')
  g.addColorStop(0.5, 'rgba(168,128,42,0.03)')
  g.addColorStop(1, 'rgba(92,85,74,0.06)')
  ctx.fillStyle = g
  ctx.fillRect(0, 0, W, H)
}

function chipRow(ctx, chips, cx, y, measureFont) {
  ctx.font = measureFont
  const gap = 16
  const pad = 22
  const h = 52
  const ws = chips.map((k) => textW(ctx, String(k)) + pad * 2)
  let x = cx - ws.reduce((a, b) => a + b, 0) / 2 - (gap * (chips.length - 1)) / 2
  for (let i = 0; i < chips.length; i++) {
    ctx.fillStyle = TOKENS.paper2
    roundRect(ctx, x, y, ws[i], h, h / 2)
    ctx.fill()
    ctx.strokeStyle = TOKENS.bamboo
    ctx.lineWidth = 2
    roundRect(ctx, x, y, ws[i], h, h / 2)
    ctx.stroke()
    ctx.fillStyle = TOKENS.ink
    ctx.textAlign = 'center'
    ctx.fillText(String(chips[i]), x + ws[i] / 2, y + h / 2 + 9)
    x += ws[i] + gap
  }
}

/**
 * VẺ ĐỒNG BỘ 1 thẻ lên ctx (unit 1080). Ném tiếp mọi lỗi → caller bắt → fallback E1.
 * opts: { frame, qrImage (9:16), caption1x1 (1:1), unit=1080 }
 * model: output buildCardModel() — CHỈ vẽ field whitelist, không đọc gì khác.
 */
export function drawShareCard(ctx, model, opts = {}) {
  const frame = opts.frame || FRAME_9X16
  const unit = opts.unit || UNIT
  const story = frame.key !== '1x1'
  const W = unit // vẽ ở đơn vị thiết kế
  const H = story ? 1920 : 1080

  // scale AN TOÀN: vẽ hệ unit, canvas thật có thể to hơn (device pixel ratio)
  ctx.save()
  const s = frame.w / unit
  ctx.scale(s, s)

  paintPaper(ctx, W, H)

  const cx = W / 2
  const bottomEdge = story ? H - SAFE_BOTTOM : H // 1610 story — dưới là đáy an toàn (QR/URL)

  // ── disclaimer: story để sát mép trên vùng an toàn (mockup); 1:1 để chân thẻ ──
  ctx.textAlign = 'center'
  if (story) {
    ctx.font = `400 26px ${SANS}`
    ctx.fillStyle = TOKENS.muted
    ctx.fillText(model.disclaimer, cx, 210)
  }

  // ── symbol cinnabar ~300px Noto Serif TC ──
  ctx.font = `600 300px ${SERIF}`
  ctx.fillStyle = TOKENS.cinnabar
  ctx.fillText(model.symbol || '', cx, story ? 600 : 300)

  // ── tên quẻ ──
  ctx.font = `600 72px ${SANS}`
  ctx.fillStyle = TOKENS.ink
  ctx.fillText(model.ten, cx, story ? 720 : 390)

  // ── ngày gieo muted + 2 nét ngang ──
  ctx.font = `400 30px ${SANS}`
  ctx.fillStyle = TOKENS.muted
  const dY = story ? 790 : 450
  ctx.fillText(model.drawn_date, cx, dY)
  ctx.strokeStyle = TOKENS.muted
  ctx.lineWidth = 2
  const dw = textW(ctx, model.drawn_date) / 2 + 24
  rule(ctx, cx - dw - 60, cx - dw, dY - 10)
  rule(ctx, cx + dw, cx + dw + 60, dY - 10)

  // ── CÂU CHÍNH (hook) — cỡ lớn nhất sau symbol; E6 minimal → bỏ khối này ──
  if (model.hook_text) {
    const hook = story ? model.hook?.text || model.hook_text : model.hook?.text_1x1 || model.hook_text
    ctx.font = `500 ${story ? 52 : 46}px ${SANS}`
    const maxLines = story ? 4 : 2
    const lines = wrapText(hook, { maxWidth: W - PAD_X * 2, measure: (t) => textW(ctx, t), maxLines })
    ctx.fillStyle = TOKENS.ink
    const lh = story ? 52 * 1.5 : 46 * 1.45
    const y0 = story ? 920 : 560
    lines.forEach((ln, i) => ctx.fillText(ln, cx, y0 + i * lh))
  }

  // ── keyword chips: 9:16 đủ 4; 1:1 tối đa 3, drop chip tràn width ──
  const chipsAll = (model.keywords || []).slice(0, story ? 4 : 3)
  ctx.font = `500 28px ${SANS}`
  const chips = pickChips(chipsAll, {
    maxWidth: W - PAD_X * 2,
    measure: (t) => textW(ctx, String(t)),
    gap: 16,
    pad: 22,
  })
  if (chips.length) chipRow(ctx, chips, cx, story ? 1300 : 680, `500 28px ${SANS}`)

  if (!story) {
    // ── 1:1: dòng phụ CAPTION_1x1 + disclaimer muted, KHÔNG QR ──
    ctx.font = `500 34px ${SANS}`
    ctx.fillStyle = TOKENS.ink
    ctx.fillText(opts.caption1x1 || '', cx, 800)
    ctx.font = `400 26px ${SANS}`
    ctx.fillStyle = TOKENS.muted
    ctx.fillText(model.disclaimer, cx, 900)
  } else {
    // ── 9:16 đáy safe-area (≥1610): CHỈ QR + URL muted ──
    const qr = opts.qrImage
    if (qr) {
      const q = 220
      const qrX = cx - q - 60
      const qrY = H - SAFE_BOTTOM + 45
      ctx.drawImage(qr, qrX, qrY, q, q)
      ctx.font = `400 34px ${SANS}`
      ctx.fillStyle = TOKENS.muted
      ctx.textAlign = 'left'
      // BUG-F7-UX1 (t_cf7e69e9): URL từng vẽ tại cx-q+40=360 ĐÈ mép phải QR (phải=480,
      // ký tự đầu nằm trong cột module ngoài cùng — QA-R2 t_414aee5e soi pixel 3/3 ảnh
      // 9x16). Fix: URL đứng SANG PHẢI ảnh QR, gap 20px ≥1 module (PNG QR đã ngậm
      // margin:1 module bên trong — không được chạm mép ảnh). Dài quá chỗ còn lại →
      // cắt theo ký tự + '…' (chỉ ảnh hiển thị; link copy/QR text không đổi).
      const urlX = qrX + q + 20
      const urlMax = W - PAD_X - urlX
      ctx.fillText(clipUrl(shortUrl(model.url), urlMax, (t) => textW(ctx, t)), urlX, qrY + q / 2 + 12)
      ctx.textAlign = 'center'
    }
  }

  ctx.restore()
}

/**
 * BUG-F7-QA2 (t_b33b9f28 · SPEC-THE §3 PNG ≤500KB): render thật máy thấp đo 695–964KB.
 * Nguyên nhân: gradient texture lớp nền + antialiasing chữ → ~2.000 màu smooth, PNG
 * full-color deflate phình to. Fix theo chỉ đạo merge-card t_a2ef281b muc 3b =
 * palette quantization / flatten texture, KHÔNG giảm resolution (1080×1920 giữ nguyên).
 * 5-bit/kênh RGB bit-replication (32 mức/kênh = ≤32768 bảng nhưng pixel thực tế gom
 * ~vài trăm → deflate thắng lớn). Mép dịch ≤3 LSB — token màu #F7F2E7 giữ đúng trực quan.
 * ALPHA giữ 8-bit (QR không vỡ cạnh). Đo offline Pillow 6 PNG QA mb8: 926–964→85–101KB,
 * 683–701→64–73KB (cùng mapping (v>>3)<<3|(v>>5)). In-place, trả chính ImageData.
 */
export const PNG_QUANT_BITS = 5
export function quantizeImageData(imageData, bits = PNG_QUANT_BITS) {
  const keep = 8 - bits
  const mask = (0xff << keep) & 0xff // ví dụ 5-bit → 0xF8
  const data = imageData.data
  for (let i = 0; i < data.length; i += 4) {
    for (let c = 0; c < 3; c++) {
      const v = data[i + c]
      // bit-replication (như Pillow posterize): giữ `bits` cao, lặp vào `keep` thấp
      data[i + c] = (v & mask) | ((v & mask) >> bits)
    }
    // alpha (i+3) giữ 8-bit
  }
  return imageData
}

/** URL hiển thị dưới QR — cắt scheme cho gọn, KHÔNG đổi link copy. */
function shortUrl(url) {
  return String(url || '').replace(/^https?:\/\//, '')
}

/**
 * BUG-F7-UX1: kẹp chuỗi URL vừa chỗ còn lại bên phải QR (đo bằng ctx thật). Cắt theo
 * ký tự + '…' (đếm cả dấu ba chấm vào width). Trả nguyên bản khi vừa. CHỈ ảnh hiển thị —
 * qr_text/link clipboard không qua hàm này.
 */
export function clipUrl(text, maxWidth, measure) {
  const s = String(text ?? '')
  if (measure(s) <= maxWidth) return s
  let lo = 0
  let hi = s.length
  while (lo < hi) {
    const mid = Math.ceil((lo + hi) / 2)
    if (measure(`${s.slice(0, mid)}…`) <= maxWidth) lo = mid
    else hi = mid - 1
  }
  return lo > 0 ? `${s.slice(0, lo)}…` : '…'
}

/**
 * Async helper cho ShareCardView: canvas offscreen cỡ thật → {canvas, blob, dataUrl, ms}.
 * Chờ document.fonts.ready TRƯỚC khi vẽ (SPEC §3 — không font-escape). Lỗi NÉM tiếp →
 * caller (view) lo fallback E1 + share_card_error.
 */
export async function renderFrame(model, frame, { qrText } = {}) {
  const t0 = performance.now()
  if (document.fonts?.ready) await document.fonts.ready
  const canvas = document.createElement('canvas')
  canvas.width = frame.w
  canvas.height = frame.h
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('canvas_2d_unavailable')
  let qrImage = null
  if (frame.key === '9x16' && qrText) {
    qrImage = await makeQrImage(qrText)
  }
  const caption1x1 = frame.key === '1x1' ? model.caption_1x1 || '' : undefined
  drawShareCard(ctx, model, { frame, qrImage, caption1x1 })
  // BUG-F7-QA2: flatten gradient/texture về palette 5-bit TRƯỚC encode → PNG ≤500KB
  // (SPEC-THE §3, chỉ đạo t_a2ef281b muc 3b — KHÔNG giảm resolution). Best-effort:
  // ctx/môi trường không có ImageData API (jsdom, tainted canvas) → bỏ qua, KHÔNG ném
  // (đừng biến tối ưu weight thành lỗi E1 fallback).
  try {
    if (typeof ctx.getImageData === 'function' && typeof ctx.putImageData === 'function') {
      const id = ctx.getImageData(0, 0, canvas.width, canvas.height)
      quantizeImageData(id)
      ctx.putImageData(id, 0, 0)
    }
  } catch {
    /* bỏ qua quantize — ảnh to nhưng render đúng */
  }
  const blob = await new Promise((res, rej) =>
    canvas.toBlob((b) => (b ? res(b) : rej(new Error('toBlob_empty'))), 'image/png'),
  )
  const dataUrl = canvas.toDataURL('image/png')
  return { canvas, blob, dataUrl, ms: Math.round(performance.now() - t0) }
}

/** QR PNG (dep qrcode có sẵn — PayQr dùng) thành Image để drawImage. */
async function makeQrImage(text) {
  const QRCode = (await import('qrcode')).default
  const url = await QRCode.toDataURL(text, {
    margin: 1,
    width: 220,
    color: { dark: TOKENS.ink, light: TOKENS.paper },
  })
  return new Promise((res, rej) => {
    const img = new Image()
    img.onload = () => res(img)
    img.onerror = () => rej(new Error('qr_image_failed'))
    img.src = url
  })
}
