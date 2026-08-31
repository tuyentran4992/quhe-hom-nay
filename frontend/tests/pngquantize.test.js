// BUG-F7-QA2 (t_b33b9f28) — TDD RED. PNG thẻ render thật 695–964KB > 500KB (SPEC-THE §3).
// Fix: quantize 5-bit/kênh RGB trên ImageData SAU khi vẽ, TRƯỚC khi encode — flatten
// gradient texture lớp nền (chỉ đạo merge-card t_a2ef281b muc 3b). KHÔNG giảm resolution.
// Bằng chứng offline (Pillow, chính xác cùng mapping bit-replication, 6 PNG QA mb8):
//   9:16 926–964KB → 85–101KB · 1:1 683–701KB → 64–73KB.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { PNG_QUANT_BITS, quantizeImageData, renderFrame, FRAME_1X1 } from '../src/utils/shareCardCanvas.js'

function mkImageData(w, h, fill) {
  const data = new Uint8ClampedArray(w * h * 4)
  for (let i = 0; i < w * h; i++) {
    data.set(fill, i * 4)
  }
  return { width: w, height: h, colorSpace: 'srgb', data }
}

describe('quantizeImageData (flatten gradient texture, SPEC-THE §3 ≤500KB)', () => {
  it('hằng số 5-bit export (đủ flat PNG, mép dịch ≤3 LSB — không vỡ token màu)', () => {
    expect(PNG_QUANT_BITS).toBe(5)
  })

  it('mapping bit-replication (giữ 5 cao | lặp vào 3 thấp): 247→247, 244→247, 250→255', () => {
    const id = mkImageData(3, 1, [0, 0, 0, 255])
    id.data.set([247, 0, 0, 255], 0)
    id.data.set([244, 0, 0, 255], 4)
    id.data.set([250, 0, 0, 255], 8)
    const out = quantizeImageData(id, 5)
    expect(out).toBe(id) // in-place, trả chính nó cho chain nhanh
    expect([id.data[0], id.data[4], id.data[8]]).toEqual([247, 247, 255])
    // 0 và 255 là 2 đầu mút giữ nguyên tuyệt đối
    const z = mkImageData(1, 1, [0, 255, 0, 255])
    quantizeImageData(z, 5)
    expect([z.data[0], z.data[1]]).toEqual([0, 255])
  })

  it('ALPHA giữ nguyên 8-bit (QR/edge không broken)', () => {
    const id = mkImageData(1, 1, [17, 99, 200, 123])
    quantizeImageData(id, 5)
    expect(id.data[3]).toBe(123)
  })

  it('ramp gradient mượt → gom về ≤4 code/kênh (deflate thắng: band bằng nhau)', () => {
    // dải texture QA đo thật: nền paper chạy 225→255 mỗi kênh
    const id = mkImageData(31, 1, [0, 0, 0, 255])
    for (let x = 0; x < 31; x++) id.data.set([225 + x, 225 + x, 225 + x, 255], x * 4)
    quantizeImageData(id, 5)
    for (let c = 0; c < 3; c++) {
      const uniq = new Set()
      for (let x = 0; x < 31; x++) uniq.add(id.data[x * 4 + c])
      expect(uniq.size).toBeLessThanOrEqual(4)
    }
    // giá trị tuyệt đối xê dịch ≤8 LSB so với gốc (màu token không đổi trực quan)
    expect(Math.abs(id.data[0] - 225)).toBeLessThanOrEqual(8)
    expect(id.data[30 * 4]).toBe(255)
  })
})

describe('renderFrame áp quantize TRƯỚC khi encode (PNG output nhỏ, resolution giữ)', () => {
  let realCreate, getImageDataSpy, putImageDataSpy
  beforeEach(() => {
    realCreate = document.createElement.bind(document)
    getImageDataSpy = vi.fn((x, y, w, h) => mkImageData(w, h, [244, 238, 225, 255]))
    putImageDataSpy = vi.fn()
    const ctx2d = {
      canvas: null,
      getImageData: getImageDataSpy,
      putImageData: putImageDataSpy,
      save: vi.fn(), restore: vi.fn(), scale: vi.fn(), translate: vi.fn(),
      fillRect: vi.fn(), beginPath: vi.fn(), moveTo: vi.fn(), lineTo: vi.fn(),
      stroke: vi.fn(), fill: vi.fn(), closePath: vi.fn(), roundRect: vi.fn(),
      arcTo: vi.fn(), quadraticCurveTo: vi.fn(), drawImage: vi.fn(), clip: vi.fn(),
      createLinearGradient: vi.fn(() => ({ addColorStop: vi.fn() })),
      measureText: vi.fn((t) => ({ width: String(t).length * 13 })),
      fillText: vi.fn(), strokeText: vi.fn(),
      set font(v) {}, get font() { return '10px sans' },
      set fillStyle(v) {}, get fillStyle() { return '#000' },
      set strokeStyle(v) {}, get strokeStyle() { return '#000' },
      set textAlign(v) {}, get textAlign() { return 'left' },
      set lineWidth(v) {}, get lineWidth() { return 1 },
    }
    vi.spyOn(document, 'createElement').mockImplementation((tag) => {
      const el = realCreate(tag)
      if (String(tag).toLowerCase() === 'canvas') {
        el.getContext = () => ctx2d
        el.toBlob = vi.fn((cb) => cb(new Blob(['x'], { type: 'image/png' })))
        el.toDataURL = vi.fn(() => 'data:image/png;base64,AAA')
        ctx2d.canvas = el
      }
      return el
    })
  })
  afterEach(() => {
    vi.restoreAllMocks()
  })

  const MODEL = {
    hexagram_id: 1, symbol: '䷀', ten: 'Càn Vi Thiên', drawn_date: '30/08/2026',
    hook: { text: 'a b', text_1x1: 'a b', source: 'dai_ci' }, hook_text: 'a b',
    keywords: ['càn'], disclaimer: 'Giải trí · tham khảo văn hoá',
    url: 'https://x/s/Ab3', minimal: false, caption_1x1: 'cap',
  }

  it('vẽ xong → getImageData(0,0,W,H) → quantize → putImageData → MỚI toBlob/toDataURL', async () => {
    const r = await renderFrame(MODEL, FRAME_1X1, {})
    expect(getImageDataSpy).toHaveBeenCalledTimes(1)
    expect(getImageDataSpy).toHaveBeenCalledWith(0, 0, 1080, 1080)
    expect(putImageDataSpy).toHaveBeenCalledTimes(1)
    // thứ tự: putImageData PHẢI trước encode — assert bằng call order trên ctx
    expect(r.dataUrl).toBe('data:image/png;base64,AAA')
    const enc = r.canvas.toDataURL.mock.invocationCallOrder[0]
    const put = putImageDataSpy.mock.invocationCallOrder[0]
    expect(put).toBeLessThan(enc)
  })

  it('canvas không có ImageData API (jsdom) → BỎ QUA quantize, KHÔNG ném E1', async () => {
    // ctx tối giản KHÔNG có getImageData/putImageData → code phải nhận diện và bỏ qua.
    vi.spyOn(document, 'createElement').mockImplementation((tag) => {
      const el = realCreate(tag)
      if (String(tag).toLowerCase() === 'canvas') {
        el.getContext = () => ({
          save: vi.fn(), restore: vi.fn(), scale: vi.fn(), fillRect: vi.fn(),
          beginPath: vi.fn(), moveTo: vi.fn(), lineTo: vi.fn(), stroke: vi.fn(),
          fill: vi.fn(), closePath: vi.fn(), roundRect: vi.fn(), arcTo: vi.fn(),
          quadraticCurveTo: vi.fn(), drawImage: vi.fn(), clip: vi.fn(), measureText: vi.fn((t) => ({ width: 10 })),
          createLinearGradient: vi.fn(() => ({ addColorStop: vi.fn() })),
          fillText: vi.fn(), strokeText: vi.fn(), set font(v) {}, get font() { return '' },
          set fillStyle(v) {}, get fillStyle() { return '' }, set strokeStyle(v) {}, get strokeStyle() { return '' },
          set textAlign(v) {}, get textAlign() { return 'left' }, set lineWidth(v) {}, get lineWidth() { return 1 },
        })
        el.toBlob = vi.fn((cb) => cb(new Blob(['x'], { type: 'image/png' })))
        el.toDataURL = vi.fn(() => 'data:image/png;base64,BBB')
      }
      return el
    })
    const r = await renderFrame(MODEL, FRAME_1X1, {})
    expect(r.dataUrl).toBe('data:image/png;base64,BBB') // vẫn render, không quăng
  })
})
