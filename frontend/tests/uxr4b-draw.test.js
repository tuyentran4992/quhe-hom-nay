// UXR-4b (t_31ef1ece) — DrawView khoảnh khắc reveal:
//  ĐX4ii (BUG THẬT @8b9d613, bắt buộc): setTimeout(router.push) auto-push KHÔNG cleanup →
//    timer chạy SAU khi component đã unmount, kéo khách về /que/:id trái ý. Fix:
//    onBeforeUnmount clearTimeout + guard tryGo (không push khi đã unmount). Test đỏ→xanh.
//  ĐX5+B1: khối reveal thêm 3 quyền chọn — nút chính «Mở bảng giải» (draw-goto-detail),
//    text-link mờ «Giữ lại thẻ quẻ hôm nay →» (draw-share-cta → /share-card?draw={id}),
//    link «Về trang chính» (draw-home-after → /). Bấm 1 trong 3 → clearTimeout auto-push.
//  Pending chậm (đã qua mốc done mà API chưa về) → spinner + «Thử lại» (draw-retry)
//    + «Về trang chính» (khách không kẹt màn không hành động).
//  AUTO_PUSH_S3_MS 600→2200 (nhịp SAU reveal); BẤT BIẾN: MAGIC_SEQUENCE_MS=1500 (C-08).
// Wording NGUYÊN VĂN /data/agents/copywriter-vn/outbox/t_UXR-W/wording.md mục 4.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DrawView from '../src/views/DrawView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { AUTO_PUSH_S3_MS, MAGIC_SEQUENCE_MS, DRAW_COPY } from '../src/constants.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { createDraw: vi.fn(), me: vi.fn(), today: vi.fn(), track: vi.fn() } }
})

const TODAY_DRAW = { id: 77, hexagram_id: 11, drawn_date: '2026-09-03', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: '2026-09-03T07:06:00+00:00' }
const ME_NOT_DRAWN = { device_id: 'd1', is_new_device: false, server_date_vn: '2026-09-03', entitlements: [], today_draw: null, free_deep: true }
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
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } }, // 4b: draw-share-cta
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

// bấm gieo → tới mốc reveal PA1 (3060ms): draw-result hiện, auto-push CHƯA nổ (còn 2200ms nữa)
async function reveal() {
  client.api.createDraw.mockResolvedValue(DRAWN_OK)
  const { r, w } = await mountView()
  await w.find('[data-testid="draw-start"]').trigger('click')
  await vi.advanceTimersByTimeAsync(3060)
  await flushPromises()
  expect(w.find('[data-testid="draw-result"]').exists()).toBe(true)
  return { r, w }
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
  _resetDeviceForTests()
  client.api.createDraw.mockReset()
  client.api.me.mockReset()
  client.api.today.mockReset()
  client.api.track.mockReset()
  client.api.me.mockResolvedValue(ME_NOT_DRAWN)
})
afterEach(() => vi.useRealTimers())

describe('UXR-4b CFG — AUTO_PUSH 600→2200, C-08 bất biến', () => {
  it('AUTO_PUSH_S3_MS = 2200 (nhịp SAU reveal, decision t_UXR3 B1)', () => {
    expect(AUTO_PUSH_S3_MS).toBe(2200)
  })
  it('BẤT BIẾN C-08 giữ nguyên: MAGIC_SEQUENCE_MS = 1500 (không đụng)', () => {
    expect(MAGIC_SEQUENCE_MS).toBe(1500)
  })
  it('auto-push nổ đúng nhịp 2200ms sau reveal (mặc định trôi, không bấm gì)', async () => {
    const { r } = await reveal()
    await vi.advanceTimersByTimeAsync(2199)
    expect(r.currentRoute.value.path).toBe('/')
    await vi.advanceTimersByTimeAsync(1)
    expect(r.currentRoute.value.path).toBe('/que/42')
  })
})

describe('UXR-4b ĐX4ii — bug auto-push sau unmount (RED @b66ef4b → GREEN)', () => {
  it('unmount TRƯỚC khi auto-push nổ → router KHÔNG bị kéo về /que/:id', async () => {
    const { r, w } = await reveal()
    w.unmount() // khách rời màn trong cửa sổ 2,2s (back/điều hướng) — bug cũ: timer sống dai
    await vi.advanceTimersByTimeAsync(3000)
    expect(r.currentRoute.value.path).not.toBe('/que/42')
    expect(r.currentRoute.value.path).toBe('/')
  })
  it('ca thật của bug: đang cửa sổ push khách bấm link sang trang khác → KHÔNG bị kéo lại /que/42', async () => {
    // draw-home-after (RouterLink) sang '/' — navigation do link BỊ timer push cũ đè lên
    // đúng bệnh @8b9d613: DrawView unmount nhưng router vẫn sống → setTimeout.push chạy tiếp.
    const { r, w } = await reveal()
    await w.find('[data-testid="draw-home-after"]').trigger('click')
    await flushPromises()
    await vi.advanceTimersByTimeAsync(3000)
    expect(r.currentRoute.value.path).toBe('/')
  })
  it('2 instance nối tiếp: unmount cái 1 giữa cửa sổ push → chỉ cái 2 được tới đích', async () => {
    const a = await reveal()
    a.w.unmount()
    const b = await reveal() // instance mới bấm gieo, tới reveal
    await vi.advanceTimersByTimeAsync(2200)
    expect(b.r.currentRoute.value.path).toBe('/que/42')
    expect(a.r.currentRoute.value.path).toBe('/') // timer chết của a không đụng đường
  })
})

describe('UXR-4b ĐX5+B1 — 3 quyền chọn sau reveal', () => {
  it('đủ 3 phần tử, wording nguyên văn UXR-W mục 4; share là text-link MỜ không phải btn-cinnabar', async () => {
    const { w } = await reveal()
    const detail = w.find('[data-testid="draw-goto-detail"]')
    const share = w.find('[data-testid="draw-share-cta"]')
    const home = w.find('[data-testid="draw-home-after"]')
    expect(detail.exists() && share.exists() && home.exists()).toBe(true)
    expect(detail.text()).toBe('Mở bảng giải')
    expect(share.text()).toBe('Giữ lại thẻ quẻ hôm nay →')
    expect(home.text()).toBe('Về trang chính')
    expect(share.attributes('href')).toBe('/share-card?draw=42')
    expect(home.attributes('href')).toBe('/')
    // anti-2-CTA: nút chia sẻ KHÔNG được mang btn-cinnabar (wording: text-link mờ)
    expect(share.classes().join(' ')).not.toMatch(/btn-cinnabar/)
    expect(share.element.tagName).toBe('A')
  })
  it('bấm «Mở bảng giải» → /que/42 NGAY + auto-push bị hủy (không push đúp sau 2200ms)', async () => {
    const { r, w } = await reveal()
    await w.find('[data-testid="draw-goto-detail"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.path).toBe('/que/42')
    await vi.advanceTimersByTimeAsync(3000)
    expect(r.currentRoute.value.path).toBe('/que/42') // vẫn đúng đích, không re-push
  })
  it('bấm «Giữ lại thẻ quẻ hôm nay →» → /share-card?draw=42 và KHÔNG bị kéo về /que/42', async () => {
    const { r, w } = await reveal()
    await w.find('[data-testid="draw-share-cta"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.name).toBe('share-card')
    expect(r.currentRoute.value.query.draw).toBe('42')
    await vi.advanceTimersByTimeAsync(3000)
    expect(r.currentRoute.value.name).toBe('share-card') // clearTimeout hiệu lực
  })
  it('bấm «Về trang chính» → / và KHÔNG bị kéo về /que/42', async () => {
    const { r, w } = await reveal()
    await w.find('[data-testid="draw-home-after"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.path).toBe('/')
    await vi.advanceTimersByTimeAsync(3000)
    expect(r.currentRoute.value.path).toBe('/')
  })
})

describe('UXR-4b — pending chậm: khách không kẹt màn không hành động', () => {
  it('qua mốc done mà API chưa về → spinner + «Thử lại» + «Về trang chính»', async () => {
    client.api.createDraw.mockReturnValue(new Promise(() => {})) // treo vĩnh viễn
    const { w } = await mountView()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(1500) // dưới mốc reveal: chỉ spinner
    await flushPromises()
    expect(w.find('[data-testid="draw-retry"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(1561) // t=3061 = done (PA1), API vẫn treo
    await flushPromises()
    expect(w.find('[data-testid="draw-spinner"]').exists()).toBe(true)
    expect(w.text()).toContain('Đang mở quẻ…')
    const retry = w.find('[data-testid="draw-retry"]')
    const home = w.find('[data-testid="draw-home-after"]')
    expect(retry.exists()).toBe(true)
    expect(retry.text()).toBe('Thử lại')
    expect(home.exists()).toBe(true)
    expect(home.attributes('href')).toBe('/')
  })
  it('pending chậm → «Về trang chính» thoát được ngay (không kẹt)', async () => {
    client.api.createDraw.mockReturnValue(new Promise(() => {}))
    const { r, w } = await mountView()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(3061)
    await flushPromises()
    await w.find('[data-testid="draw-home-after"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.path).toBe('/')
  })
  it('«Thử lại» khi pending chậm → bắn lại #3, reveal khối kết quả', async () => {
    client.api.createDraw.mockReturnValue(new Promise(() => {}))
    const { w } = await mountView()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(3061)
    await flushPromises()
    client.api.createDraw.mockResolvedValue(DRAWN_OK)
    await w.find('[data-testid="draw-retry"]').trigger('click') // #3 lần 2
    await vi.advanceTimersByTimeAsync(3060) // sequence mới tới mốc reveal PA1
    await flushPromises()
    expect(client.api.createDraw).toHaveBeenCalledTimes(2)
    expect(w.find('[data-testid="draw-result"]').exists()).toBe(true)
  })
})

describe('UXR-4b wording contract — DRAW_COPY surface duy nhất', () => {
  it('3 chuỗi mới đúng nguyên văn wording.md mục 4', () => {
    expect(DRAW_COPY.detailBtn).toBe('Mở bảng giải')
    expect(DRAW_COPY.shareCta).toBe('Giữ lại thẻ quẻ hôm nay →')
    expect(DRAW_COPY.homeAfter).toBe('Về trang chính')
    expect(DRAW_COPY.retryPending).toBe('Thử lại')
  })
})
