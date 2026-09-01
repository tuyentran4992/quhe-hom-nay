// LUAN-V2 FE (card t_b13fd2b9, SPEC-LUAN-V2 §7 + D3/D4) — ô "Bạn đang vướng chuyện gì?"
// + 3 chip gợi ý text + đẩy question vào API.
// Chốt kỷ luật: chip KHÔNG đổi topic API (topic vẫn theo tab → props); question rỗng sau
// trim → KHÔNG gửi key `question` (giữ nguyên nhánh cache cũ phía BE — CEO comment card).
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import TopicGate from '../src/components/TopicGate.vue'
import * as client from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { QUESTION_MAX, QUESTION_SUGGESTIONS } from '../src/constants.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: { me: vi.fn(), today: vi.fn(), requestInterpretation: vi.fn(), aiJob: vi.fn() },
  }
})

const JOB = { job_uuid: 'j-1', status: 'queued' }

let wrapper = null
function mountGate(topic = 'duyen') {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
    ],
  })
  wrapper = mount(TopicGate, {
    props: { drawId: 42, topic },
    global: { plugins: [r] },
  })
  return wrapper
}

beforeEach(async () => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  client.api.me.mockResolvedValue({
    device_id: 'd', is_new_device: false, server_date_vn: '2026-09-01',
    entitlements: ['duyen', 'tai_loc', 'xuat_hanh'], today_draw: null,
  })
  client.api.requestInterpretation.mockResolvedValue({ data: JOB })
  await useDevice().load()
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
})

describe('LUAN-V2 §7.1 — ô question hiển thị trước nút "Xin luận sâu"', () => {
  it('phase idle → có textarea gate-question, placeholder đúng, maxlength 200', async () => {
    const w = mountGate()
    await flushPromises()
    const ta = w.find('[data-testid="gate-question"]')
    expect(ta.exists()).toBe(true)
    expect(ta.element.tagName).toBe('TEXTAREA')
    expect(ta.attributes('placeholder')).toContain('Bạn đang vướng chuyện gì?')
    expect(ta.attributes('placeholder')).toContain('không bắt buộc')
    expect(Number(ta.attributes('maxlength'))).toBe(200)
    expect(QUESTION_MAX).toBe(200)
    // ô phải ĐỨNG TRƯỚC nút Xin luận sâu trong DOM
    const html = w.html()
    expect(html.indexOf('gate-question')).toBeLessThan(html.indexOf('gate-ask'))
  })

  it('counter: 0/200 luc đầu → theo số ký tự thật khi gõ (unicode-safe)', async () => {
    const w = mountGate()
    await flushPromises()
    const counter = w.find('[data-testid="gate-question-counter"]')
    expect(counter.exists()).toBe(true)
    expect(counter.text()).toBe('0/200')
    await w.find('[data-testid="gate-question"]').setValue('chuyện tình cảm')
    expect(counter.text()).toBe('15/200')
  })
})

describe('LUAN-V2 §7.2 + D3 — 3 chip gợi ý text, KHÔNG đổi topic API', () => {
  it('mỗi topic có đúng 3 gói gợi ý trong constants', () => {
    for (const t of ['duyen', 'tai_loc', 'xuat_hanh']) {
      expect(Array.isArray(QUESTION_SUGGESTIONS[t])).toBe(true)
      expect(QUESTION_SUGGESTIONS[t]).toHaveLength(3)
      QUESTION_SUGGESTIONS[t].forEach((s) => expect(s.trim().length).toBeGreaterThan(0))
    }
    // gợi ý duyen theo chốt của anh Tuyền trong card
    expect(QUESTION_SUGGESTIONS.duyen.join(' ')).toContain('chuyện tình cảm của em')
  })

  it('bấm chip CHỈ điền text vào ô — không gọi API, topic payload vẫn theo tab', async () => {
    const w = mountGate('tai_loc')
    await flushPromises()
    const chips = w.findAll('[data-testid="gate-question-chip"]')
    expect(chips).toHaveLength(3)
    await chips[0].trigger('click')
    expect(client.api.requestInterpretation).not.toHaveBeenCalled()
    await w.find('[data-testid="gate-question"]').setValue('') // clear để test setValue theo chip
    await chips[1].trigger('click')
    expect(w.find('[data-testid="gate-question"]').element.value).toBe(QUESTION_SUGGESTIONS.tai_loc[1])
    // bấm nút → payload topic vẫn 'tai_loc' (không đổi enum theo chip — D3)
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    const payload = client.api.requestInterpretation.mock.calls[0][0]
    expect(payload.topic).toBe('tai_loc')
    expect(payload.question).toBe(QUESTION_SUGGESTIONS.tai_loc[1])
  })
})

describe('LUAN-V2 §7.3 + D4 — gửi question qua requestInterpretation', () => {
  it('rỗng hoặc whitespace-only sau trim → KHÔNG gửi key question trong payload', async () => {
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-question"]').setValue('   \n ')
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    const payload = client.api.requestInterpretation.mock.calls[0][0]
    // undefined = client.js không dựng key vào body JSON (assert body thật ở apiclient.test.js)
    expect(payload.question).toBeUndefined()
    expect(payload.draw_id).toBe(42)
    expect(payload.topic).toBe('duyen')
  })

  it('có text → gửi nguyên văn ĐÃ TRIM (khớp hash normalize phía BE §4.1)', async () => {
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-question"]').setValue('  bao giờ em có người  ')
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    const payload = client.api.requestInterpretation.mock.calls[0][0]
    expect(payload.question).toBe('bao giờ em có người')
    // key idempotency vẫn sinh mới mỗi lần bấm (giữ nguyên hành vi cũ)
    expect(typeof payload.idempotency_key).toBe('string')
  })

  it('retry (gate-retry) vẫn mang theo question đã nhập — không mất nội dung', async () => {
    client.api.requestInterpretation.mockRejectedValueOnce({ code: 'SERVER', status: 500 })
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-question"]').setValue('em có nên đổi việc')
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-failed"]').exists()).toBe(true)
    await w.find('[data-testid="gate-retry"]').trigger('click')
    await flushPromises()
    const payload = client.api.requestInterpretation.mock.calls[1][0]
    expect(payload.question).toBe('em có nên đổi việc')
  })
})
