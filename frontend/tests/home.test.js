// HomeView S1 — 2 nhánh today_draw null/không-null + chip entitlements (TESTIDS.md #3..#22).
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeView from '../src/views/HomeView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn() } }
})

const routes = [
  { path: '/', name: 'home', component: HomeView },
  { path: '/draw', name: 'draw', component: { template: '<div/>' } },
  { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
  { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
]

function mk() {
  return createRouter({ history: createMemoryHistory(), routes })
}

const DRAWN = {
  device_id: 'dev12345678', is_new_device: false, server_date_vn: '2026-08-30',
  entitlements: ['duyen'],
  today_draw: {
    id: 42, hexagram_id: 11, drawn_date: '2026-08-30',
    lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2],
    created_at: '2026-08-30T02:15:00Z',
    hexagram: {
      id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊',
      lines: [1, 1, 1, 0, 0, 0], keywords: ['thái', 'giao thái', 'hanh thông', 'quẻ tốt'],
      daiCI: 'Trời xuống đất lên, giao nhau nên thông.',
      free_content: { congViec: 'Việc mình nộp im re hôm kia đang được người ta mở ra xem.', tinhDuyen: 'x', taiLoc: 'y' },
    },
  },
}
const EMPTY = { device_id: 'dev12345678', is_new_device: true, server_date_vn: '2026-08-30', entitlements: [], today_draw: null }

beforeEach(() => { vi.clearAllMocks(); _resetDeviceForTests(); document.title = 'x' })

describe('HomeView', () => {
  it('đang tải → hiện loading, không trắng', async () => {
    client.api.me.mockReturnValue(new Promise(() => {}))
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    expect(w.find('[data-testid="home-loading"]').exists()).toBe(true)
  })

  it('today_draw=null → home-cta-card + nút gieo (link /draw)', async () => {
    client.api.me.mockResolvedValue(EMPTY)
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-cta-card"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(false)
    expect(w.find('[data-testid="home-cta-draw"]').exists()).toBe(true)
  })

  it('today_draw có → card tóm tắt: symbol, ten, date dd/MM/yyyy, slot congViec, link /que/42', async () => {
    client.api.me.mockResolvedValue(DRAWN)
    const r = mk()
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="home-today-card"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-hexagram-name"]').text()).toContain('Địa Thiên Thái')
    expect(w.find('[data-testid="home-server-date"]').text()).toBe('30/08/2026')
    expect(w.find('[data-testid="home-slot-congViec"]').text()).toContain('Việc mình nộp')
    expect(w.find('[data-testid="home-link-detail"]').attributes('href')).toBe('/que/42')
    expect(w.find('[data-testid="home-changing-lines"]').text()).toContain('Hào 2 động')
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
})
