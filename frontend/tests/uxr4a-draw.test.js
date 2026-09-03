// UXR-4a (t_0c74b51e) — DrawView lớp brand + nghi thức:
//  ĐX1+A2: mini-header 3 điểm (brand/Sổ quẻ/Trang chính), rolling ẩn 2 link, triện 卦, H1 chân trang.
//  ĐX2: trạng thái ĐÃ GIEO đọc từ #1 /me qua useDevice (payload that §3.2, không mock shape le):
//       today_draw → nút phụ draw-today → /que/:id + dòng hẹn; chưa gieo → giữ nút đỏ + chip quota.
//  ĐX3+A1/ĐX6: khối preview «Sau khi gieo, bạn nhận về:» nguyên văn HOME_COPY.steps + sân gieo tinh
//       (6 slot ghost + 3 xu, token MagicSequence, aria-hidden, không animation).
//  C1: giờ quẻ «gieo lúc HH:MM» client-side new Date() tại lúc bấm, hiện ở khối kết quả; bỏ vế Âm lịch.
// Wording NGUYÊN VĂN /data/agents/copywriter-vn/outbox/t_UXR-W/wording.md (mục 1–2–3–5).
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DrawView from '../src/views/DrawView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { HOME_COPY, DRAW_COPY } from '../src/constants.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { createDraw: vi.fn(), me: vi.fn(), today: vi.fn(), track: vi.fn() } }
})

// payload THẬT §3.2 draw (03-api) — y hệt shape #1 today_draw, không chế field
const TODAY_DRAW = { id: 77, hexagram_id: 11, drawn_date: '2026-09-03', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: '2026-09-03T07:06:00+00:00' }
const ME_NOT_DRAWN = { device_id: 'd1', is_new_device: false, server_date_vn: '2026-09-03', entitlements: [], today_draw: null, free_deep: true }
const ME_DRAWN = { ...ME_NOT_DRAWN, today_draw: TODAY_DRAW }
const DRAWN_OK = {
  data: {
    draw: { ...TODAY_DRAW, id: 42 },
    hexagram: { id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0], free_content: { congViec: 'a', tinhDuyen: 'b', taiLoc: 'c' } },
    hao_texts: [],
    already_drawn: false,
  },
}

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/draw', name: 'draw', component: DrawView },
      { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
      { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } }, // UXR-4b draw-share-cta
    ],
  })
}

async function mountView() {
  const r = mk()
  const w = mount(DrawView, { global: { plugins: [r] } })
  await r.isReady()
  await flushPromises()
  return { r, w }
}

beforeEach(() => {
  _resetDeviceForTests()
  client.api.createDraw.mockReset()
  client.api.me.mockReset()
  client.api.today.mockReset()
  client.api.track.mockReset()
  client.api.me.mockResolvedValue(ME_NOT_DRAWN)
})

describe('UXR-4a ĐX1 — mini-header nghi thức (thay trọn "← Về")', () => {
  it('idle: đủ 3 điểm brand→/ · Sổ quẻ→/cua-ban · Trang chính→/ đúng wording nguyên văn', async () => {
    const { w } = await mountView()
    expect(w.find('[data-testid="draw-back"]').exists()).toBe(false) // chuỗi cũ chết hẳn
    const brand = w.find('[data-testid="draw-brand"]')
    const lib = w.find('[data-testid="draw-library"]')
    const home = w.find('[data-testid="draw-home"]')
    expect(brand.exists() && lib.exists() && home.exists()).toBe(true)
    expect(brand.text()).toBe('Quẻ Hôm Nay')
    expect(lib.text()).toBe('Sổ quẻ')
    expect(home.text()).toBe('Trang chính')
    expect(brand.attributes('href')).toBe('/')
    expect(lib.attributes('href')).toBe('/cua-ban')
    expect(home.attributes('href')).toBe('/')
  })
  it('rolling: chỉ giữ brand, ẨN 2 link (chỉ đạo ĐX1)', async () => {
    client.api.createDraw.mockReturnValue(new Promise(() => {})) // treo — ở lại rolling
    const { w } = await mountView()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="draw-brand"]').exists()).toBe(true)
    expect(w.find('[data-testid="draw-library"]').exists()).toBe(false)
    expect(w.find('[data-testid="draw-home"]').exists()).toBe(false)
  })
})

describe('UXR-4a ĐX2 — trạng thái quota/đã-gieo TRƯỚC khi bấm (đọc #1 qua useDevice)', () => {
  it('today_draw tồn tại → nút phụ «Xem quẻ hôm nay» → /que/:id + dòng hẹn nguyên văn; không còn nút đỏ', async () => {
    client.api.me.mockResolvedValue(ME_DRAWN)
    const { w } = await mountView()
    const btn = w.find('[data-testid="draw-today"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toBe('Xem quẻ hôm nay')
    expect(btn.attributes('href')).toBe('/que/77')
    expect(w.text()).toContain('Hôm nay đã gieo — hẹn giờ Tý (0h) mai.')
    expect(w.find('[data-testid="draw-start"]').exists()).toBe(false)
    expect(w.find('[data-testid="draw-quota-note"]').exists()).toBe(false)
  })
  it('chưa gieo → giữ nút đỏ + chip «1 quẻ mỗi ngày · miễn phí» (draw-quota-note)', async () => {
    const { w } = await mountView()
    expect(w.find('[data-testid="draw-start"]').exists()).toBe(true)
    const chip = w.find('[data-testid="draw-quota-note"]')
    expect(chip.exists()).toBe(true)
    expect(chip.text()).toBe('1 quẻ mỗi ngày · miễn phí')
    expect(w.find('[data-testid="draw-today"]').exists()).toBe(false)
  })
  it('#1 LỖI → không trắng hành động: vẫn nút đỏ, không lỗi console', async () => {
    client.api.me.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng', {}))
    const err = vi.spyOn(console, 'error').mockImplementation(() => {})
    const { w } = await mountView()
    expect(w.find('[data-testid="draw-start"]').exists()).toBe(true)
    expect(err).not.toHaveBeenCalled()
    err.mockRestore()
  })
})

describe('UXR-4a ĐX3 — khối preview giá trị (HOME_COPY.steps nguyên văn, cấm chế chữ)', () => {
  it('nhãn đầu khối + 3 dòng no·t—d khớp từng ký tự constants đã duyệt', async () => {
    const { w } = await mountView()
    expect(w.find('[data-testid="draw-preview"]').text()).toContain('Sau khi gieo, bạn nhận về:')
    HOME_COPY.steps.forEach((s, i) => {
      const li = w.find(`[data-testid="draw-preview-step-${i + 1}"]`)
      expect(li.exists()).toBe(true)
      expect(li.text()).toContain(`${s.t} — ${s.d}`)
      expect(li.text()).toContain(s.no)
    })
    // chốt literal chống drift constants (wording.md mục 3)
    expect(HOME_COPY.steps[0].t).toBe('Gieo quẻ')
    expect(HOME_COPY.steps[2].d).toBe('Chọn chủ đề — luận trọn ba ngôi soạn theo hỏi ý.')
  })
})

describe('UXR-4a A1/ĐX6 + A2 — sân gieo tinh + triện 卦 + H1 brand chân trang', () => {
  it('sân gieo: 6 slot ghost + 3 xu, aria-hidden (không phải dữ liệu), không animation', async () => {
    const { w } = await mountView()
    const stage = w.find('[data-testid="draw-ghost-field"]')
    expect(stage.exists()).toBe(true)
    expect(stage.attributes('aria-hidden')).toBe('true')
    expect(w.findAll('[data-testid="draw-ghost-slot"]')).toHaveLength(6)
    expect(w.findAll('[data-testid="draw-ghost-coin"]')).toHaveLength(3)
    expect(stage.html()).not.toMatch(/animation:|transition:/i) // tĩnh tuyệt đối — C-08 không bị chạm
  })
  it('triện 卦 ≤48px + H1 «Quẻ Hôm Nay» trong vùng web (brand vào khung hình)', async () => {
    const { w } = await mountView()
    expect(w.find('[data-testid="draw-seal"]').text()).toContain('卦')
    const h1 = w.find('[data-testid="draw-foot-brand"]')
    expect(h1.exists()).toBe(true)
    expect(h1.element.tagName).toBe('H1')
    expect(h1.text()).toBe('Quẻ Hôm Nay')
  })
})

describe('UXR-4a C1 — giờ quẻ «gieo lúc HH:MM» (dấu mực, không lời bình, không vế Âm)', () => {
  beforeEach(() => vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] }))
  afterEach(() => vi.useRealTimers())

  async function revealAt(hour, minute) {
    client.api.createDraw.mockResolvedValue(DRAWN_OK)
    vi.setSystemTime(new Date(2026, 8, 3, hour, minute, 0))
    const { w } = await mountView()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(3060) // PA1 revealAt (sàn C-08 1500 giữ nguyên)
    await flushPromises()
    return w
  }
  it('khối kết quả: «{ten} — gieo lúc HH:MM» thay dòng «đang vào bảng giải» cũ', async () => {
    const w = await revealAt(14, 6)
    const res = w.find('[data-testid="draw-result"]')
    expect(res.exists()).toBe(true)
    // UXR-4b: khối giờ cũng CHỨA 3 quyền chọn (ĐX5/B1) → soi đúng dòng giờ (p đầu)
    expect(res.find('p').text()).toBe('Địa Thiên Thái — gieo lúc 14:06')
    expect(w.text()).not.toContain('đang vào bảng giải')
  })
  it('pad số 0 + mốc đêm: 23:05 → «23:05», 00:05 → «00:05» (giờ máy khách, không lib lịch)', async () => {
    expect((await revealAt(23, 5)).find('[data-testid="draw-result"] p').text()).toBe('Địa Thiên Thái — gieo lúc 23:05')
    expect((await revealAt(0, 5)).find('[data-testid="draw-result"] p').text()).toBe('Địa Thiên Thái — gieo lúc 00:05')
  })
  it('KO-định-đoán: không lời bình giờ, không vế Âm lịch', async () => {
    const w = await revealAt(9, 0)
    expect(w.text()).not.toMatch(/âm lịch| hợp giờ|ứng giờ|rebirth/i)
  })
})

describe('UXR-4a wording contract — DRAW_COPY là surface duy nhất', () => {
  it('mọi chuỗi 4a đúng nguyên văn wording.md (kể cả dòng alt ngắn đã đổi tên)', () => {
    expect(DRAW_COPY).toMatchObject({
      brand: 'Quẻ Hôm Nay',
      library: 'Sổ quẻ',
      home: 'Trang chính',
      todayBtn: 'Xem quẻ hôm nay',
      drawnNote: 'Hôm nay đã gieo — hẹn giờ Tý (0h) mai.',
      quotaNote: '1 quẻ mỗi ngày · miễn phí',
      previewHead: 'Sau khi gieo, bạn nhận về:',
    })
    expect(DRAW_COPY.castAt('09:00')).toBe('gieo lúc 09:00')
  })
})
