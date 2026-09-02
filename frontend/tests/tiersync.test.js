// [FE-TIER-SYNC] t_ea138b84 — DONATE_TIERS phải KHỚP 1-1 mockup V2 đã duyệt boss 02/09
// (/data/agents/ux-ui/outbox/UX-MOCKUP-DONATE/shot2-form-mobile.png + mockup-form.html):
//   10k 十 "tâm ý khởi đầu" · 20k 廿 "lòng thành" · 50k 五十 "trọn lễ" · 100k 百 "lễ lớn".
//   Chip Hán ở GÓC TRÊN-TRÁI thẻ (mockup .han-tag{left:8px;top:6px}), badge ✓ đỏ góc phải,
//   mặc định chọn 20k. Don't-flow CẤM lộ token: tên app ('Qu+Hom+Nay'/'Quẻ Hôm Nay'),
//   giá '29.000', wording 'mở khóa' (C4). DONATE_OPTIONS/MIN/MAX không đổi (đã đúng).
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PaywallView from '../src/views/PaywallView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { DONATE_TIERS, DONATE_OPTIONS, DONATE_MIN, DONATE_MAX } from '../src/constants.js'
import { readFileSync } from 'node:fs'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), createPayment: vi.fn(), paymentStatus: vi.fn(), track: vi.fn() } }
})

const DONATE_ORDER = {
  order_code: 384721, kind: 'donate', topic: null, amount_vnd: 20000, status: 'pending',
  qr_data: 'vietqr/action/qr/970436/stub384721/20000/Qu+Hom+Nay',
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

async function mountDonate() {
  const r = mk()
  await r.push({ name: 'paywall', params: { topic: 'duyen' }, query: { mode: 'donate' } })
  await r.isReady()
  const w = mount(PaywallView, { global: { plugins: [r] } })
  await flushPromises(); await flushPromises()
  return { r, w }
}

describe('DONATE_TIERS — constants khớp mockup V2 (card t_ea138b84)', () => {
  it('4 tier đúng thứ tự, ĐỦ han + note theo shot2 đã duyệt', () => {
    expect(DONATE_TIERS.map((t) => t.amount)).toEqual([10000, 20000, 50000, 100000])
    expect(DONATE_TIERS.map((t) => t.han)).toEqual(['十', '廿', '五十', '百'])
    expect(DONATE_TIERS.map((t) => t.note)).toEqual(['tâm ý khởi đầu', 'lòng thành', 'trọn lễ', 'lễ lớn'])
    DONATE_TIERS.forEach((t) => { expect(t.han).toBeTruthy(); expect(t.note).toBeTruthy() })
  })
  it('KHÔNG đổi DONATE_OPTIONS/MIN/MAX (card: đã đúng, cấm đụng)', () => {
    expect(DONATE_OPTIONS).toEqual([10000, 20000, 50000, 100000])
    expect(DONATE_MIN).toBe(5000)
    expect(DONATE_MAX).toBe(500000)
  })
  it('gate comment "tạm để trống" phải hết (boss đã duyệt mắt 02/09)', () => {
    const src = readFileSync('src/constants.js', 'utf8')
    expect(src).not.toMatch(/tạm để trống/)
    expect(src).toMatch(/MOCKUP-DONATE-V2/)
  })
})

describe('PaywallView donate form — render theo mockup V2', () => {
  it('mỗi chip: Hán tag GÓC TRÊN-TRÁI + số tiền + note, đúng cặp theo thứ tự', async () => {
    const { w } = await mountDonate()
    const chips = w.findAll('[data-testid="pay-donate-chip"]')
    expect(chips.length).toBe(4)
    const want = [
      { han: '十', amt: '10.000', note: 'tâm ý khởi đầu' },
      { han: '廿', amt: '20.000', note: 'lòng thành' },
      { han: '五十', amt: '50.000', note: 'trọn lễ' },
      { han: '百', amt: '100.000', note: 'lễ lớn' },
    ]
    want.forEach((t, i) => {
      const chip = chips[i]
      const tag = chip.find('.han-tag')
      expect(tag.exists()).toBe(true)
      expect(tag.text()).toBe(t.han)
      // absolute + góc trên-trái theo mockup .han-tag{left:8px;top:6px}
      expect(tag.classes().join(' ')).toMatch(/absolute/)
      expect(tag.classes().join(' ')).toMatch(/left-/)
      expect(tag.classes().join(' ')).toMatch(/top-/)
      expect(chip.text()).toContain(t.amt)
      expect(chip.text()).toContain(t.note)
    })
  })
  it('mặc định chip 20k on: aria-pressed true + ✓ badge + son viền', async () => {
    const { w } = await mountDonate()
    const chips = w.findAll('[data-testid="pay-donate-chip"]')
    expect(chips[1].attributes('aria-pressed')).toBe('true')
    expect(chips[0].attributes('aria-pressed')).toBe('false')
    expect(chips[1].find('.ck').exists()).toBe(true) // badge ✓ mockup
    expect(chips[1].classes().join(' ')).toMatch(/border-cinnabar/)
    expect(chips[0].find('.ck').exists()).toBe(false)
  })
  it('bấm chip 50k → Hán 五十 vẫn hiện góc thẻ, chọn chuyển sang 50k', async () => {
    const { w } = await mountDonate()
    await w.findAll('[data-testid="pay-donate-chip"]')[2].trigger('click')
    const chips = w.findAll('[data-testid="pay-donate-chip"]')
    expect(chips[2].attributes('aria-pressed')).toBe('true')
    expect(chips[2].find('.han-tag').text()).toBe('五十')
    expect(chips[2].find('.ck').exists()).toBe(true)
  })
})

describe('Don\'t-flow zero token cấm (C4 + SPEC-CHANGE 02/09)', () => {
  const FORBIDDEN = ['Qu+Hom+Nay', 'Quẻ Hôm Nay', '29.000', 'Mở khóa', 'mở khóa']
  // Chốt VISIBLE TEXT (w.text()) — không soi w.html(): comment nguồn trong template có
  // nhắc chính cái token bị cấm (hướng dẫn CẤM), không phải nội dung user nhìn thấy.
  it('màn form donate: không lộ tên app / giá unlock / wording mở khóa', async () => {
    const { w } = await mountDonate()
    const txt = w.text()
    FORBIDDEN.forEach((s) => expect(txt.includes(s), `form lộ "${s}"`).toBe(false))
  })
  it('màn QR donate: không lộ tên app / giá unlock / wording mở khóa', async () => {
    const { w } = await mountDonate()
    client.api.createPayment.mockResolvedValue({ data: DONATE_ORDER })
    client.api.paymentStatus.mockResolvedValue({ data: { status: 'pending' } })
    await w.find('[data-testid="pay-donate-btn"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="pay-donate-qr"]').exists()).toBe(true)
    const txt = w.text()
    FORBIDDEN.forEach((s) => expect(txt.includes(s), `qr lộ "${s}"`).toBe(false))
    expect(txt).toContain('QH384721') // nội dung CK trung tính phải có thật
  })
})
