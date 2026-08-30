// S5 Library FE-1 — #4 trả draw[] §3.2 THUẦN (không embed hexagram). Tên/symbol mỗi dòng
// phải tra #2 (qua cache useHexagrams, 1 request/quẻ). Dòng hôm nay có link S3.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import LibraryView from '../src/views/LibraryView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests, useHexagrams } from '../src/composables/useHexagrams.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), history: vi.fn(), hexagram: vi.fn() } }
})

const D42 = { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const D7 = { id: 7, hexagram_id: 3, drawn_date: '2026-08-29', lines_rolled: [8, 7, 7, 8, 9, 7], changing_lines: [5], created_at: 't' }
const HX11 = { id: 11, ten: 'Địa Thiên Thái', symbol: '䷊' }
const HX3 = { id: 3, ten: 'Thuỷ Sơn Truân', symbol: '䷂' }

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
      { path: '/cua-ban', name: 'library', component: LibraryView },
    ],
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  client.api.today.mockResolvedValue({ data: { today_draw: D42, entitlements: [], server_date_vn: '2026-08-30' } })
  client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: D42 })
  client.api.hexagram.mockImplementation(async (id) => ({ data: id === 11 ? HX11 : HX3 }))
})

describe('LibraryView', () => {
  it('timeline #4: mỗi dòng hiện ten+symbol THẬT tra từ #2 (không phải "Quẻ #id")', async () => {
    client.api.history.mockResolvedValue({ data: [D42, D7], meta: { count: 2 } })
    const r = mk()
    const w = mount(LibraryView, { global: { plugins: [r] } })
    await flushPromises()
    await flushPromises()
    expect(client.api.history).toHaveBeenCalledWith(20)
    const names = w.findAll('[data-testid="lib-item-name"]').map((e) => e.text())
    expect(names).toEqual(['Địa Thiên Thái', 'Thuỷ Sơn Truân'])
    const syms = w.findAll('[data-testid="lib-item-symbol"]').map((e) => e.text())
    expect(syms).toEqual(['䷊', '䷂'])
    const dates = w.findAll('[data-testid="lib-item-date"]').map((e) => e.text())
    expect(dates).toEqual(['30/08/2026', '29/08/2026'])
  })

  it('#2 lỗi 1 quẻ → dòng vẫn render (symbol fallback), không sập cả danh sách', async () => {
    client.api.history.mockResolvedValue({ data: [D7], meta: { count: 1 } })
    client.api.hexagram.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const r = mk()
    const w = mount(LibraryView, { global: { plugins: [r] } })
    await flushPromises()
    await flushPromises()
    expect(w.findAll('[data-testid="lib-item-symbol"]').length).toBe(1)
    expect(w.find('[data-testid="lib-item-name"]').text()).toContain('Quẻ #3')
  })

  it('cache module: vào lại màn → #2 không gọi lại cho quẻ đã tra', async () => {
    client.api.history.mockResolvedValue({ data: [D7], meta: { count: 1 } })
    const r = mk()
    const w1 = mount(LibraryView, { global: { plugins: [r] } })
    await flushPromises()
    await flushPromises()
    expect(client.api.hexagram).toHaveBeenCalledTimes(1)
    w1.unmount()
    mount(LibraryView, { global: { plugins: [r] } })
    await flushPromises()
    await flushPromises()
    expect(client.api.hexagram).toHaveBeenCalledTimes(1) // cache dùng lại
  })

  it('rỗng → lib-empty "Chưa có quẻ nào."; lỗi #4 → lib-error + retry', async () => {
    client.api.history.mockResolvedValue({ data: [], meta: { count: 0 } })
    const r = mk()
    const w = mount(LibraryView, { global: { plugins: [r] } })
    await flushPromises()
    expect(w.find('[data-testid="lib-empty"]').text()).toContain('Chưa có quẻ nào')
    w.unmount()
    client.api.history.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    _resetHexagramCacheForTests()
    const w2 = mount(LibraryView, { global: { plugins: [r] } })
    await flushPromises()
    expect(w2.find('[data-testid="lib-error"]').exists()).toBe(true)
    expect(w2.find('[data-testid="lib-retry"]').exists()).toBe(true)
  })
})
