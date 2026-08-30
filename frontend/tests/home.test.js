// HomeView S1 — shape THẬT theo 03-api: #1 today_draw = draw §3.2 thuần (KHÔNG embed
// hexagram) → S1 phải tra #2 theo hexagram_id để hiện symbol/ten/slot congViec.
// Reload cùng ngày = #1 trả lại draw cũ (idempotent phía BE) → S1 KHÔNG gọi #3.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeView from '../src/views/HomeView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), createDraw: vi.fn() } }
})

const routes = [
  { path: '/', name: 'home', component: HomeView },
  { path: '/draw', name: 'draw', component: { template: '<div/>' } },
  { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
  { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
  { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
]

function mk() {
  return createRouter({ history: createMemoryHistory(), routes })
}

// draw §3.2 thuần — đúng như BE-1 toApi() trả, không có field hexagram
const TODAY_DRAW = {
  id: 42, hexagram_id: 11, drawn_date: '2026-08-30',
  lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2],
  created_at: '2026-08-30T02:15:00Z',
}
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊',
  lines: [1, 1, 1, 0, 0, 0], keywords: ['thái', 'giao thái', 'hanh thông', 'quẻ tốt'],
  dai_ci: 'Trời xuống đất lên, giao nhau nên thông.',
  free_content: { congViec: 'Việc mình nộp im re hôm kia đang được người ta mở ra xem.', tinhDuyen: 'x', taiLoc: 'y' },
}
const DRAWN = {
  device_id: 'dev12345678', is_new_device: false, server_date_vn: '2026-08-30',
  entitlements: ['duyen'], today_draw: TODAY_DRAW,
}
const EMPTY = { device_id: 'dev12345678', is_new_device: true, server_date_vn: '2026-08-30', entitlements: [], today_draw: null }

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  document.title = 'x'
  client.api.hexagram.mockResolvedValue({ data: HX11 })
})

describe('HomeView', () => {
  it('đang tải → hiện loading, không trắng', async () => {
    client.api.me.mockReturnValue(new Promise(() => {}))
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    expect(w.find('[data-testid="home-loading"]').exists()).toBe(true)
  })

  it('today_draw=null → home-cta-card + nút gieo (link /draw), KHÔNG gọi #3', async () => {
    client.api.me.mockResolvedValue(EMPTY)
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-cta-card"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-cta-draw"]').exists()).toBe(true)
    expect(client.api.createDraw).not.toHaveBeenCalled() // reload không tự gieo
  })

  it('AC2 idempotent: #1 trả draw cũ cùng ngày → S1 hiện lại quẻ qua #2, không gieo mới', async () => {
    client.api.me.mockResolvedValue(DRAWN) // BE trả đúng row draws hôm nay — không tạo mới
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(true)
    expect(client.api.hexagram).toHaveBeenCalledWith(11) // shape thật: tra #2 theo hexagram_id
    expect(client.api.createDraw).not.toHaveBeenCalled()
    expect(w.find('[data-testid="home-hexagram-name"]').text()).toContain('Địa Thiên Thái')
    expect(w.find('[data-testid="home-server-date"]').text()).toBe('30/08/2026')
    expect(w.find('[data-testid="home-slot-congViec"]').text()).toContain('Việc mình nộp')
    expect(w.find('[data-testid="home-link-detail"]').attributes('href')).toBe('/que/42')
    expect(w.find('[data-testid="home-changing-lines"]').text()).toContain('Hào 2 động')
  })

  it('TESTIDS #9/#23/#24: link-inline S1→S3 + nav desktop draw/library tồn tại', async () => {
    client.api.me.mockResolvedValue(DRAWN)
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-link-detail-inline"]').attributes('href')).toBe('/que/42')
    expect(w.find('[data-testid="home-nav-draw"]').attributes('href')).toBe('/draw')
    expect(w.find('[data-testid="home-nav-library"]').attributes('href')).toBe('/cua-ban')
  })

  it('chip duyen đã mở (✓), tai_loc/xuat_hanh khóa + giá 29.000đ → link S4', async () => {
    client.api.me.mockResolvedValue(DRAWN)
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-chip-duyen-state"]').text()).toContain('đã mở')
    expect(w.find('[data-testid="home-chip-tai-loc-price"]').text()).toBe('29.000đ')
    expect(w.find('[data-testid="home-chip-tai-loc"]').attributes('href')).toBe('/mo-khoa/tai_loc')
  })

  it('lỗi API → home-error (không trắng màn)', async () => {
    client.api.me.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-error"]').exists()).toBe(true)
  })

  it('#2 lỗi khi đã có draw → vẫn hiện card, slot thay trạng thái lỗi, không trắng', async () => {
    client.api.me.mockResolvedValue(DRAWN)
    client.api.hexagram.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-hexagram-pending"]').exists()).toBe(true)
  })
})
