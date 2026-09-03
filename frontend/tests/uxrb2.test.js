// UXR-B2 (card t_74c8d220) — TopicGate hết quota: 2 exit quiet chống ngõ cụt.
// Wording = /data/agents/copywriter-vn/outbox/t_UXR-W/wording.md mục 6 BẢN CHỐT
// (nguyên văn, QA chấm từng chuỗi). Đặt DƯỚI 2 dòng nghi thức Q4 (t_QUOTA-Q1 PA1).
// Bất biến: chỉ thêm đường dẫn — không đổi hành vi ẩn nút Q4, không CTA "mở khóa",
// không nag, không đụng generator LUAN-RACE hay vòng poll.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import TopicGate from '../src/components/TopicGate.vue'
import * as client from '../src/api/client.js'
import { ApiError } from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      me: vi.fn(), today: vi.fn(),
      requestInterpretation: vi.fn(), aiJob: vi.fn(),
      savedInterpretation: vi.fn(),
    },
  }
})

const QUOTA_429 = () => new ApiError(429, 'quota_exceeded', 'Hết lượt.', {
  max_deep_reads_per_draw: 3, used: 3, remaining: 0,
})
// Nguyên văn UXR-W mục 6 (BẢN CHỐT, không alternative):
const EXIT1 = 'Xem thẻ quẻ của hôm nay →'
const EXIT2 = 'Về Sổ quẻ'
// 2 dòng Q4 phía trên PHẢI còn nguyên (bất biến card):
const NOTE_PA1 = 'Quẻ này đã đủ 3 lời. Ngẫm kỹ bài đã luận — ngày mai gieo quẻ mới.'
const HINT_PA1 = 'Bài đã luận vẫn còn nguyên với quẻ này — lúc nào nhớ, mở lại quẻ là đọc được.'

let wrapper = null
function mountGate(props = {}) {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
      { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
    ],
  })
  wrapper = mount(TopicGate, {
    props: { drawId: 42, topic: 'duyen', ...props },
    global: { plugins: [r] },
  })
  return wrapper
}

beforeEach(async () => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  client.api.me.mockResolvedValue({
    device_id: 'd', is_new_device: false, server_date_vn: '2026-09-03',
    entitlements: ['duyen', 'tai_loc'], today_draw: null,
    remaining_deep_reads: 3,
  })
  client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
  await useDevice().load()
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
})

describe('UXR-B2 — phase quota: 2 exit quiet dưới nghi thức Q4', () => {
  it('x=0 lúc mount → gate-quota-share href đúng /share-card?draw={id}, chữ nguyên văn', async () => {
    const w = mountGate({ remaining: 0, maxDeepReads: 3 })
    await flushPromises()
    const share = w.find('[data-testid="gate-quota-share"]')
    expect(share.exists()).toBe(true)
    expect(share.text()).toBe(EXIT1)
    expect(share.attributes('href')).toBe('/share-card?draw=42')
  })
  it('x=0 lúc mount → gate-quota-library href /cua-ban, chữ nguyên văn', async () => {
    const w = mountGate({ remaining: 0, maxDeepReads: 3 })
    await flushPromises()
    const lib = w.find('[data-testid="gate-quota-library"]')
    expect(lib.exists()).toBe(true)
    expect(lib.text()).toBe(EXIT2)
    expect(lib.attributes('href')).toBe('/cua-ban')
  })
  it('429 quota_exceeded lúc bấm → cả 2 exit hiện, KHÔNG còn nút hỏi (Q4 giữ nguyên)', async () => {
    client.api.requestInterpretation.mockRejectedValue(QUOTA_429())
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-quota-share"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-quota-library"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-question"]').exists()).toBe(false)
  })
  it('exit KHÔNG phá nghi thức Q4: note+hint PA1 còn nguyên, thứ tự exit nằm dưới hint', async () => {
    const w = mountGate({ remaining: 0, maxDeepReads: 3 })
    await flushPromises()
    expect(w.find('[data-testid="gate-quota-note"]').text()).toBe(NOTE_PA1)
    expect(w.find('[data-testid="gate-quota-hint"]').text()).toBe(HINT_PA1)
    const html = w.find('[data-testid="gate-quota"]').html()
    expect(html.indexOf('gate-quota-hint')).toBeLessThan(html.indexOf('gate-quota-share'))
  })
  it('exit 1 gắn theo drawId động (prop đổi → href đổi)', async () => {
    const w = mountGate({ remaining: 0 })
    await flushPromises()
    await w.setProps({ drawId: 77 })
    await flushPromises()
    expect(w.find('[data-testid="gate-quota-share"]').attributes('href')).toBe('/share-card?draw=77')
  })
  it('CHƯA hết quota (x≥1) → 2 exit KHÔNG hiện (đúng phase idle)', async () => {
    const w = mountGate({ remaining: 2 })
    await flushPromises()
    expect(w.find('[data-testid="gate-quota-share"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-quota-library"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
  })
  it('không có chuỗi cấm: "mở khóa"/giá ở nhánh quota (chống paywall ngầm)', async () => {
    const w = mountGate({ remaining: 0, maxDeepReads: 3 })
    await flushPromises()
    const t = w.find('[data-testid="gate-quota"]').text()
    expect(t).not.toMatch(/mở khóa|解锁|29\.000|nâng cấp/i)
  })
})
