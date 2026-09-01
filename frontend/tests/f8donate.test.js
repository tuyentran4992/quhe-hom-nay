// [F8-FE] t_03424b76 — CTA "Lễ tùy tâm" (CONTRACT-F8-DONATE §C1/§C3/§C4/§C5).
// TDD RED trước code: 3 slice — useDevice.freeDeep → DetailView donate-cta-open → Paywall donateMode.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DetailView from '../src/views/DetailView.vue'
import PaywallView from '../src/views/PaywallView.vue'
import * as client from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), history: vi.fn(), haoTexts: vi.fn(),
      requestInterpretation: vi.fn(), aiJob: vi.fn(), createPayment: vi.fn(), paymentStatus: vi.fn(),
      track: vi.fn(), shareLinks: vi.fn(), shareCard: vi.fn(),
    },
  }
})

const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái'], dai_ci: 'Trời xuống đất lên.', vv_nien: 'v',
  free_content: { congViec: 'cv', tinhDuyen: 'td', taiLoc: 'tl' }, ban_goc: {},
}
// shape #1 theo C1: top-level free_deep bool; entitlements GIỮ shape mảng string
const me = (over = {}) => ({
  device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30',
  entitlements: [], today_draw: DRAW42, free_deep: false, ...over,
})

function mkRoutes(detailComp = DetailView) {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: detailComp },
      { path: '/mo-khoa/:topic', name: 'paywall', component: detailComp === PaywallView ? PaywallView : { template: '<div/>' } },
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
    ],
  })
}

// Wrapper phải unmount giữa các test: state.me là module-level — component cũ còn sống
// thì watch của nó vẫn phản ứng với #1 của test sau (app thật luôn unmount khi đổi route).
const wrappers = []
afterEach(() => { wrappers.splice(0).forEach((w) => w.unmount()) })

async function mountView(router, to) {
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [router] } })
  wrappers.push(w)
  await router.push(to)
  await router.isReady()
  await flushPromises()
  await flushPromises()
  await flushPromises()
  return { r: router, w }
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  document.body.innerHTML = ''
  client.api.me.mockResolvedValue(me())
  client.api.hexagram.mockResolvedValue({ data: HX11 })
  client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 11, hao: [] } })
  client.api.track.mockResolvedValue(null)
})

// ================= Slice 2 — C3: DetailView nút "Lễ tùy tâm ủng hộ" =================
describe('DetailView CTA donate (C3)', () => {
  it('freeDeep true → hiện nút donate-cta-open, nhãn "Lễ tùy tâm ủng hộ", button thật, SAU TopicGate; [UI-POLISH t_fc6387df] donate = btn-cinnabar NỔI BẬT NHẤT, share nhẹ hơn một bậc (không còn chip y hệt)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const { w } = await mountView(mkRoutes(), '/que/42')
    const btn = w.find('[data-testid="donate-cta-open"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Lễ tùy tâm ủng hộ')
    expect(btn.attributes('type')).toBe('button')
    // CTA donate là phần tử nổi bật nhất hàng hành động (SOUL finish-gate)
    expect(btn.attributes('class')).toContain('btn-cinnabar')
    // share KHÔNG còn giống hệt donate — nhẹ hơn một bậc (outline)
    const share = w.find('[data-testid="share-card-open"]')
    expect(btn.attributes('class')).not.toBe(share.attributes('class'))
    expect(share.attributes('class')).not.toContain('btn-cinnabar')
    // vị trí: SAU TopicGate trong cùng footer
    const html = w.html()
    expect(html.indexOf('data-testid="topic-gate"')).toBeLessThan(html.indexOf('data-testid="donate-cta-open"'))
  })

  it('freeDeep false + entitlements đủ 3 topic → ẨN nút + KHÔNG bắn donate_cta_shown (C1: không suy từ entitlements)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: false, entitlements: ['xuat_hanh', 'duyen', 'tai_loc'] }))
    const { w } = await mountView(mkRoutes(), '/que/42')
    expect(w.find('[data-testid="donate-cta-open"]').exists()).toBe(false)
    expect(client.api.track.mock.calls.filter((c) => c[0].name === 'donate_cta_shown').length).toBe(0)
  })

  it('đang loading (#2 chưa về) → chưa nút, chưa shown; data về + freeDeep → hiện, shown ĐÚNG 1 lần', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    let resolveHx
    client.api.hexagram.mockImplementation(() => new Promise((res) => { resolveHx = res }))
    const { w } = await mountView(mkRoutes(), '/que/42')
    expect(w.find('[data-testid="detail-loading"]').exists()).toBe(true)
    expect(w.find('[data-testid="donate-cta-open"]').exists()).toBe(false)
    expect(client.api.track).not.toHaveBeenCalled()
    resolveHx({ data: HX11 })
    await flushPromises(); await flushPromises()
    expect(w.find('[data-testid="donate-cta-open"]').exists()).toBe(true)
    const shown = client.api.track.mock.calls.filter((c) => c[0].name === 'donate_cta_shown')
    expect(shown.length).toBe(1)
    expect(shown[0][0].props).toEqual({ topic: 'xuat_hanh' }) // tab mặc định congViec→xuat_hanh
  })

  it('bấm nút → track donate_cta_click {topic tab hiện hành} + push /mo-khoa/{topic}?mode=donate', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const { r, w } = await mountView(mkRoutes(), '/que/42')
    client.api.track.mockClear()
    await w.find('[data-testid="detail-tab-tinh-duyen"]').trigger('click')
    await w.find('[data-testid="donate-cta-open"]').trigger('click')
    await flushPromises()
    const click = client.api.track.mock.calls.find((c) => c[0].name === 'donate_cta_click')
    expect(click).toBeTruthy()
    expect(click[0].props).toEqual({ topic: 'duyen' })
    expect(r.currentRoute.value.name).toBe('paywall')
    expect(r.currentRoute.value.params.topic).toBe('duyen')
    expect(r.currentRoute.value.query.mode).toBe('donate')
  })

  it('track click reject → vẫn chuyển route (fire-and-forget, .catch ăn — C2)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    client.api.track.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const { r, w } = await mountView(mkRoutes(), '/que/42')
    await w.find('[data-testid="donate-cta-open"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.name).toBe('paywall')
    expect(r.currentRoute.value.query.mode).toBe('donate')
  })

  it('không phá render hiện có: share-card-open + TopicGate vẫn nguyên, nút donate không phải dialog', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const { w } = await mountView(mkRoutes(), '/que/42')
    expect(w.find('[data-testid="share-card-open"]').exists()).toBe(true)
    expect(w.find('[data-testid="topic-gate"]').exists()).toBe(true)
    expect(document.querySelector('[role="dialog"]')).toBeNull()
  })
})

// ================= Slice 1 — C1: useDevice.freeDeep =================
describe('useDevice.freeDeep (C1)', () => {
  it('payload #1 có free_deep:true → computed freeDeep === true', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const d = useDevice()
    await d.load(true)
    expect(d.freeDeep.value).toBe(true)
  })

  it('free_deep:false → false; THIẾU key (BE chưa lên) → false, không crash', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: false }))
    const d = useDevice()
    await d.load(true)
    expect(d.freeDeep.value).toBe(false)
    const m = me()
    delete m.free_deep
    client.api.me.mockResolvedValue(m)
    await d.load(true)
    expect(d.freeDeep.value).toBe(false)
  })

  it('KHÔNG suy từ entitlements: đủ 3 topic mà free_deep:false → vẫn false (C1)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: false, entitlements: ['xuat_hanh', 'duyen', 'tai_loc'] }))
    const d = useDevice()
    await d.load(true)
    expect(d.entitlements.value.length).toBe(3)
    expect(d.freeDeep.value).toBe(false)
  })

  it('#10 refresh trả free_deep → state cập nhật (device đang xem, flag bật giữa phiên)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: false }))
    const d = useDevice()
    await d.load(true)
    expect(d.freeDeep.value).toBe(false)
    client.api.today.mockResolvedValue({ data: { today_draw: DRAW42, entitlements: [], server_date_vn: '2026-08-30', free_deep: true } })
    await d.refresh()
    expect(d.freeDeep.value).toBe(true)
  })
})

// ================= Slice 3 — C4: PaywallView mode=donate =================
async function mountS4(topic, query) {
  const r = mkRoutes(PaywallView)
  await r.push({ name: 'paywall', params: { topic }, query })
  await r.isReady()
  const w = mount(PaywallView, { global: { plugins: [r] } }) // pattern gốc paywall.test.js: push TRƯỚC mount để donate_open thấy topic (không mount 2 lần)
  wrappers.push(w)
  await w.vm.$nextTick()
  await flushPromises()
  await flushPromises()
  return { r, w }
}

describe('PaywallView donateMode (C4)', () => {
  it('?mode=donate + freeDeep true → donateMode: ẩn pay-unlock-btn, sạch mọi wording 29k/"Trả một lần"/"Mở khóa", h1 "Lễ tùy tâm", testid pay-mode-donate, block donate giữ nguyên', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const { w } = await mountS4('duyen', { mode: 'donate' })
    expect(w.find('[data-testid="pay-mode-donate"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-price"]').exists()).toBe(false)
    expect(w.text()).not.toMatch(/29[.,]?000|Mở khóa|Trả một lần/i)
    expect(w.find('h1').text()).toContain('Lễ tùy tâm')
    // block donate (chip + input + Gửi lễ) GIỮ NGUYÊN
    expect(w.find('[data-testid="pay-donate-block"]').exists()).toBe(true)
    expect(w.findAll('[data-testid="pay-donate-chip"]').length).toBe(4)
    expect(w.find('[data-testid="pay-donate-input"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-donate-btn"]').exists()).toBe(true)
  })

  it('adversary: ?mode=donate khi flag OFF → phớt lờ query, paywall 29k NGUYÊN BẢN (nút unlock + price + h1 Mở khóa)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: false }))
    const { w } = await mountS4('duyen', { mode: 'donate' })
    expect(w.find('[data-testid="pay-mode-donate"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-price"]').text()).toContain('29.000')
    expect(w.find('h1').text()).toContain('Mở khóa')
  })

  it('freeDeep true nhưng KHÔNG ?mode=donate → paywall 29k bình thường (mode chỉ bật qua query)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const { w } = await mountS4('duyen', {})
    expect(w.find('[data-testid="pay-mode-donate"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-price"]').exists()).toBe(true)
  })

  it('donateMode: donate_open (MKT-F6-fix) vẫn bắn ĐÚNG 1 lần, semantics không đổi (C4)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    await mountS4('tai_loc', { mode: 'donate' })
    const opens = client.api.track.mock.calls.filter((c) => c[0].name === 'donate_open')
    expect(opens.length).toBe(1)
    expect(opens[0][0].props).toEqual({ topic: 'tai_loc' })
  })

  it('donateMode: gửi lễ vẫn chạy #7 kind=donate (block donate giữ nguyên chức năng)', async () => {
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    client.api.createPayment.mockResolvedValue({ data: { order_code: 1, kind: 'donate', amount_vnd: 5000 } })
    const { w } = await mountS4('duyen', { mode: 'donate' })
    await w.find('[data-testid="pay-donate-input"]').setValue('5000')
    await w.find('[data-testid="pay-donate-btn"]').trigger('click')
    await flushPromises()
    const arg = client.api.createPayment.mock.calls[0][0]
    expect(arg.kind).toBe('donate')
    expect(arg.amount_vnd).toBe(5000)
    expect(w.find('[data-testid="pay-thanks"]').exists()).toBe(true)
  })
})
