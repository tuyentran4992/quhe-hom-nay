// S4 Paywall FE-1 — đúng payload #7 stub BE-2: qr_data, confirm_url, stub:true → UI ghi
// chú "sắp mở / chưa thu tiền" (card FE-1: paywall stub). Nút "Lễ tùy tâm" HIỆN nhưng
// paid (#9) → refresh #1 → toast "Đã mở khóa" (stack ở App shell — assert qua composable)
// + về S3 trigger TopicGate.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PaywallView from '../src/views/PaywallView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { useToasts } from '../src/composables/useToasts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), createPayment: vi.fn(), paymentStatus: vi.fn(), track: vi.fn() } }
})

const ORDER = {
  order_code: 17880833415, kind: 'unlock', topic: 'duyen', amount_vnd: 29000, status: 'pending',
  qr_data: 'vietqr/action/qr/970436/stub17880833415/2900000/Qu+Hom+Nay',
  confirm_url: 'http://127.0.0.1:5380/api/payments/17880833415/simulate-paid',
  checkout_url: '/pay/17880833415', stub: true, expires_at: '2026-08-30T17:00:00Z',
}
const ME_LOCKED = { device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: null }
const ME_PAID = { ...ME_LOCKED, entitlements: ['duyen'], today_draw: { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 7, 7, 7, 7, 7], changing_lines: [], created_at: 't' } }

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: PaywallView },
    ],
  })
}

beforeEach(() => { vi.clearAllMocks(); _resetDeviceForTests(); client.api.me.mockResolvedValue(ME_LOCKED); client.api.track.mockResolvedValue(null) })
afterEach(() => vi.useRealTimers())

async function mountS4(topic = 'duyen') {
  const r = mk()
  const w = mount(PaywallView, { global: { plugins: [r] }, props: {}, slots: {} , attachTo: undefined })
  await r.push({ name: 'paywall', params: { topic } })
  await w.vm.$nextTick()
  await flushPromises()
  return { r, w }
}

describe('PaywallView — stub paywall từ #7 BE-2', () => {
  it('nút Mở khóa bắn #7 kind=unlock topic+amount 29000 + idempotency_key 8-64', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'tai_loc' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    client.api.createPayment.mockResolvedValue({ data: { ...ORDER, topic: 'tai_loc' } })
    await w.find('[data-testid="pay-unlock-btn"]').trigger('click')
    await flushPromises()
    expect(client.api.createPayment).toHaveBeenCalledTimes(1)
    const arg = client.api.createPayment.mock.calls[0][0]
    expect(arg.kind).toBe('unlock')
    expect(arg.topic).toBe('tai_loc')
    expect(arg.amount_vnd).toBe(29000)
    expect(typeof arg.idempotency_key).toBe('string')
    expect(arg.idempotency_key.length).toBeGreaterThanOrEqual(8)
  })

  it('#7 về stub:true → hiện QR + ghi chú "sắp mở" + KHÔNG thu tiền (thẻ stub, testid pay-stub-note)', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    client.api.createPayment.mockResolvedValue({ data: ORDER })
    await w.find('[data-testid="pay-unlock-btn"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="pay-qr"]').exists()).toBe(true)
    const note = w.find('[data-testid="pay-stub-note"]')
    expect(note.exists()).toBe(true)
    expect(note.text()).toMatch(/sắp mở/i)
    expect(w.find('[data-testid="pay-status"]').text()).toContain('Chờ thanh toán')
  })

  it('poll #9 → paid: refresh #1 thấy entitlement, toast "Đã mở khóa Tình duyên", điều hướng S3', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await vi.advanceTimersByTimeAsync(0)
    await flushPromises()
    client.api.createPayment.mockResolvedValue({ data: ORDER })
    await w.find('[data-testid="pay-unlock-btn"]').trigger('click')
    await vi.advanceTimersByTimeAsync(0)
    await flushPromises()
    client.api.paymentStatus.mockResolvedValue({ data: { order_code: ORDER.order_code, status: 'paid', kind: 'unlock', topic: 'duyen', amount_vnd: 29000, paid_at: '2026-08-30T03:00:00Z' } })
    client.api.today.mockResolvedValue({ data: { today_draw: ME_PAID.today_draw, entitlements: ['duyen'], server_date_vn: '2026-08-30' } })
    await vi.advanceTimersByTimeAsync(3000) // PAY_POLL_MS
    await flushPromises()
    expect(client.api.paymentStatus).toHaveBeenCalledWith(ORDER.order_code)
    expect(useToasts().list.value.some((t) => /Đã mở khóa/.test(t.text))).toBe(true)
    expect(r.currentRoute.value.path).toBe('/que/42')
    vi.useRealTimers()
  })

  it('Lễ tùy tâm HIỆN: chips 1000/2000/5000/50000 + input tay; bấm Gửi lễ → #7 kind=donate, KHÔNG topic', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(w.find('[data-testid="pay-donate-block"]').exists()).toBe(true)
    expect(w.findAll('[data-testid="pay-donate-chip"]').length).toBe(4)
    client.api.createPayment.mockResolvedValue({ data: { ...ORDER, kind: 'donate', topic: null, amount_vnd: 5000 } })
    await w.find('[data-testid="pay-donate-input"]').setValue('5000')
    await w.find('[data-testid="pay-donate-btn"]').trigger('click')
    await flushPromises()
    const arg = client.api.createPayment.mock.calls[0][0]
    expect(arg.kind).toBe('donate')
    expect(arg.amount_vnd).toBe(5000)
    expect(arg.topic === undefined || arg.topic === null || arg.topic === '').toBe(true)
    // [DEV-DONATE-QR] t_dc6112cf — hành vi ĐỔI có chủ đích (boss GO): gửi lễ → QR THẬT chờ
    // tiền về, KHÔNG nhảy thẳng "Cảm ơn" khi chưa paid. Test cũ chốt đúng cái bệnh đó.
    expect(w.find('[data-testid="pay-donate-qr"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(false)
  })

  it('donate ngoài khoảng C--07 (999đ) → chặn client-side, không gọi #7', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    await w.find('[data-testid="pay-donate-input"]').setValue('999')
    expect(w.find('[data-testid="pay-donate-btn"]').attributes('disabled')).toBeDefined()
    expect(client.api.createPayment).not.toHaveBeenCalled()
  })

  it('#7 lỗi NETWORK → pay-error, không trắng màn', async () => {
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    client.api.createPayment.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    await w.find('[data-testid="pay-unlock-btn"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="pay-error"]').exists()).toBe(true)
  })

  // ============ [MKT-F6-fix/FE] t_9bad794e — donate_open bắn #11 khi mở màn (§2.2) ============

  it('mở /mo-khoa/duyen → api.track gọi ĐÚNG 1 lần payload {name:donate_open, props:{topic}} (§3 #11)', async () => {
    client.api.track.mockResolvedValue(null)
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(client.api.track).toHaveBeenCalledTimes(1)
    const arg = client.api.track.mock.calls[0][0]
    expect(arg.name).toBe('donate_open')
    expect(arg.props).toEqual({ topic: 'duyen' })
  })

  it('topic khác vẫn bắn đúng props.topic (route param, không hardcode)', async () => {
    client.api.track.mockResolvedValue(null)
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'tai_loc' } })
    mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(client.api.track).toHaveBeenCalledTimes(1)
    expect(client.api.track.mock.calls[0][0].props).toEqual({ topic: 'tai_loc' })
  })

  it('track reject (422/NETWORK) → fire-and-forget: UI vẫn render bình thường, không toast/error', async () => {
    client.api.track.mockRejectedValue(new client.ApiError(422, 'VALIDATION_FAILED', 'x', {}))
    const r = mk()
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(client.api.track).toHaveBeenCalledTimes(1)
    expect(w.find('[data-testid="pay-unlock-btn"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-donate-block"]').exists()).toBe(true)
    expect(w.find('[data-testid="pay-error"]').exists()).toBe(false)
  })
})
