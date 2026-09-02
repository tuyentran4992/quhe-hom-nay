// [DEV-DONATE-QR] t_dc6112cf — bệnh: donate() vứt response #7, nhảy thẳng 'donated' khi
// khách CHƯA chuyển đồng nào. FIX: giữ order → màn QR thật (pay-donate-qr) → poll #9 →
// CHỈ paid mới 'donated' (pay-donate-thanks). donate: KHÔNG router.replace, KHÔNG toast,
// KHÔNG refresh entitlement. expired → "Gửi lễ lại" tạo đơn MỚI (idempotency key mới).
// Unlock 29k GIỮ NGUYÊN hành vi (các test cũ paywall.test.js là regression gate).
// Thẫm mỹ theo mockup DUYỆT: /data/agents/ux-ui/outbox/UX-MOCKUP-DONATE/ (SHOT1 thẻ lễ,
// SHOT2 badge vàng "Chưa phải đã gửi — chờ tiền về").
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PaywallView from '../src/views/PaywallView.vue'
import * as client from '../src/api/client.js'
import { parseVietQr, ckCode } from '../src/utils/donateQr.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { useToasts } from '../src/composables/useToasts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), createPayment: vi.fn(), paymentStatus: vi.fn(), track: vi.fn() } }
})

// đúng shape #7 donate từ PaymentController::qrPayload (main 67911a3)
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
    ],
  })
}

beforeEach(() => { vi.clearAllMocks(); _resetDeviceForTests(); client.api.me.mockResolvedValue(ME_FREE); client.api.track.mockResolvedValue(null) })
afterEach(() => { vi.useRealTimers() })

// donateMode chuẩn: freeDeep ON + ?mode=donate (C4)
async function mountDonate() {
  const r = mk()
  await r.push({ name: 'paywall', params: { topic: 'duyen' }, query: { mode: 'donate' } })
  await r.isReady()
  const w = mount(PaywallView, { global: { plugins: [r] } })
  await flushPromises(); await flushPromises()
  return { r, w }
}
async function sendOffer(w, value = '50000') {
  client.api.createPayment.mockResolvedValue({ data: DONATE_ORDER })
  await w.find('[data-testid="pay-donate-input"]').setValue(value)
  await w.find('[data-testid="pay-donate-btn"]').trigger('click')
  await flushPromises()
}

// ============ util: đọc nội dung CK từ qr_data stub (quá cảnh trước payOS) ============
describe('parseVietQr — chuỗi qr_data → thông tin chuyển khoản', () => {
  it('payload stub BE thật → bin/account/amount/content', () => {
    expect(parseVietQr(DONATE_ORDER.qr_data)).toEqual({
      bin: '970436', account: 'stub384721', amount: 50000, content: 'Qu+Hom+Nay',
    })
  })
  it('chuỗi lạ (payOS data thật khác format) → null để UI fallback', () => {
    expect(parseVietQr('https://pay.live/example')).toBe(null)
    expect(parseVietQr('')).toBe(null)
    expect(parseVietQr(null)).toBe(null)
  })
})

// ============ luồng chính: Gửi lễ → QR THẬT, KHÔNG "Cảm ơn" sớm ============
describe('donate → màn QR thật (pay-donate-qr)', () => {
  it('bấm Gửi lễ → #7 kind=donate, GIỮ response làm order → hiện pay-donate-qr với ĐÚNG số tiền + đơn #; KHÔNG phải pay-donate-thanks', async () => {
    const { w } = await mountDonate()
    await sendOffer(w)
    const arg = client.api.createPayment.mock.calls[0][0]
    expect(arg.kind).toBe('donate')
    expect(arg.amount_vnd).toBe(50000)
    expect(arg.idempotency_key.length).toBeGreaterThanOrEqual(8)
    const qr = w.find('[data-testid="pay-donate-qr"]')
    expect(qr.exists()).toBe(true)
    expect(qr.text()).toContain('50.000')
    expect(qr.text()).toContain('384721')
    expect(w.find('[data-testid="pay-qr"]').exists()).toBe(true) // reuse PayQr như unlock
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(false) // CHƯA paid → CẤM cảm ơn
  })

  it('QR donate: badge pay-status "Chưa phải đã gửi — chờ tiền về" + hint 5 phút + nút recheck + nội dung CK parse từ qr_data', async () => {
    const { w } = await mountDonate()
    await sendOffer(w)
    const badge = w.find('[data-testid="pay-status"]')
    expect(badge.text()).toMatch(/Chưa phải đã gửi/)
    expect(badge.text()).toMatch(/chờ tiền về/)
    expect(badge.text()).toMatch(/3 giây/)
    expect(w.find('[data-testid="pay-donate-timeout-hint"]').text()).toMatch(/5 phút/)
    expect(w.find('[data-testid="pay-donate-timeout-hint"]').text()).toMatch(/khích lệ tinh thần/)
    expect(w.find('[data-testid="pay-donate-recheck"]').exists()).toBe(true)
  })
})

// ============ SHOT1 form lễ — [SPEC-CHANGE boss 02/09] ============
describe('form lễ theo spec 02/09', () => {
  it('4 mức 10.000/20.000/50.000/100.000đ; mặc định 20.000đ on; aria-pressed phản ánh chọn', async () => {
    const { w } = await mountDonate()
    const chips = w.findAll('[data-testid="pay-donate-chip"]')
    expect(chips.length).toBe(4)
    const texts = chips.map((c) => c.text())
    expect(texts.join(' ')).toMatch(/10\.000/)
    expect(texts.join(' ')).toMatch(/20\.000/)
    expect(texts.join(' ')).toMatch(/50\.000/)
    expect(texts.join(' ')).toMatch(/100\.000/)
    expect(chips[1].attributes('aria-pressed')).toBe('true') // 20.000đ mặc định
    expect(chips[0].attributes('aria-pressed')).toBe('false')
    await chips[3].trigger('click')
    const after = w.findAll('[data-testid="pay-donate-chip"]')
    expect(after[3].attributes('aria-pressed')).toBe('true')
    expect(after[1].attributes('aria-pressed')).toBe('false')
  })
  it('nhập số khác → bỏ chọn chip hết (aria-pressed false), nút sáng nếu hợp lệ', async () => {
    const { w } = await mountDonate()
    await w.find('[data-testid="pay-donate-input"]').setValue('120000')
    w.findAll('[data-testid="pay-donate-chip"]').forEach((c) => expect(c.attributes('aria-pressed')).toBe('false'))
    expect(w.find('[data-testid="pay-donate-btn"]').attributes('disabled')).toBeUndefined()
  })
  it('sàn nhập tay 5.000đ (boss 02/09): 4.999 → disabled, không gọi #7; 5.000 → hợp lệ', async () => {
    const { w } = await mountDonate()
    await w.find('[data-testid="pay-donate-input"]').setValue('4999')
    expect(w.find('[data-testid="pay-donate-btn"]').attributes('disabled')).toBeDefined()
    expect(client.api.createPayment).not.toHaveBeenCalled()
    await w.find('[data-testid="pay-donate-input"]').setValue('5000')
    expect(w.find('[data-testid="pay-donate-btn"]').attributes('disabled')).toBeUndefined()
    expect(w.find('[data-testid="pay-donate-input"]').attributes('min')).toBe('5000')
  })
})

// ============ [SPEC-CHANGE boss 02/09] CK = QH<đơn>, CẤM tên app trên màn ============
describe('nội dung CK trung tính QH<đơn>', () => {
  it('ckCode(384721) = QH384721', () => {
    expect(ckCode(384721)).toBe('QH384721')
    expect(ckCode('99')).toBe('QH99')
  })
  it('màn QR donate hiện "QH384721" — và TOÀN MÀN không chứa Qu+Hom+Nay / Quẻ Hôm Nay (qr_data thô CẤM in ra)', async () => {
    const { w } = await mountDonate()
    await sendOffer(w)
    const ck = w.find('[data-testid="pay-donate-ck"]')
    expect(ck.text()).toContain('QH384721')
    expect(ck.text()).toContain('Vietcombank') // bank vẫn từ BIN stub
    const t = w.text()
    expect(t).not.toMatch(/Qu\+Hom\+Nay|Quẻ Hôm Nay|Quhe Hom Nay/i)
  })
  it('qr_data format lạ (payOS) → vẫn hiện QH<đơn>, không in chuỗi thô, không bịa bank', async () => {
    client.api.createPayment.mockResolvedValue({ data: { ...DONATE_ORDER, qr_data: 'payos://opaque/blob-9f2' } })
    const { w } = await mountDonate()
    await w.find('[data-testid="pay-donate-input"]').setValue('50000')
    await w.find('[data-testid="pay-donate-btn"]').trigger('click')
    await flushPromises()
    const ck = w.find('[data-testid="pay-donate-ck"]')
    expect(ck.text()).toContain('QH384721')
    expect(ck.text()).not.toMatch(/payos:\/\//)
    expect(ck.text()).not.toMatch(/Vietcombank/) // BIN lạ → không khẳng định bank
  })
})

// ============ poll #9: paid MỚi được "Cảm ơn" ============
describe('donate poll — chỉ paid → donated', () => {
  it('poll trả paid → pay-donate-thanks hiện; KHÔNG đổi route, KHÔNG toast, KHÔNG gọi #10 refresh', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
    const { r, w } = await mountDonate()
    await sendOffer(w)
    client.api.paymentStatus.mockResolvedValue({ data: { order_code: 384721, status: 'paid', kind: 'donate', topic: null, amount_vnd: 50000, paid_at: 'x' } })
    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()
    expect(client.api.paymentStatus).toHaveBeenCalledWith(384721)
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(true)
    expect(w.text()).toMatch(/Cảm ơn/)
    expect(w.text()).toMatch(/khích lệ tinh thần/) // wording chốt mockup, không hứa nội dung
    expect(r.currentRoute.value.query.mode).toBe('donate') // ở lại màn, KHÔNG router.replace
    expect(useToasts().list.value.length).toBe(0)
    expect(client.api.today).not.toHaveBeenCalled() // donate không đụng entitlement
    vi.useRealTimers()
  })

  it('pending suốt 5 phút → expired: KHÔNG BAO GIỜ pay-donate-thanks; hiện "Gửi lễ lại"; bấm → đơn MỚI idempotency key MỚI', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
    const { w } = await mountDonate()
    await sendOffer(w)
    client.api.paymentStatus.mockResolvedValue({ data: { order_code: 384721, status: 'pending' } })
    await vi.advanceTimersByTimeAsync(303000) // vượt PAY_POLL_TIMEOUT_MS=300000
    await flushPromises()
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-donate-qr"]').text()).toMatch(/hết hạn/i)
    const retry = w.find('[data-testid="pay-donate-retry"]')
    expect(retry.exists()).toBe(true)
    await retry.trigger('click')
    await flushPromises()
    expect(client.api.createPayment).toHaveBeenCalledTimes(2)
    const k1 = client.api.createPayment.mock.calls[0][0].idempotency_key
    const k2 = client.api.createPayment.mock.calls[1][0].idempotency_key
    expect(k2).not.toBe(k1)
    expect(w.find('[data-testid="pay-donate-qr"]').exists()).toBe(true)
    vi.useRealTimers()
  })

  it('BE trả status expired sớm → về đúng màn expired donate, không cảm ơn', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
    const { w } = await mountDonate()
    await sendOffer(w)
    client.api.paymentStatus.mockResolvedValue({ data: { order_code: 384721, status: 'expired' } })
    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()
    expect(w.find('[data-testid="pay-donate-thanks"]').exists()).toBe(false)
    expect(w.find('[data-testid="pay-donate-retry"]').exists()).toBe(true)
    vi.useRealTimers()
  })
})
