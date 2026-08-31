// F7-FE ShareCardView /share-card — TDD RED. Overlay fullscreen (SPEC-THE §1):
// thẻ thật + 2 toggle khung + 3 hành động. jsdom KHÔNG có canvas 2d context thật →
// renderer module được mock; hành vi E1 fallback + track V1-V4 assert qua fetch mock.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ShareCardView from '../src/views/ShareCardView.vue'
import * as client from '../src/api/client.js'
import { routes } from '../src/router/index.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'
import { renderFrame } from '../src/utils/shareCardCanvas.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), haoTexts: vi.fn(), history: vi.fn(),
      shareLinks: vi.fn(), shareCard: vi.fn(),
    },
  }
})
vi.mock('../src/utils/shareCardCanvas.js', async (orig) => {
  const real = await orig()
  return { ...real, renderFrame: vi.fn() }
})

const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái', 'giao thái', 'hanh thông', 'quẻ tốt'],
  dai_ci: 'Trời xuống đất lên, giao nhau nên thông.',
  vv_nien: 'v', free_content: { congViec: 'cv', tinhDuyen: 'td', taiLoc: 'tl' }, ban_goc: {},
}
const LINK = { token: 'Ab3dE9fGh1', url: 'https://que.today/s/Ab3dE9fGh1' }
const FAKE_CANVAS = { width: 1080, height: 1920, toBlob: null }

function trackBodies() {
  return globalThis.fetch.mock.calls.map((c) => JSON.parse(c[1].body).name)
}
function lastTrackProps() {
  const call = globalThis.fetch.mock.calls.filter((c) => c[0] === '/api/track').pop()
  return JSON.parse(call[1].body)
}
function trackCall(name) {
  const call = globalThis.fetch.mock.calls.find((c) => c[0] === '/api/track' && JSON.parse(c[1].body).name === name)
  return call && JSON.parse(call[1].body)
}

async function mountView() {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/share-card', name: 'share-card', component: ShareCardView }],
  })
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
  await r.push('/share-card?draw=42')
  await r.isReady()
  await flushPromises()
  await flushPromises()
  await flushPromises()
  return { w, r }
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 204 })
  client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: DRAW42 })
  client.api.hexagram.mockResolvedValue({ data: HX11 })
  client.api.history.mockResolvedValue({ data: [DRAW42] })
  client.api.haoTexts.mockResolvedValue({ data: { hao: [{ vi: 2, hao: 'Lục nhị', han: 'h', quoc_am: 'a', nghia: 'Bao dung được cả chỗ hoang vu, như vượt sông không còn gì để mất.' }] } })
  client.api.shareLinks.mockResolvedValue(LINK)
  renderFrame.mockResolvedValue({ canvas: FAKE_CANVAS, blob: { size: 1234 }, ms: 42, dataUrl: 'data:image/png;base64,AAA' })
})

afterEach(() => {
  delete globalThis.fetch
  delete navigator.share
  delete navigator.clipboard
  vi.unstubAllGlobals()
})

describe('router', () => {
  it('có route /share-card trong routes SPA', () => {
    expect(routes.some((r) => r.path === '/share-card')).toBe(true)
  })
})

describe('overlay /share-card', () => {
  it('mount → root testid share-card-open + đủ 5 testid hành động/khung FE-side', async () => {
    const { w } = await mountView()
    expect(w.find('[data-testid="share-card-open"]').exists()).toBe(true)
    for (const tid of ['share-card-frame-9x16', 'share-card-frame-1x1', 'share-card-download', 'share-card-copy-link', 'share-card-native'])
      expect(w.find(`[data-testid="${tid}"]`).exists()).toBe(true)
  })

  it('V1 share_card_open bắn khi overlay hiện, ĐÚNG params (hào động → has_dynamic_line true)', async () => {
    await mountView()
    await vi.waitFor(() => expect(trackBodies()).toContain('share_card_open'))
    expect(trackCall('share_card_open')).toEqual({
      name: 'share_card_open',
      props: { draw_id: 42, hexagram_id: 11, has_dynamic_line: true },
    })
  })

  it('0 hào động → has_dynamic_line false + hook TH2 dai_ci vế đầu', async () => {
    client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: { ...DRAW42, changing_lines: [] } })
    const { w } = await mountView()
    await vi.waitFor(() => expect(trackBodies()).toContain('share_card_open'))
    expect(trackCall('share_card_open').props.has_dynamic_line).toBe(false)
    expect(renderFrame).toHaveBeenCalled()
    const model = renderFrame.mock.calls[0][0]
    expect(model.hook_text).toBe('Trời xuống đất lên')
    expect(w.exists()).toBe(true)
  })

  it('V2 share_card_created bắn sau khung đầu render xong {draw_id, frame:"9x16", render_ms}', async () => {
    await mountView()
    await vi.waitFor(() => expect(trackBodies()).toContain('share_card_created'))
    expect(trackCall('share_card_created').props).toMatchObject({ draw_id: 42, frame: '9x16', render_ms: expect.any(Number) })
  })

  it('POST /api/share-links gọi với draw_id; QR text = url /s/{token}', async () => {
    await mountView()
    expect(client.api.shareLinks).toHaveBeenCalledWith(42)
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    const model = renderFrame.mock.calls[0][0]
    expect(model.qr_text).toBe(LINK.url)
    expect(model.url).toBe(LINK.url)
  })

  it('share-links fail (BE chưa merge) → thẻ VẪN vẽ + copy dùng URL dự phòng /app/que/{id}, reason share_link_failed (không chặn UX)', async () => {
    client.api.shareLinks.mockRejectedValue(new Error('503'))
    await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    const model = renderFrame.mock.calls[0][0]
    expect(model.token).toBeNull()
    expect(model.url).toContain('/que/42')
    expect(trackBodies()).not.toContain('share_card_error') // link fail ≠ E1 render fail
  })

  it('E1: renderFrame throw → fallback thẻ HTML (testid share-card-fallback) + V3 share_card_error + Copy link vẫn chạy', async () => {
    renderFrame.mockRejectedValue(new Error('canvas exception'))
    const { w } = await mountView()
    await vi.waitFor(() => expect(trackBodies()).toContain('share_card_error'))
    expect(w.find('[data-testid="share-card-fallback"]').exists()).toBe(true)
    expect(trackCall('share_card_error').props).toMatchObject({ draw_id: 42, reason: expect.any(String) })
    // copy hoạt động: clipboard mock
    const write = vi.fn().mockResolvedValue()
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: write }, configurable: true })
    await w.find('[data-testid="share-card-copy-link"]').trigger('click')
    await flushPromises()
    expect(write).toHaveBeenCalledTimes(1)
    expect(write.mock.calls[0][0]).toContain(LINK.url) // fallback không chặn copy — link vẫn là /s/{token}
    expect(trackBodies()).toContain('share_card_done')
  })
})

describe('toggle 2 khung', () => {
  it('mặc định 9:16 active; bấm 1x1 → renderFrame gọi với FRAME_1x1 + aria-pressed đổi', async () => {
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    expect(w.find('[data-testid="share-card-frame-9x16"]').attributes('aria-pressed')).toBe('true')
    await w.find('[data-testid="share-card-frame-1x1"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="share-card-frame-1x1"]').attributes('aria-pressed')).toBe('true')
    const real = await import('../src/utils/shareCardCanvas.js')
    const last = renderFrame.mock.calls.at(-1)
    expect(last[1]).toBe(real.FRAME_1X1)
  })
})

describe('3 hành động overlay', () => {
  it('Copy link 9:16 = NGUYÊN URL /s/{token} (không caption), bắn V4 method=copy', async () => {
    const write = vi.fn().mockResolvedValue()
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: write }, configurable: true })
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    await w.find('[data-testid="share-card-copy-link"]').trigger('click')
    await flushPromises()
    expect(write).toHaveBeenCalledWith(LINK.url)
    expect(lastTrackProps()).toEqual({ name: 'share_card_done', props: { draw_id: 42, method: 'copy', token: LINK.token } })
  })

  it('Copy link khi đang khung 1:1 = CAPTION_1X1 + "\\n" + URL (clipboard rule CAP-THE)', async () => {
    const write = vi.fn().mockResolvedValue()
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: write }, configurable: true })
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    await w.find('[data-testid="share-card-frame-1x1"]').trigger('click')
    await flushPromises()
    await w.find('[data-testid="share-card-copy-link"]').trigger('click')
    await flushPromises()
    expect(write.mock.calls.at(-1)[0]).toBe(`Địa Thiên Thái — bạn là quẻ nào?\n${LINK.url}`)
  })

  it('Tải ảnh = toBlob PNG tên que-{token}.png; bắn V4 method=download', async () => {
    const blob = { type: 'image/png' }
    const toBlob = vi.fn((cb) => cb(blob))
    renderFrame.mockResolvedValue({ canvas: { toBlob }, blob, ms: 10, dataUrl: 'data:,' })
    const createObjectURL = vi.fn(() => 'blob:x')
    const revoke = vi.fn()
    vi.stubGlobal('URL', Object.assign(Object.create(URL), { createObjectURL: createObjectURL, revokeObjectURL: revoke }))
    const clicked = []
    const realClick = HTMLAnchorElement.prototype.click
    HTMLAnchorElement.prototype.click = function () { clicked.push(this.download) }
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    await w.find('[data-testid="share-card-download"]').trigger('click')
    await vi.waitFor(() => expect(clicked.length).toBe(1))
    HTMLAnchorElement.prototype.click = realClick
    expect(clicked[0]).toBe('que-Ab3dE9fGh1.png')
    expect(lastTrackProps().props).toMatchObject({ draw_id: 42, method: 'download', token: LINK.token })
  })

  it('Chia sẻ native: navigator.share với files(PNG)+text(CAPTION_NATIVE render tên quẻ); success → V4 method=native', async () => {
    const share = vi.fn().mockResolvedValue()
    Object.defineProperty(navigator, 'share', { value: share, configurable: true })
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    await w.find('[data-testid="share-card-native"]').trigger('click')
    await vi.waitFor(() => expect(share).toHaveBeenCalledTimes(1))
    const data = share.mock.calls[0][0]
    expect(data.text).toBe('Hôm nay tôi là Địa Thiên Thái — bạn là quẻ nào?')
    expect(data.files && data.files[0]).toBeTruthy()
    expect(lastTrackProps().props).toMatchObject({ draw_id: 42, method: 'native', token: LINK.token })
  })

  it('E2: navigator.share reject (cancel/unsupported) → IM LẶNG, không bắn done, không alert', async () => {
    const share = vi.fn().mockRejectedValue(new Error('AbortError'))
    Object.defineProperty(navigator, 'share', { value: share, configurable: true })
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    await w.find('[data-testid="share-card-native"]').trigger('click')
    await new Promise((r) => setTimeout(r, 0))
    await flushPromises()
    expect(trackBodies()).not.toContain('share_card_done')
    expect(alertSpy).not.toHaveBeenCalled()
  })

  it('E2: unsupported (không có navigator.share) → bấm không chết, không done', async () => {
    const { w } = await mountView()
    await vi.waitFor(() => expect(renderFrame).toHaveBeenCalled())
    await w.find('[data-testid="share-card-native"]').trigger('click')
    await new Promise((r) => setTimeout(r, 0))
    expect(trackBodies()).not.toContain('share_card_done')
  })
})
