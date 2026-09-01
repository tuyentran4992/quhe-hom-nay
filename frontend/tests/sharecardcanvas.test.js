// F7-FE utils/shareCardCanvas.js — TDD RED. Renderer canvas TỰ VẼ 2 lớp (KHÔNG html2canvas),
// 2 cỡ 1080×1920 + 1080×1080 theo MOCKUP-CARD. An toàn scale: vẽ ở đơn vị rộng 1080 rồi scale.
// Tokens (04-ui §1 / SPEC-THE §3): paper #F7F2E7, ink #1E1B18, cinnabar #B33A2B (symbol ~300px
// Noto Serif TC), chips viền bamboo #3E5C48 nền paper2, ngày muted #5C554A.
// Story safe-area: đỉnh 250 đáy 310 — text quan trọng KHÔNG nằm trong; chỉ QR+URL dưới đáy.
// 9:16 có QR; 1:1 KHÔNG QR + dòng phụ CAPTION_1x1 + 3 chips (bỏ chip 4 nếu tràn).
import { describe, it, expect, vi } from 'vitest'
import {
  FRAME_9X16,
  FRAME_1X1,
  drawShareCard,
  wrapText,
  pickChips,
} from '../src/utils/shareCardCanvas.js'

// ctx mock ghi lại mọi lệnh vẽ + đo text theo width ~ 0.52*size mỗi ký tự (giống font rộng)
function mkCtx({ fails = false } = {}) {
  const calls = []
  const ctx = {
    calls,
    canvas: { width: 1080, height: 1920 },
    save: vi.fn(() => calls.push(['save'])),
    restore: vi.fn(() => calls.push(['restore'])),
    scale: vi.fn((sx, sy) => calls.push(['scale', sx, sy])),
    translate: vi.fn(),
    fillRect: vi.fn((...a) => calls.push(['fillRect', ...a])),
    strokeRect: vi.fn((...a) => calls.push(['strokeRect', ...a])),
    roundRect: vi.fn((...a) => calls.push(['roundRect', ...a])),
    beginPath: vi.fn(() => calls.push(['beginPath'])),
    moveTo: vi.fn(),
    lineTo: vi.fn(),
    closePath: vi.fn(),
    arc: vi.fn(),
    quadraticCurveTo: vi.fn(),
    bezierCurveTo: vi.fn(),
    createLinearGradient: vi.fn(() => {
      const g = { addColorStop: vi.fn((...a) => calls.push(['stop', ...a])) }
      return g
    }),
    createRadialGradient: vi.fn(() => {
      const g = { addColorStop: vi.fn((...a) => calls.push(['stop', ...a])) }
      return g
    }),
    fill: vi.fn(() => calls.push(['fill'])),
    stroke: vi.fn(() => calls.push(['stroke'])),
    fillText: vi.fn((t, x, y) => calls.push(['fillText', t, x, y])),
    strokeText: vi.fn(),
    drawImage: vi.fn((...a) => calls.push(['drawImage', ...a])),
    measureText: vi.fn((t) => ({ width: String(t).length * 13 })),
    clip: vi.fn(),
  }
  let fillStyle = '#000'
  Object.defineProperty(ctx, 'fillStyle', {
    get: () => fillStyle,
    set: (v) => {
      fillStyle = v
      calls.push(['fillStyle', v])
    },
  })
  let strokeStyle = '#000'
  Object.defineProperty(ctx, 'strokeStyle', {
    get: () => strokeStyle,
    set: (v) => {
      strokeStyle = v
      calls.push(['strokeStyle', v])
    },
  })
  let font = '10px sans-serif'
  Object.defineProperty(ctx, 'font', {
    get: () => font,
    set: (v) => {
      font = v
      calls.push(['font', v])
    },
  })
  Object.defineProperty(ctx, 'textAlign', { get: () => 'left', set: () => {} })
  if (fails) ctx.fillText.mockImplementation(() => { throw new Error('boom') })
  return ctx
}

const MODEL = {
  hexagram_id: 1,
  symbol: '䷀',
  ten: 'Càn Vi Thiên',
  drawn_date: '30/08/2026',
  hook: { text: 'Suốt ngày lo làm cho tới, chiều tối vẫn còn e dè.', text_1x1: 'Suốt ngày lo làm cho tới.', source: 'hao_dong' },
  hook_text: 'Suốt ngày lo làm cho tới, chiều tối vẫn còn e dè.',
  keywords: ['càn', 'tự cường', 'dẫn đầu', 'hành động'],
  disclaimer: 'Giải trí · tham khảo văn hoá',
  qr_text: 'https://quhehomnay.vn/s/Ab3dE9fGh1',
  url: 'https://quhehomnay.vn/s/Ab3dE9fGh1',
  minimal: false,
}

const QR_IMG = { width: 200, height: 200 }

function kind(calls, k) {
  return calls.filter((c) => c[0] === k)
}
function texts(calls) {
  return kind(calls, 'fillText').map((c) => String(c[1]))
}

describe('cỡ khung', () => {
  it('9:16 = 1080×1920, 1:1 = 1080×1080', () => {
    expect(FRAME_9X16).toMatchObject({ w: 1080, h: 1920 })
    expect(FRAME_1X1).toMatchObject({ w: 1080, h: 1080 })
  })
})

describe('drawShareCard khung 9:16', () => {
  it('vẽ nền paper #F7F2E7 rồi lớp texture gradient (2 lớp, không html2canvas)', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    const fills = kind(ctx.calls, 'fillStyle')
    expect(fills.some((c) => c[1] === '#F7F2E7')).toBe(true)
    // có gradient làm texture (addColorStop được gọi)
    expect(kind(ctx.calls, 'stop').length).toBeGreaterThan(0)
  })

  it('symbol cinnabar #B33A2B font Noto Serif TC cỡ ~300px+, ngày muted #5C554A', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    const sym = kind(ctx.calls, 'fillText').find((c) => c[1] === '䷀')
    expect(sym).toBeTruthy()
    // font được set trước khi fillText symbol: tìm 'font' call gần nhất trước lệnh đó
    const idx = ctx.calls.indexOf(sym)
    const fontsBefore = ctx.calls.slice(0, idx).filter((c) => c[0] === 'font').map((c) => c[1])
    const lastFont = fontsBefore[fontsBefore.length - 1]
    expect(lastFont).toMatch(/Noto Serif TC|han/i)
    expect(parseFloat(lastFont.match(/(\d+(\.\d+)?)px/)[1])).toBeGreaterThanOrEqual(280)
    const cinnabar = kind(ctx.calls, 'fillStyle').some((c) => c[1] === '#B33A2B')
    expect(cinnabar).toBe(true)
    expect(texts(ctx.calls)).toContain('30/08/2026')
    expect(kind(ctx.calls, 'fillStyle').some((c) => c[1] === '#5C554A')).toBe(true)
  })

  it('safe-area story: core text dưới 250px đỉnh, QR+URL nằm trong đáy từ 1610px', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    // symbol/tên/ngày/hook/chips phải nằm dưới mép an toàn đỉnh (đơn vị canvas = 1920 cao)
    const core = kind(ctx.calls, 'fillText').filter(
      (c) => c[1] === '䷀' || c[1] === 'Càn Vi Thiên' || c[1] === '30/08/2026' || String(c[1]).includes('Suốt ngày') || MODEL.keywords.some((k) => String(c[1]).includes(k)),
    )
    expect(core.length).toBeGreaterThanOrEqual(7)
    for (const c of core) expect(c[3]).toBeGreaterThanOrEqual(250) // y của fillText
    // QR nằm trong đáy an toàn
    const qrDraw = kind(ctx.calls, 'drawImage')
    expect(qrDraw.length).toBe(1)
    expect(qrDraw[0][3]).toBeGreaterThanOrEqual(1610 - 10) // dy của drawImage(img, dx, dy, dw, dh)
    // disclaimer là dòng nhỏ nhất — mockup để gần đỉnh story (SPEC cấm text QUAN TRỌNG, không cấm disclaimer)
    const dis = kind(ctx.calls, 'fillText').find((c) => c[1] === MODEL.disclaimer)
    expect(dis).toBeTruthy()
    expect(dis[3]).toBeGreaterThan(180)
  })

  it('QR có (drawImage 1 lần) + URL ngắn dưới QR', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    expect(kind(ctx.calls, 'drawImage').length).toBe(1)
    expect(texts(ctx.calls).some((t) => t.includes('quhehomnay'))).toBe(true)
  })

  // BUG-F7-UX1 (t_cf7e69e9): QA-R2 t_414aee5e soi pixel TH1/TH2 9x16 — chuỗi URL muted
  // ĐÈ mép phải QR (ký tự đầu nằm trong vùng module + quiet zone). Bất biến: URL đứng
  // SANG PHẢI ảnh QR với gap ≥1 module; QR giữ nguyên vị trí (chỉ đạo card).
  it('BUG-F7-UX1: URL không đè QR — mép trái text ≥ mép phải ảnh QR + 1 module', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    const qr = kind(ctx.calls, 'drawImage')[0] // [img, dx, dy, dw, dh]
    const [, , dx, dy, dw, dh] = qr
    const urlCall = kind(ctx.calls, 'fillText').find((c) => String(c[1]).includes('quhehomnay'))
    expect(urlCall).toBeTruthy()
    const [, t, x, y] = urlCall
    // PNG QR đã chứa margin:1 module BÊN TRONG ảnh → cấm text chạm mép ảnh QR
    expect(x).toBeGreaterThanOrEqual(dx + dw)
    // ≥1 module dự phòng ngoài ảnh QR: v3 (220px/31module ≈ 7,1px) → 16px an toàn mọi version
    expect(x - (dx + dw)).toBeGreaterThanOrEqual(16)
    // thẳng hàng dọc vùng QR (không nhảy xuống/xout ngoài đáy)
    expect(y).toBeGreaterThan(dy)
    expect(y).toBeLessThanOrEqual(dy + dh + 20)
    // không tràn mép phải an toàn (unit 1080 − PAD_X 84; mock measure 13px/ký)
    expect(x + ctx.measureText(t).width).toBeLessThanOrEqual(1080 - 84)
  })

  it('BUG-F7-UX1: URL dài quá chỗ còn lại → cắt dấu … , KHÔNG tràn mép phải', () => {
    const ctx = mkCtx()
    const model = { ...MODEL, url: 'https://que.today/s/' + 'x'.repeat(60) } // shortUrl → 73 ký
    drawShareCard(ctx, model, { frame: FRAME_9X16, qrImage: QR_IMG })
    const urlCall = kind(ctx.calls, 'fillText').find((c) => String(c[1]).startsWith('que.today'))
    expect(urlCall).toBeTruthy()
    const [, t, x] = urlCall
    expect(t.endsWith('…')).toBe(true)
    expect(x + ctx.measureText(t).width).toBeLessThanOrEqual(1080 - 84)
    // URL ngắn bình thường KHÔNG bị cắt
    const ctx2 = mkCtx()
    drawShareCard(ctx2, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    const t2 = texts(ctx2.calls).find((s) => s.includes('quhehomnay'))
    expect(t2).toBe('quhehomnay.vn/s/Ab3dE9fGh1')
  })

  it('đủ nội dung: symbol, tên, ngày, hook, 4 chips, disclaimer', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    const t = texts(ctx.calls)
    expect(t).toContain('䷀')
    expect(t).toContain('Càn Vi Thiên')
    expect(t).toContain('30/08/2026')
    expect(t.some((x) => x.includes('Suốt ngày'))).toBe(true)
    for (const k of MODEL.keywords) expect(t.some((x) => x.includes(k))).toBe(true)
    expect(t).toContain('Giải trí · tham khảo văn hoá')
  })

  it('chips viền bamboo #3E5C48, nền paper2 #EFE6D3', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })
    expect(kind(ctx.calls, 'strokeStyle').some((c) => c[1] === '#3E5C48')).toBe(true)
    expect(kind(ctx.calls, 'fillStyle').some((c) => c[1] === '#EFE6D3')).toBe(true)
  })

  it('KHÔNG BAO GIỜ vẽ free_content/han/quốc âm (SPEC-THE §2 chống lộ)', () => {
    const ctx = mkCtx()
    const model = { ...MODEL, free_content: { congViec: 'LUAN DI DAI' }, han: '乾', quoc_am: 'Can' }
    drawShareCard(ctx, model, { frame: FRAME_9X16, qrImage: QR_IMG })
    const t = texts(ctx.calls).join('|')
    expect(t).not.toContain('LUAN DI DAI')
    expect(t).not.toContain('乾')
    expect(t).not.toMatch(/\bCan\b/)
  })
})

describe('drawShareCard khung 1:1', () => {
  it('KHÔNG QR (0 drawImage) + dòng phụ caption 1:1 render', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_1X1, qrImage: QR_IMG, caption1x1: 'Càn Vi Thiên — bạn là quẻ nào?' })
    expect(kind(ctx.calls, 'drawImage').length).toBe(0)
    expect(texts(ctx.calls).some((t) => t.includes('bạn là quẻ nào?'))).toBe(true)
  })

  it('câu chính dùng bản 60 ký tự (text_1x1), 2 dòng tối đa', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_1X1, qrImage: null, caption1x1: 'x' })
    const hookLines = texts(ctx.calls).filter((t) => t.includes('Suốt ngày'))
    expect(hookLines.length).toBeLessThanOrEqual(2)
    expect(hookLines[0]).toContain('Suốt ngày lo làm cho tới.')
  })

  it('bỏ chip thứ 4 nếu tràn width (measureText mock 13px/ký tự)', () => {
    const ctx = mkCtx()
    // keywords cố tình siêu dài để chắc chắn tràn
    const model = { ...MODEL, keywords: ['aaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbb', 'ccccccccccccccccccccccc', 'dddddddddddddddddddddddd'] }
    drawShareCard(ctx, model, { frame: FRAME_1X1, qrImage: null, caption1x1: 'x' })
    const drawn = texts(ctx.calls)
    expect(drawn.some((t) => t.includes('aaaa'))).toBe(true)
    expect(drawn.some((t) => t.includes('dddd'))).toBe(false)
  })

  it('chip vừa đủ → giữ 3 chip, vẫn bỏ chip 4 ở 1:1 (mockup: 3 chips)', () => {
    const ctx = mkCtx()
    drawShareCard(ctx, MODEL, { frame: FRAME_1X1, qrImage: null, caption1x1: 'x' })
    const inChips = MODEL.keywords.slice(0, 3).every((k) => texts(ctx.calls).some((t) => t.includes(k)))
    expect(inChips).toBe(true)
    expect(texts(ctx.calls).some((t) => t === MODEL.keywords[3])).toBe(false)
  })
})

describe('pickChips + wrapText (helper thuần, đo được)', () => {
  const measure = (s) => s.length * 10
  it('pickChips: nhét vừa width thì giữ, chip tràn thì DỪNG (không nhảy con sau)', () => {
    // chip width = measure(k) + 2*pad; ngăn cách gap
    expect(pickChips(['a', 'bb', 'ccc'], { maxWidth: 1000, measure, gap: 10, pad: 20 })).toEqual(['a', 'bb', 'ccc'])
    expect(pickChips(['a', 'bb', 'ccc'], { maxWidth: 200, measure, gap: 10, pad: 20 })).toEqual(['a', 'bb'])
  })
  it('wrapText ≤2 dòng cho feed, cắt word-boundary', () => {
    const lines = wrapText('aaa bbb ccc ddd eee fff ggg', { maxWidth: 90, measure, maxLines: 2 })
    expect(lines.length).toBeLessThanOrEqual(2)
    for (const l of lines) expect(l.trim().length).toBeGreaterThan(0)
    expect(lines[0]).toBe('aaa bbb') // greedy: thêm ' ccc' = 91px > 90 → ngắt tại word-boundary
    expect(lines[1]).toContain('…') // phần tràn dồn vào dòng cuối + ellipsis
  })
})

describe('thẻ tối giản E6', () => {
  it('hook null → chỉ symbol + tên + ngày + chips + disclaimer, không có dòng hook', () => {
    const ctx = mkCtx()
    const m = { ...MODEL, hook: null, hook_text: null, minimal: true }
    drawShareCard(ctx, m, { frame: FRAME_9X16, qrImage: QR_IMG })
    const t = texts(ctx.calls)
    expect(t).toContain('䷀')
    expect(t).toContain('Càn Vi Thiên')
    expect(t.some((x) => x.includes('Suốt ngày'))).toBe(false)
  })
})

describe('an toàn scale + lỗi', () => {
  it('canvas thật cỡ khác 1080 đơn vị → ctx.scale được gọi (vẽ đơn vị 1080 rồi scale)', () => {
    const ctx = mkCtx()
    ctx.canvas = { width: 1350, height: 2400 }
    drawShareCard(ctx, MODEL, { frame: { ...FRAME_9X16, w: 1350, h: 2400 }, unit: 1080, qrImage: QR_IMG })
    expect(kind(ctx.calls, 'scale').length).toBe(1)
    expect(ctx.scale).toHaveBeenCalledWith(1.25, 1.25)
  })

  it('fillText ném lỗi → drawShareCard NÉM tiếp để caller bắt → fallback E1 (không nuốt)', () => {
    const ctx = mkCtx({ fails: true })
    expect(() => drawShareCard(ctx, MODEL, { frame: FRAME_9X16, qrImage: QR_IMG })).toThrow()
  })
})
