// [HOME-V4-B] t_3647e25e — Luật 2: /tam-tu là route RIÊNG cho lễ tùy tâm.
// TDD RED trước code: (a) /tam-tu render DonateView có pay-donate-block;
// (b) PaywallView thuần LUÔN hiện PRICE_LABEL + pay-unlock-btn, không còn nhánh donate;
// (c) luồng donate chuyển nhà nguyên vẹn (payload #7 kind=donate, QR, thanks) —
// data-testid + tracking event name GIỮ NGUYÊN theo card §1.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PaywallView from '../src/views/PaywallView.vue'
import DonateView from '../src/views/DonateView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { PRICE_LABEL, PRICE_UNLOCK_VND } from '../src/constants.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), createPayment: vi.fn(), paymentStatus: vi.fn(), track: vi.fn() } }
})

const DONATE_ORDER = {
  order_code: 384721, kind: 'donate', topic: null, amount_vnd: 50000, status: 'pending',
  qr_data: 'vietqr/action/qr/970436/stub384721/50000/Qu+Hom+Nay',
  confirm_url: 'http://127.0.0.1:5380/api/payments/384721/simulate-paid',
  checkout_url: '/pay/384721', stub: true, expires_at: '2026-08-31T17:00:00Z',
}
const ME_FREE = { device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: null, free_deep: true }

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: PaywallView },
      { path: '/tam-tu', name: 'donate', component: DonateView },
    ],
  })
}

beforeEach(() => { vi.clearAllMocks(); _resetDeviceForTests(); client.api.me.mockResolvedValue(ME_FREE); client.api.track.mockResolvedValue(null) })
afterEach(() => vi.useRealTimers())

async function mountDonate() {
  const r = mk()
  await r.push('/tam-tu')
  await r.isReady()
  const w = mount(DonateView, { global: { plugins: [r] } })
  await flushPromises(); await flushPromises()
  return { r, w }
}
async function sendOffer(w, value = '50000') {
  client.api.createPayment.mockResolvedValue({ data: DONATE_ORDER })
  await w.find('[data-testid="pay-donate-input"]').setValue(value)
  await w.find('[data-testid="pay-donate-btn"]').trigger('click')
  await flushPromises()
}

// ============ (a) /tam-tu = màn lễ tùy tâm ============
describe('DonateView /tam-tu — màn lễ tùy tâm độc lập (HOME-V4-B)', () => {
  it('render DonateView: pay-mode-donate + pay-donate-block, 4 chip mức lễ, h1 "Lễ tùy tâm"', async () => {
    const { w } = await mountDonate()
    expect(w.find('[data-testid="pay-mode-donate"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-donate-block"]').exists()).toBe(true)
    expect(w.findAll('[data-testid="pay-donate-chip"]').length).toBe(4)
    expect(w.find('[data-testid="pay-title"]').text()).toContain('Lễ tùy tâm')
  })

  it('CẤM lộ đường giá: màn donate không chứa 29.000 / "Mở khóa" / pay-unlock-btn / pay-price (C4)', async () => {
    const { w } = await mountDonate()
    const t = w.text()
    expect(t).not.toMatch(/29[.,]?000/)
    expect(t).not.toMatch(/Mở khóa/i)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-price"]').exists()).toBe(false)
  })

  it('bấm Gửi lễ → #7 payload nguyên bản (kind=donate, amount, return_url, idempotency_key) → QR donate', async () => {
    const { w } = await mountDonate()
    await sendOffer(w)
    const arg = client.api.createPayment.mock.calls[0][0]
    expect(arg.kind).toBe('donate')
    expect(arg.amount_vnd).toBe(50000)
    expect(arg.idempotency_key.length).toBeGreaterThanOrEqual(8)
    expect(w.find('[data-testid="pay-donate-qr"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(false)
  })

  it('poll paid → pay-donate-thanks tại chỗ, KHÔNG đổi route, KHÔNG toast, không refresh entitlement', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
    const { r, w } = await mountDonate()
    await sendOffer(w)
    client.api.paymentStatus.mockResolvedValue({ data: { order_code: 384721, status: 'paid', kind: 'donate', amount_vnd: 50000, paid_at: 'x' } })
    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(true)
    expect(r.currentRoute.value.path).toBe('/tam-tu')
    vi.useRealTimers()
  })

  it('donate_open bắn ĐÚNG 1 lần khi mở màn #11 (event name giữ nguyên, topic null — route mới không có topic)', async () => {
    await mountDonate()
    const opens = client.api.track.mock.calls.filter((c) => c[0].name === 'donate_open')
    expect(opens.length).toBe(1)
    expect(opens[0][0].props).toEqual({ topic: null })
  })
})

// ============ (c) PaywallView THUẦN — 1 chế độ mở khóa giá ============
describe('PaywallView thuần (HOME-V4-B): /mo-khoa/* chỉ còn chế độ giá', () => {
  it('luôn hiện PRICE_LABEL + pay-unlock-btn, KHÔNG có nhánh donate (block/chip/input)', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(w.find('[data-testid="pay-price"]').text()).toBe(PRICE_LABEL)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-unlock-btn"]').text()).toContain(PRICE_LABEL)
    expect(w.find('[data-testid="pay-donate-block"]').exists()).toBe(false)
    expect(w.findAll('[data-testid="pay-donate-chip"]').length).toBe(0)
    expect(w.find('[data-testid="pay-donate-input"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-mode-donate"]').exists()).toBe(false)
  })

  it('unlock #7 payload không đổi (kind=unlock, topic, PRICE_UNLOCK_VND)', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'tai_loc' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    client.api.createPayment.mockResolvedValue({ data: { order_code: 1, kind: 'unlock', topic: 'tai_loc', amount_vnd: PRICE_UNLOCK_VND, status: 'pending', qr_data: 'vietqr/x', confirm_url: '', checkout_url: '/pay/1', stub: true } })
    await w.find('[data-testid="pay-unlock-btn"]').trigger('click')
    await flushPromises()
    const arg = client.api.createPayment.mock.calls[0][0]
    expect(arg.kind).toBe('unlock')
    expect(arg.topic).toBe('tai_loc')
    expect(arg.amount_vnd).toBe(PRICE_UNLOCK_VND)
  })

  it('freeDeep=true cũng KHÔNG đổi hình paywall — không đọc ?mode, không flag ẩn giá (Luật 2)', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(w.find('[data-testid="pay-price"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(true)
  })
})
