// HOME-FE-V3 (t_a7026e13) — NAV-SPEC §1 (CEO chốt t_53f6274b): NavBar desktop +
// BottomTabs mobile mount trong App.vue shell; nav-donate gating freeDeep (#1 top-level
// free_deep, useDeviceApi.js:17); loại trừ DrawView + ShareCardView; DrawView có draw-back.
// TDD: file này ĐỎ trước khi component tồn tại.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import App from '../src/App.vue'
import NavBar from '../src/components/NavBar.vue'
import BottomTabs from '../src/components/BottomTabs.vue'
import DrawView from '../src/views/DrawView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: { me: vi.fn(), today: vi.fn(), history: vi.fn(), hexagram: vi.fn(), createDraw: vi.fn(), track: vi.fn() },
  }
})

const STUB = { template: '<div/>' }
const stubWithName = (name) => ({ template: '<div/>', name })

async function mk(startPath = '/') {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: STUB },
      { path: '/draw', name: 'draw', component: DrawView },
      { path: '/que/:drawId', name: 'detail', component: stubWithName('DetailView') },
      { path: '/mo-khoa/:topic', name: 'paywall', component: stubWithName('PaywallView') },
      { path: '/cua-ban', name: 'library', component: STUB },
      { path: '/share-card', name: 'share-card', component: stubWithName('ShareCardView') },
    ],
  })
  // push là async — PHẢI await trước khi mount, không router vẫn ở '/' (route.name
  // undefined làm hỏng cả loại trừ shell lẫn router-link-active).
  await r.push(startPath)
  return r
}

const ME_FREE = {
  device_id: 'd1', is_new_device: false, server_date_vn: '2026-09-02',
  entitlements: ['duyen', 'tai_loc', 'xuat_hanh'], today_draw: null, free_deep: true,
}
const ME_PAID = { ...ME_FREE, entitlements: [], free_deep: false }

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  client.api.me.mockResolvedValue(ME_FREE)
  client.api.today.mockResolvedValue({ data: {} })
  client.api.history.mockResolvedValue({ data: [], meta: { count: 0 } })
  client.api.createDraw.mockReturnValue(new Promise(() => {})) // treo — không reveal trong test
})

describe('NavBar — header chung desktop (NAV-SPEC §1a)', () => {
  it('đủ 4 testid × href đúng spec', async () => {
    const r = await mk('/')
    const w = mount(NavBar, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-brand"]').attributes('href')).toBe('/')
    expect(w.find('[data-testid="nav-draw"]').attributes('href')).toBe('/draw')
    expect(w.find('[data-testid="nav-library"]').attributes('href')).toBe('/cua-ban')
    // [HOME-V4-B] t_3647e25e — nav-donate về route riêng /tam-tu (hằng số DONATE_HREF)
    expect(w.find('[data-testid="nav-donate"]').attributes('href')).toBe('/tam-tu')
  })

  it('nav-donate HIỆN khi free_deep=true (mock #1)', async () => {
    client.api.me.mockResolvedValue(ME_FREE)
    const r = await mk('/')
    const w = mount(NavBar, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-donate"]').exists()).toBe(true)
  })

  it('nav-donate ẨN khi free_deep=false — 3 item kia vẫn đủ', async () => {
    client.api.me.mockResolvedValue(ME_PAID)
    const r = await mk('/')
    const w = mount(NavBar, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-donate"]').exists()).toBe(false)
    expect(w.find('[data-testid="nav-draw"]').exists()).toBe(true)
    expect(w.find('[data-testid="nav-library"]').exists()).toBe(true)
  })

  it('item đang mở có router-link-active (nav-library ở /cua-ban)', async () => {
    const r = await mk('/cua-ban')
    const w = mount(NavBar, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-library"]').classes()).toContain('router-link-active')
  })
})

describe('BottomTabs — tab mobile (NAV-SPEC §1b)', () => {
  it('đủ 3 testid × href đúng', async () => {
    const r = await mk('/')
    const w = mount(BottomTabs, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="tab-home"]').attributes('href')).toBe('/')
    expect(w.find('[data-testid="tab-draw"]').attributes('href')).toBe('/draw')
    expect(w.find('[data-testid="tab-library"]').attributes('href')).toBe('/cua-ban')
  })

  it('tab active tô router-link-active (tab-draw ở /draw... shell ẩn, test trực tiếp component)', async () => {
    const r = await mk('/draw')
    const w = mount(BottomTabs, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="tab-draw"]').classes()).toContain('router-link-active')
  })
})

describe('App.vue shell — mount điểm + loại trừ (NAV-SPEC §1a/§1c)', () => {
  it('/ → có NavBar + BottomTabs + DisclaimerBar cùng shell', async () => {
    const r = await mk('/')
    const w = mount(App, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-brand"]').exists()).toBe(true)
    expect(w.find('[data-testid="tab-home"]').exists()).toBe(true)
    expect(w.find('[data-testid="disclaimer-bar"]').exists()).toBe(true)
  })

  it('/draw → KHÔNG nav shell (nghi thức) nhưng DrawView có draw-back', async () => {
    const r = await mk('/draw')
    const w = mount(App, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-brand"]').exists()).toBe(false)
    expect(w.find('[data-testid="tab-home"]').exists()).toBe(false)
    const back = w.find('[data-testid="draw-back"]')
    expect(back.exists()).toBe(true)
    expect(back.attributes('href')).toBe('/')
  })

  it('/share-card → KHÔNG nav shell (overlay fullscreen)', async () => {
    const r = await mk('/share-card')
    const w = mount(App, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    expect(w.find('[data-testid="nav-brand"]').exists()).toBe(false)
    expect(w.find('[data-testid="tab-library"]').exists()).toBe(false)
  })

  it('/cua-ban + /mo-khoa/duyen + /que/1 → nav shell hiện (mọi màn khác)', async () => {
    for (const p of ['/cua-ban', '/mo-khoa/duyen', '/que/1']) {
      const r = await mk(p)
      const w = mount(App, { global: { plugins: [r] } })
      await r.isReady()
      await flushPromises()
      expect(w.find('[data-testid="nav-brand"]').exists()).toBe(true)
      w.unmount()
    }
  })
})
