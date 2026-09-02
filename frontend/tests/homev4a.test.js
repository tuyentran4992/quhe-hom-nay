// [HOME-V4-A] t_698d4076 — Luật 1 + Luật 3 (boss chốt nguyên văn 02/09, card mẹ t_8ddc67e5).
// Luật 1: freeDeep=true → chip home nhảy THẲNG /que/<today_draw_id>?topic=<tab DetailView>,
//         KHÔNG bao giờ qua /mo-khoa; chưa có draw → /draw. freeDeep=false giữ nguyên hành vi cũ.
//         DetailView đọc ?topic= (congViec|tinhDuyen|taiLoc; value khác → mặc định hiện hành).
// Luật 3: khối luận ngắn home-today-free-line xuống HÀNG RIÊNG đầy bề rộng card (không còn
//         kẹt cột phải hẹp); pending tên quẻ = skeleton cùng vị trí, CẤM chữ "…"/câu lịch góm;
//         loading/error hợp nhất 1 kiểu skeleton trong card.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeView from '../src/views/HomeView.vue'
import DetailView from '../src/views/DetailView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), history: vi.fn(), hexagram: vi.fn(), createDraw: vi.fn(), track: vi.fn(), haoTexts: vi.fn(), requestInterpretation: vi.fn(), aiJob: vi.fn() } }
})

const routes = [
  { path: '/', name: 'home', component: HomeView },
  { path: '/draw', name: 'draw', component: { template: '<div/>' } },
  { path: '/que/:drawId', name: 'detail', component: DetailView },
  { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
  { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
  { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
]
const mk = () => createRouter({ history: createMemoryHistory(), routes })

// shape y hệt tests/home.test.js (draw §3.2 thuần, id 42)
const TODAY_DRAW = {
  id: 42, hexagram_id: 11, drawn_date: '2026-09-02',
  lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2],
  created_at: '2026-09-02T01:12:00Z',
}
const HX = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái'], dai_ci: 'Trời xuống đất lên.', vv_nien: 'v',
  free_content: {
    congViec: 'Việc mình nộp im re hôm kia đang được người ta mở ra xem.',
    tinhDuyen: 'Duyên đang tới chậm mà chắc.',
    taiLoc: 'Tiền về đúng lúc mình cần kiên nhẫn.',
  },
  ban_goc: {},
}
const drawOn = (id, date) => ({ id, hexagram_id: id + 60, drawn_date: date, lines_rolled: [7, 7, 7, 7, 7, 7], changing_lines: [], created_at: `${date}T02:00:00Z` })
const HISTORY_B = { data: [TODAY_DRAW, drawOn(41, '2026-09-01'), drawOn(40, '2026-08-31')], meta: { count: 3 } }
const HISTORY_C = { data: [drawOn(41, '2026-09-01'), drawOn(40, '2026-08-31')], meta: { count: 2 } }
const meFor = ({ today = null, history = false, freeDeep = false, server = '2026-09-02', entitlements = [] }) => ({
  device_id: 'dev12345678', is_new_device: !history, server_date_vn: server,
  entitlements, today_draw: today, free_deep: freeDeep,
})

async function mountAt(path) {
  const r = mk()
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
  await r.push(path)
  await r.isReady()
  await flushPromises()
  await flushPromises()
  return w
}

async function mountHome(mePayload, hist) {
  client.api.me.mockResolvedValue(mePayload)
  client.api.history.mockResolvedValue(hist)
  const r = mk()
  const w = mount(HomeView, { global: { plugins: [r] } })
  await r.push('/')
  await r.isReady()
  await flushPromises()
  await flushPromises()
  return w
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  document.title = 'x'
  client.api.me.mockResolvedValue(meFor({ today: TODAY_DRAW, history: true }))
  client.api.history.mockResolvedValue(HISTORY_B)
  client.api.hexagram.mockResolvedValue({ data: HX })
  client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 11, hao: [] } })
})

// ── LUẬT 1 ────────────────────────────────────────────────────────────────────
describe('[HOME-V4-A L1] chip freeDeep=true nhảy thẳng trang quẻ', () => {
  it('A1 — State B freeDeep=true: href 3 chip chứa /que/42 + topic=, KHÔNG chứa /mo-khoa', async () => {
    const w = await mountHome(
      meFor({ today: TODAY_DRAW, history: true, freeDeep: true, entitlements: ['duyen', 'tai_loc', 'xuat_hanh'] }),
      HISTORY_B,
    )
    // map topic→tab ngược theo DetailView (congViec→xuat_hanh, tinhDuyen→duyen, taiLoc→tai_loc)
    const expectTab = { duyen: 'tinhDuyen', 'tai-loc': 'taiLoc', 'xuat-hanh': 'congViec' }
    for (const [slug, tab] of Object.entries(expectTab)) {
      const href = w.find(`[data-testid="home-chip-${slug}"]`).attributes('href')
      expect(href).toContain('/que/42')
      expect(href).toContain(`topic=${tab}`)
      expect(href).not.toContain('/mo-khoa')
    }
    // pill ngôn ngữ free vẫn còn (không regress HOME-FE-V3)
    expect(w.find('[data-testid="home-chip-duyen-free"]').text()).toContain('Luận sâu MIỄN PHÍ')
  })

  it('chưa có draw (State A/C) freeDeep=true → cả 3 chip dẫn về /draw', async () => {
    const w = await mountHome(meFor({ history: true, freeDeep: true }), HISTORY_C)
    for (const slug of ['duyen', 'tai-loc', 'xuat-hanh']) {
      const href = w.find(`[data-testid="home-chip-${slug}"]`).attributes('href')
      expect(href).toBe('/draw')
      expect(href).not.toContain('/mo-khoa')
      expect(href).not.toContain('/que/')
    }
  })

  it('A1 regress — freeDeep=false giữ nguyên href /mo-khoa/<topic> (cả khi có draw hôm nay)', async () => {
    const w = await mountHome(meFor({ today: TODAY_DRAW, history: true, entitlements: ['duyen'] }), HISTORY_B)
    expect(w.find('[data-testid="home-chip-duyen"]').attributes('href')).toBe('/mo-khoa/duyen')
    expect(w.find('[data-testid="home-chip-tai-loc"]').attributes('href')).toBe('/mo-khoa/tai_loc')
    expect(w.find('[data-testid="home-chip-xuat-hanh"]').attributes('href')).toBe('/mo-khoa/xuat_hanh')
    expect(w.find('[data-testid="home-chip-duyen-state"]').text()).toContain('Đã mở')
    expect(w.find('[data-testid="home-chip-tai-loc-price"]').text()).toBe('29.000đ')
  })

  it('DetailView /que/42?topic=taiLoc → tab Tài lộc là tab mở đầu tiên (selected + free-slot đúng nội dung)', async () => {
    const w = await mountAt('/que/42?topic=taiLoc')
    const tabs = w.findAll('[role="tab"]')
    expect(tabs.length).toBe(3)
    expect(tabs[2].attributes('aria-selected')).toBe('true')
    expect(w.find('[data-testid="detail-free-slot"]').text()).toContain('Tiền về đúng lúc')
  })

  it('DetailView ?topic congViec/tinhDuyen → mở đúng tab tương ứng', async () => {
    const w1 = await mountAt('/que/42?topic=congViec')
    expect(w1.findAll('[role="tab"]')[0].attributes('aria-selected')).toBe('true')
    const w2 = await mountAt('/que/42?topic=tinhDuyen')
    expect(w2.findAll('[role="tab"]')[1].attributes('aria-selected')).toBe('true')
    expect(w2.find('[data-testid="detail-free-slot"]').text()).toContain('Duyên đang tới')
  })

  it('DetailView ?topic value không hợp lệ → mặc định hiện hành congViec', async () => {
    const w = await mountAt('/que/42?topic=linh-tinh')
    expect(w.findAll('[role="tab"]')[0].attributes('aria-selected')).toBe('true')
  })

  it('DetailView không có ?topic → mặc định congViec (không regress)', async () => {
    const w = await mountAt('/que/42')
    expect(w.findAll('[role="tab"]')[0].attributes('aria-selected')).toBe('true')
  })
})

// ── LUẬT 3 ────────────────────────────────────────────────────────────────────
describe('[HOME-V4-A L3] HomeTodayCard: luận ngắn hàng riêng + skeleton thống nhất', () => {
  it('luận ngắn là hàng RIÊNG trực tiếp của card (không còn kẹt trong cột phải của hàng ảnh quẻ)', async () => {
    const w = await mountHome(meFor({ today: TODAY_DRAW, history: true }), HISTORY_B)
    const card = w.find('[data-testid="home-today-card"]')
    const freeLine = w.find('[data-testid="home-today-free-line"]')
    expect(freeLine.exists()).toBe(true)
    // DOM: free-line là con trực tiếp của <article card> → bề rộng đầy đủ của card
    const parentTag = freeLine.element.parentElement.getAttribute('data-testid')
    expect(parentTag).toBe('home-today-card')
    // vẫn là khối hàng đầy → không nằm trong flex row với ảnh quẻ
    expect(freeLine.classes().join(' ')).toContain('block')
    // accent giữ lại
    expect(freeLine.classes().join(' ')).toContain('border-l-2')
    expect(freeLine.text()).toContain('Việc mình nộp')
  })

  it('pending khi #2 lỗi: skeleton cùng vị trí, CẤM chữ "…" và câu lịch góm; 1 kiểu cho symbol+name', async () => {
    client.api.hexagram.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const w = await mountHome(meFor({ today: TODAY_DRAW, history: true }), HISTORY_B)
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(true)
    const pendings = w.findAll('[data-testid="home-hexagram-pending"]')
    expect(pendings.length).toBe(2) // vị trí ảnh quẻ + vị trí tên quẻ
    for (const p of pendings) {
      expect(p.text()).toBe('') // skeleton thuần — không ký tự, không câu chữ
      expect(p.classes().join(' ')).toContain('animate-pulse')
      expect(p.classes().join(' ')).toContain('home-skeleton')
    }
    expect(w.html()).not.toContain('Chưa tải được tên quẻ')
    expect(w.find('[data-testid="home-today-free-line"]').exists()).toBe(false) // hx null → không luận ngắn
  })
})
