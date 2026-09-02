// CFG-FE (t_130d6f4b) — constants.js = MỘT surface nghiệp vụ duy nhất của FE.
// Chốt 3 điều:
//  1) A1: không còn `= <số 3 chữ số>` trong src/views + src/components (grep máy).
//  2) PRICE_LABEL SUY RA từ PRICE_UNLOCK_VND — đổi giá ở constants là UI đổi theo
//     (chống drift "đổi số này quên nhãn kia").
//  3) Các binding số trong component (QR size, donate mặc định, auto-push S3,
//     toast TTL) đọc thẳng từ constants — không phải literal.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PaywallView from '../src/views/PaywallView.vue'
import DonateView from '../src/views/DonateView.vue' // [HOME-V4-B] t_3647e25e
import TopicGate from '../src/components/TopicGate.vue'
import PayQr from '../src/components/PayQr.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import {
  PRICE_UNLOCK_VND, PRICE_LABEL, DONATE_TIERS, DONATE_DEFAULT_VND,
  QR_SIZE_PX, AUTO_PUSH_S3_MS, TOAST_TTL_MS,
} from '../src/constants.js'
import { useToasts } from '../src/composables/useToasts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: { me: vi.fn(), today: vi.fn(), createPayment: vi.fn(), paymentStatus: vi.fn(), track: vi.fn(), requestInterpretation: vi.fn(), interpretStatus: vi.fn(), haoTexts: vi.fn() },
  }
})

function walk(dir) {
  return readdirSync(dir).flatMap((e) => {
    const p = join(dir, e)
    if (statSync(p).isDirectory()) return walk(p)
    return /\.(vue|js)$/.test(e) && !/\.(test|spec)\.js$/.test(e) ? [p] : []
  })
}

describe('CFG-FE A1 — hết số kinh 8 gán thẳng trong views/components', () => {
  it('grep `= *[0-9]{3,}` = 0 kết quả trên src/views + src/components', () => {
    const hits = []
    for (const f of [...walk('src/views'), ...walk('src/components')]) {
      readFileSync(f, 'utf8').split('\n').forEach((line, i) => {
        if (/= *[0-9]{3,}/.test(line)) hits.push(`${f}:${i + 1}: ${line.trim()}`)
      })
    }
    expect(hits).toEqual([])
  })
})

describe('CFG-FE — một surface, đổi một chỗ là đổi hết', () => {
  it('PRICE_LABEL suy ra từ PRICE_UNLOCK_VND (kiểu VN, hậu tố đ)', () => {
    expect(PRICE_LABEL).toBe(`${PRICE_UNLOCK_VND.toLocaleString('vi-VN')}đ`)
  })
  it('DONATE_DEFAULT_VND đúng bằng mức 2 trong DONATE_TIERS (10/20/50/100k — boss 02/09)', () => {
    expect(DONATE_DEFAULT_VND).toBe(DONATE_TIERS[1].amount)
  })
})

describe('CFG-FE — binding UI đọc từ constants', () => {
  const ME_LOCKED = { device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: null }

  beforeEach(() => {
    vi.clearAllMocks()
    _resetDeviceForTests()
    client.api.me.mockResolvedValue(ME_LOCKED)
    client.api.track.mockResolvedValue(null)
  })
  afterEach(() => vi.useRealTimers())

  it('Paywall: nhãn giá = PRICE_LABEL, nút unlock chứa PRICE_LABEL, payload #7 = PRICE_UNLOCK_VND; donate ở /tam-tu mặc định DONATE_DEFAULT_VND', async () => {
    const r = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: { template: '<div/>' } },
        { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
        { path: '/mo-khoa/:topic', name: 'paywall', component: PaywallView },
        { path: '/tam-tu', name: 'donate', component: DonateView }, // [HOME-V4-B] t_3647e25e
      ],
    })
    await r.push({ name: 'paywall', params: { topic: 'duyen' } })
    const w = mount(PaywallView, { global: { plugins: [r] } })
    await flushPromises()
    expect(w.find('[data-testid="pay-price"]').text()).toBe(PRICE_LABEL)
    expect(w.find('[data-testid="pay-unlock-btn"]').text()).toContain(PRICE_LABEL)
    // [HOME-V4-B] Luật 2: /mo-khoa THUẦN giá — không còn block donate trên màn này
    expect(w.findAll('[data-testid="pay-donate-chip"]').length).toBe(0)
    client.api.createPayment.mockResolvedValue({ data: { order_code: 1, kind: 'unlock', topic: 'duyen', amount_vnd: PRICE_UNLOCK_VND, status: 'pending', qr_data: 'vietqr/x', confirm_url: '', checkout_url: '/pay/1', stub: true } })
    await w.find('[data-testid="pay-unlock-btn"]').trigger('click')
    await flushPromises()
    expect(client.api.createPayment.mock.calls[0][0].amount_vnd).toBe(PRICE_UNLOCK_VND)
    // donate mặc định = DONATE_DEFAULT_VND, giờ kiểm ở màn riêng /tam-tu
    vi.clearAllMocks()
    await r.push('/tam-tu')
    const wd = mount(DonateView, { global: { plugins: [r] } })
    await flushPromises()
    const chips = wd.findAll('[data-testid="pay-donate-chip"]')
    expect(chips[1].attributes('aria-pressed')).toBe('true') // mức 2 = DONATE_DEFAULT_VND
    wd.unmount()
  })

  it('TopicGate nhánh locked: wording giá đọc PRICE_LABEL (không literal 29k)', async () => {
    const r = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: { template: '<div/>' } },
        { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
      ],
    })
    const w = mount(TopicGate, { props: { drawId: 1, topic: 'duyen' }, global: { plugins: [r] } })
    await flushPromises()
    const locked = w.find('[data-testid="gate-locked"]')
    expect(locked.exists()).toBe(true)
    expect(locked.text()).toContain(`mở khóa ${PRICE_LABEL}`)
    expect(locked.find('button').text()).toContain(PRICE_LABEL)
  })

  it('PayQr render ảnh QR đúng cạnh QR_SIZE_PX (attr width/height)', () => {
    const w = mount(PayQr, { props: { qrData: '', confirmUrl: '', amountLabel: '' } })
    const img = w.find('[data-testid="pay-qr"] img')
    expect(img.attributes('width')).toBe(String(QR_SIZE_PX))
    expect(img.attributes('height')).toBe(String(QR_SIZE_PX))
  })

  it('useToasts mặc định hẹn giờ biến mất bằng TOAST_TTL_MS', () => {
    vi.useFakeTimers()
    const t = useToasts()
    t.push('demo')
    expect(t.list.value.length).toBe(1)
    vi.advanceTimersByTime(TOAST_TTL_MS)
    expect(t.list.value.length).toBe(0)
  })

  it('DrawView auto-push S3 đọc AUTO_PUSH_S3_MS từ constants (04-ui §S3 B3)', () => {
    const src = readFileSync('src/views/DrawView.vue', 'utf8')
    expect(src).toContain('AUTO_PUSH_S3_MS')
    expect(AUTO_PUSH_S3_MS).toBeGreaterThan(0)
  })
})
