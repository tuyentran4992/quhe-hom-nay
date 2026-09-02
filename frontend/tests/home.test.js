// HomeView S1 — HOME-FE-V3 (t_a7026e13): dựng lại theo mockup đã duyệt UX-HOME-V2 (+NAV)
// 3 trạng thái A (khách mới chưa gieo) / B (đã gieo hôm nay) / C (quay lại, có lịch sử,
// hôm nay chưa gieo). Nội dung ĐỘNG: quẻ hôm nay + trạng thái từ #1 today_draw /
// server_date_vn / entitlements / free_deep; tên quẻ qua useHexagrams.ensure (#2);
// dải Sổ quẻ + streak suy từ #4 history drawn_date (neo server_date_vn — KHÔNG đồng hồ máy).
// Ngôn ngữ freeDeep=true: pill "Luận sâu MIỄN PHÍ" + link "Lễ tùy tâm", CẤM "29.000".
// freeDeep=false giữ hành vi giá cũ. Testid nav cũ (home-nav-draw/library) dời lên shell
// nav — coverage href ở tests/nav.test.js; ở đây chốt chúng KHÔNG còn trong HomeView.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeView from '../src/views/HomeView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), history: vi.fn(), hexagram: vi.fn(), createDraw: vi.fn(), track: vi.fn() } }
})

const routes = [
  { path: '/', name: 'home', component: HomeView },
  { path: '/draw', name: 'draw', component: { template: '<div/>' } },
  { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
  { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
  { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
  { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
]

function mk() {
  return createRouter({ history: createMemoryHistory(), routes })
}

// draw §3.2 thuần (BE toApi — Draw.php:38-47), không embed hexagram
const TODAY_DRAW = {
  id: 42, hexagram_id: 11, drawn_date: '2026-09-02',
  lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2],
  created_at: '2026-09-02T01:12:00Z',
}
const HX = (id) => ({
  id, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊',
  lines: [1, 1, 1, 0, 0, 0], keywords: ['thái'],
  dai_ci: 'Trời xuống đất lên.',
  free_content: { congViec: 'Việc mình nộp im re hôm kia đang được người ta mở ra xem.', tinhDuyen: 'x', taiLoc: 'y' },
})
const drawOn = (id, date) => ({ id, hexagram_id: id + 60, drawn_date: date, lines_rolled: [7, 7, 7, 7, 7, 7], changing_lines: [], created_at: `${date}T02:00:00Z` })
// history B: đủ chuỗi 01/09 · 31/08 + hôm nay → streak "Ngày thứ 3"
const HISTORY_B = { data: [TODAY_DRAW, drawOn(41, '2026-09-01'), drawOn(40, '2026-08-31')], meta: { count: 3 } }
// history C: hôm nay CHƯA gieo, chuỗi 2 ngày tính từ hôm qua (ràng buộc a4)
const HISTORY_C = { data: [drawOn(41, '2026-09-01'), drawOn(40, '2026-08-31')], meta: { count: 2 } }

const meFor = ({ today = null, history = false, freeDeep = false, server = '2026-09-02', entitlements = [] }) => ({
  device_id: 'dev12345678', is_new_device: !history, server_date_vn: server,
  entitlements, today_draw: today, free_deep: freeDeep,
})

async function mountHome(mePayload, hist = { data: [], meta: { count: 0 } }) {
  client.api.me.mockResolvedValue(mePayload)
  client.api.history.mockResolvedValue(hist)
  const r = mk()
  const w = mount(HomeView, { global: { plugins: [r] } })
  await r.isReady()
  await flushPromises()
  await flushPromises()
  return w
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  document.title = 'x'
  client.api.hexagram.mockImplementation(async (id) => ({ data: HX(id) }))
})

describe('HomeView — khung trạng thái', () => {
  it('đang tải → hiện loading, không trắng', async () => {
    client.api.me.mockReturnValue(new Promise(() => {}))
    client.api.history.mockReturnValue(new Promise(() => {}))
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    expect(w.find('[data-testid="home-loading"]').exists()).toBe(true)
  })

  it('lỗi API → home-error (không trắng màn)', async () => {
    client.api.me.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    client.api.history.mockResolvedValue({ data: [], meta: { count: 0 } })
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-error"]').exists()).toBe(true)
  })
})

describe('HomeView State A — khách mới, hôm nay chưa gieo, không lịch sử', () => {
  it('hero A đúng copy duyệt + CTA gieo variant new → /draw; KHÔNG streak chip, KHÔNG dải sổ quẻ', async () => {
    const w = await mountHome(meFor({}))
    expect(w.find('[data-testid="home-hero"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-hero-title"]').text()).toContain('Gieo ba đồng xu')
    expect(w.find('[data-testid="home-hero-title"]').text()).toContain('hôm nay')
    expect(w.find('[data-testid="home-hero-tagline"]').text()).toContain('một quẻ Kinh Dịch cho một ngày của bạn')
    expect(w.find('[data-testid="home-ritual"]').exists()).toBe(true)
    const cta = w.find('[data-testid="home-cta-gieo"]')
    expect(cta.attributes('href')).toBe('/draw')
    expect(cta.attributes('data-variant')).toBe('new')
    expect(w.find('[data-testid="home-streak-chip"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-library-strip"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-steps"]').exists()).toBe(true)
    expect(client.api.createDraw).not.toHaveBeenCalled() // reload không tự gieo
  })

  it('nav cũ không còn trong HomeView (dời lên shell — tests/nav.test.js giữ coverage href)', async () => {
    const w = await mountHome(meFor({}))
    expect(w.find('[data-testid="home-nav-draw"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-nav-library"]').exists()).toBe(false)
  })
})

describe('HomeView State B — đã gieo hôm nay', () => {
  const ME_B = meFor({ today: TODAY_DRAW, history: true })

  it('today card ĐỘNG từ #1+#2: tên quẻ tra #2, hào vector (6 dòng, hào động chấm), dòng công việc, không gọi #3', async () => {
    const w = await mountHome(ME_B, HISTORY_B)
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(true)
    expect(client.api.hexagram).toHaveBeenCalledWith(11) // shape thật: tra #2 theo hexagram_id
    expect(client.api.createDraw).not.toHaveBeenCalled() // AC2 idempotent — không gieo mới
    expect(w.find('[data-testid="home-hexagram-name"]').text()).toContain('Địa Thiên Thái')
    const fig = w.find('[data-testid="home-hexagram-symbol"]')
    expect(fig.findAll('.ln').length).toBe(6) // 6 hào vector (không phụ thuộc glyph ䷊)
    expect(fig.findAll('.ln.mov').length).toBe(1) // hào 2 động (changing_lines=[2])
    expect(w.find('[data-testid="home-today-free-line"]').text()).toContain('Việc mình nộp')
    expect(w.find('[data-testid="home-changing-lines"]').text()).toContain('Hào 2 động')
  })

  it('status "Đã gieo lúc HH:MM — hẹn giờ Tý (0h) mai" (giờ máy khách từ created_at) + CTA detail/share đúng href; KHÔNG nút gieo lại', async () => {
    const w = await mountHome(ME_B, HISTORY_B)
    expect(w.find('[data-testid="home-today-status"]').text()).toMatch(/Đã gieo lúc \d{2}:\d{2} — hẹn giờ Tý \(0h\) mai/)
    expect(w.find('[data-testid="home-cta-detail"]').attributes('href')).toBe('/que/42')
    expect(w.find('[data-testid="home-share-btn"]').attributes('href')).toBe('/share-card?draw=42')
    expect(w.find('[data-testid="home-cta-gieo"]').exists()).toBe(false) // lệ §3B1
  })

  it('streak B "Ngày thứ 3 của bạn" suy từ chuỗi drawn_date #4, neo server_date_vn', async () => {
    const w = await mountHome(ME_B, HISTORY_B)
    expect(w.find('[data-testid="home-streak-chip"]').text()).toContain('Ngày thứ 3 của bạn')
  })

  it('dải Sổ quẻ hiện history ≥1, TRÁM quẻ hôm nay (id 42) — link /que/:id + link Xem tất cả /cua-ban', async () => {
    const w = await mountHome(ME_B, HISTORY_B)
    expect(w.find('[data-testid="home-library-strip"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-library-item-1"]').attributes('href')).toBe('/que/41')
    expect(w.find('[data-testid="home-library-item-2"]').attributes('href')).toBe('/que/40')
    expect(w.find('[data-testid="home-library-item-3"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-library-link-all"]').attributes('href')).toBe('/cua-ban')
  })

  it('#2 lỗi khi đã có draw → vẫn hiện card, không trắng (home-today-card + pending)', async () => {
    client.api.me.mockResolvedValue(ME_B)
    client.api.history.mockResolvedValue(HISTORY_B)
    client.api.hexagram.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    await flushPromises()
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-hexagram-pending"]').exists()).toBe(true)
  })
})

describe('HomeView State C — quay lại (có lịch sử, hôm nay chưa gieo)', () => {
  it('hero C "vẫn đang chờ bạn" + streak chip đếm chuỗi từ hôm qua (a4) + dòng note ngày + dải sổ quẻ', async () => {
    const w = await mountHome(meFor({ history: true }), HISTORY_C)
    expect(w.find('[data-testid="home-hero-title"]').text()).toContain('vẫn đang')
    expect(w.find('[data-testid="home-hero-title"]').text()).toContain('chờ bạn')
    expect(w.find('[data-testid="home-streak-chip"]').text()).toContain('Chuỗi của bạn: 2 ngày')
    // note liệt ngày thật từ #4: 01/09 · 31/08 — KHÔNG hardcode quẻ/copy tỉnh
    expect(w.find('[data-testid="home-hero-note"]').text()).toContain('01/09')
    expect(w.find('[data-testid="home-hero-note"]').text()).toContain('31/08')
    expect(w.find('[data-testid="home-hero-note"]').text()).toContain('đang chờ ngày thứ 3')
    expect(w.find('[data-testid="home-library-strip"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-cta-gieo"]').exists()).toBe(true) // C giữ variant new
  })

  it('API chưa có streak field & không suy được chuỗi (server_date_vn khuyết) → dòng KHÔNG con số "Mỗi ngày một quẻ, mai lại gặp quẻ mới"', async () => {
    const w = await mountHome(meFor({ history: true, server: '' }), HISTORY_C)
    const note = w.find('[data-testid="home-hero-note"]').text()
    expect(note).toContain('Mỗi ngày một quẻ, mai lại gặp quẻ mới')
    expect(note).not.toMatch(/\d/) // không bịa con số
    expect(w.find('[data-testid="home-streak-chip"]').exists()).toBe(false)
  })
})

describe('HomeView — ngôn ngữ sản phẩm theo free-deep (boss chốt 02/09)', () => {
  it('freeDeep=false (nhánh giá cũ): chip hiện PRICE_LABEL 29.000đ, topic đã mở hiện Đã mở ✓, KHÔNG link Lễ tùy tâm', async () => {
    const w = await mountHome(meFor({ today: TODAY_DRAW, history: true, entitlements: ['duyen'] }), HISTORY_B)
    expect(w.find('[data-testid="home-chip-duyen"]').attributes('href')).toBe('/mo-khoa/duyen')
    expect(w.find('[data-testid="home-chip-duyen-state"]').text()).toContain('Đã mở')
    expect(w.find('[data-testid="home-chip-tai-loc-price"]').text()).toBe('29.000đ')
    expect(w.find('[data-testid="home-chip-tai-loc"]').attributes('href')).toBe('/mo-khoa/tai_loc')
    expect(w.find('[data-testid="home-chip-xuat-hanh-price"]').text()).toBe('29.000đ')
    expect(w.find('[data-testid="home-donate-link"]').exists()).toBe(false)
    expect(w.html()).not.toContain('MIỄN PHÍ')
  })

  it('freeDeep=true: mỗi chip pill "Luận sâu MIỄN PHÍ" + link home-donate-link → /mo-khoa/duyen?mode=donate; home KHÔNG chứa chuỗi "29.000" ở đâu', async () => {
    const w = await mountHome(
      meFor({ today: TODAY_DRAW, history: true, freeDeep: true, entitlements: ['duyen', 'tai_loc', 'xuat_hanh'] }),
      HISTORY_B,
    )
    for (const slug of ['duyen', 'tai-loc', 'xuat-hanh']) {
      expect(w.find(`[data-testid="home-chip-${slug}"]`).exists()).toBe(true)
      expect(w.find(`[data-testid="home-chip-${slug}-free"]`).text()).toContain('Luận sâu MIỄN PHÍ')
    }
    // [HOME-V4-B] t_3647e25e — link donate về route riêng /tam-tu (hằng số DONATE_HREF)
    expect(w.find('[data-testid="home-donate-link"]').attributes('href')).toBe('/tam-tu')
    expect(w.html()).not.toContain('29.000')
    expect(w.find('[data-testid="home-chip-tai-loc-price"]').exists()).toBe(false)
  })
})
