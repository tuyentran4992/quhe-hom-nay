// F7-FE nút S3 (DetailView) + api client shareLinks/shareCard — TDD RED.
// SPEC-THE §1: nút "Chia sẻ thẻ quẻ" chip paper2 cạnh "Xin luận sâu", hiện SAU khi
// dữ liệu #3/#2 render xong; CẤM popup chặn reveal. Route overlay = /share-card?draw={id}.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DetailView from '../src/views/DetailView.vue'
import * as client from '../src/api/client.js'
import { api } from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), history: vi.fn(), haoTexts: vi.fn(),
      requestInterpretation: vi.fn(), aiJob: vi.fn(), shareLinks: vi.fn(), shareCard: vi.fn(),
    },
  }
})

const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái'], dai_ci: 'Trời xuống đất lên, giao nhau nên thông.', vv_nien: 'v',
  free_content: { congViec: 'cv', tinhDuyen: 'td', taiLoc: 'tl' }, ban_goc: {},
}

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: DetailView },
      { path: '/share-card', name: 'share-card', component: { template: '<div data-testid="share-card-open"/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
    ],
  })
}

async function mountAt(path) {
  const r = mk()
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
  await r.push(path)
  await r.isReady()
  await flushPromises()
  await flushPromises()
  await flushPromises()
  return { r, w }
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: DRAW42 })
  client.api.hexagram.mockResolvedValue({ data: HX11 })
  client.api.haoTexts.mockResolvedValue({ data: { hao: [{ vi: 2, nghia: 'n2' }] } })
})

describe('nút Chia sẻ thẻ quẻ ở S3', () => {
  it('data render xong → có nút data-testid="share-card-open" nhãn "Chia sẻ thẻ quẻ"', async () => {
    const { w } = await mountAt('/que/42')
    const btn = w.find('[data-testid="share-card-open"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Chia sẻ thẻ quẻ')
    expect(btn.attributes('type')).toBe('button')
  })

  it('đang loading (#2 chưa về) → CHƯA có nút (render SAU #3/#2, SPEC §1)', async () => {
    let resolveHx
    client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: DRAW42 })
    client.api.hexagram.mockImplementation(() => new Promise((res) => { resolveHx = res }))
    const { w } = await mountAt('/que/42')
    expect(w.find('[data-testid="detail-loading"]').exists()).toBe(true)
    expect(w.find('[data-testid="share-card-open"]').exists()).toBe(false)
    resolveHx({ data: HX11 })
    await flushPromises()
    await flushPromises()
    expect(w.find('[data-testid="share-card-open"]').exists()).toBe(true)
  })

  it('lỗi tải (#4 không có draw) → không nút, không popup', async () => {
    client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: null })
    client.api.history.mockResolvedValue({ data: [] })
    const { w } = await mountAt('/que/99')
    expect(w.find('[data-testid="detail-error"]').exists()).toBe(true)
    expect(w.find('[data-testid="share-card-open"]').exists()).toBe(false)
  })

  it('bấm nút → router push /share-card?draw=42 (overlay, không popup chắn)', async () => {
    const { r, w } = await mountAt('/que/42')
    await w.find('[data-testid="share-card-open"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.name).toBe('share-card')
    expect(r.currentRoute.value.query.draw).toBe('42')
  })

  it('không đổi render hiện có: LineChart + TopicGate vẫn nguyên vị trí, nút KHÔNG phải modal/dialog', async () => {
    const { w } = await mountAt('/que/42')
    expect(w.find('[data-testid="detail-linechart"]').exists()).toBe(true)
    expect(w.find('[data-testid="topic-gate"]').exists()).toBe(true)
    const btn = w.find('[data-testid="share-card-open"]')
    expect(btn.attributes('role')).not.toBe('dialog')
    expect(document.querySelector('.modal, [role="dialog"]')).toBeNull()
  })
})

describe('api client #share', () => {
  it('shareLinks(draw_id) → POST /api/share-links body {draw_id}', async () => {
    const { api: realApi } = await vi.importActual('../src/api/client.js')
    const f = vi.fn().mockResolvedValue({ ok: true, status: 201, json: async () => ({ token: 't', url: 'u' }) })
    globalThis.fetch = f
    const r = await realApi.shareLinks(42)
    const [url, opt] = f.mock.calls[0]
    expect(url).toBe('/api/share-links')
    expect(opt.method).toBe('POST')
    expect(JSON.parse(opt.body)).toEqual({ draw_id: 42 })
    expect(r).toEqual({ token: 't', url: 'u' })
    delete globalThis.fetch
  })

  it('shareCard(token) → GET /api/share-links/{token}', async () => {
    const { api: realApi } = await vi.importActual('../src/api/client.js')
    const payload = { card: { hexagram_id: 1 }, sharer_label: 'Khách 4F2A', views: 3 }
    const f = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => payload })
    globalThis.fetch = f
    const r = await realApi.shareCard('Ab3dE9fGh1')
    expect(f.mock.calls[0][0]).toBe('/api/share-links/Ab3dE9fGh1')
    expect(r.sharer_label).toBe('Khách 4F2A')
    delete globalThis.fetch
  })
})
