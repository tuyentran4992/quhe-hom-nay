// LUAN-RACE-FE (card t_debf4bbf, bệnh án /data/agents/supervisor/outbox/BUG-LUAN-RACE-20260902.md)
// Defect boss: đang luận sâu tab A, đổi sang tab B lúc job B running → poll/POST của job
// CŨ in-flight về sau khi đổi tab vẫn ghi phase/result → bài topic cũ chảy sang tab mới
// (hijack UI, "chỉ xem lại được luận sâu tình duyên").
// Fix chốt (FE-only, TopicGate.vue): generator `gen` — watch đổi (drawId, topic, unlocked)
// → gen++; mọi callback in-flight (poll api.aiJob + POST #5) snapshot myGen, về sau mà
// myGen !== gen → im lặng tuyệt đối (không phase, không result, không reschedule poll).
// TDD: 2 case ĐỎ viết TRƯỚC trên main 6ac8a8a — phải fail vì bug thật, xanh sau khi sửa.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import TopicGate from '../src/components/TopicGate.vue'
import * as client from '../src/api/client.js'
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

const JOB = { job_uuid: 'j-cu', status: 'queued' }
const DONE_CU = { job_uuid: 'j-cu', status: 'done', result: 'bài-topic-CU-lậu' }

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
  vi.useFakeTimers()
  _resetDeviceForTests()
  // KHÁCH đã unlock CẢ 2 tab — mô phỏng đúng repro bệnh án: đổi tab là hợp lệ,
  // lỗi nằm ở callback topic cũ về muộn chứ không ở quyền truy cập.
  client.api.me.mockResolvedValue({
    device_id: 'd', is_new_device: false, server_date_vn: '2026-09-02',
    entitlements: ['duyen', 'tai_loc'], today_draw: null,
  })
  // mặc định: tab mới chưa có bài lưu → probe trả exists=false (nhánh idle).
  client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
  await useDevice().load()
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
  vi.useRealTimers()
})

describe('LUAN-RACE A1 — poll in-flight bị loại khi gen đổi', () => {
  it('api.aiJob của topic CŨ resolve SAU khi đổi tab → tab mới KHÔNG bị đè phase/result, vòng poll cũ CHẾT hẳn', async () => {
    let resolveStaleJob
    client.api.requestInterpretation.mockResolvedValue({ data: JOB })
    client.api.aiJob.mockImplementation(
      () => new Promise((res) => { resolveStaleJob = res }),
    )
    const w = mountGate('duyen')
    await flushPromises()

    // Tab duyen: bấm "Xin luận sâu" → POST #5 về queued → hẹn poll 2s.
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-skeleton"]').exists()).toBe(true)

    // Poll đầu tiên NỔ ra và IN-FLIGHT (fetch treo — chính là cửa sổ race bệnh án).
    await vi.advanceTimersByTimeAsync(2_000)
    expect(client.api.aiJob).toHaveBeenCalledTimes(1)

    // User đổi tab Sang tai_loc KHI request cũ chưa về.
    await w.setProps({ topic: 'tai_loc' })
    await flushPromises()
    expect(w.find('[data-testid="gate-skeleton"]').exists()).toBe(false) // tab mới: idle sạch

    // Response của job CŨ về muộn — đây là phát đạn race.
    resolveStaleJob({ data: DONE_CU })
    await flushPromises()

    // tab mới PHẢI vẫn idle: không phase 'done', không bài cũ chảy sang, không reschedule.
    expect(w.text()).not.toContain('bài-topic-CU-lậu')
    expect(w.find('[data-testid="gate-result"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-skeleton"]').exists()).toBe(false)
    // vòng poll cũ chết hẳn: không có poll kế tiếp nào được lên lịch từ callback lậu.
    await vi.advanceTimersByTimeAsync(10_000)
    await flushPromises()
    expect(client.api.aiJob).toHaveBeenCalledTimes(1)
  })
})

describe('LUAN-RACE A1b — POST #5 về sau khi đổi tab → im lặng', () => {
  it('requestInterpretation resolve SAU khi user đã đổi tab → không set phase queued, không start poll', async () => {
    let resolvePost
    client.api.requestInterpretation.mockImplementation(
      () => new Promise((res) => { resolvePost = res }),
    )
    client.api.aiJob.mockResolvedValue({ data: DONE_CU })
    const w = mountGate('duyen')
    await flushPromises()

    // Bấm "Xin luận sâu" → submitting (sync, hợp lệ — branch trước await đầu).
    await w.find('[data-testid="gate-ask"]').trigger('click')
    expect(w.find('[data-testid="gate-submit-spinner"]').exists()).toBe(true)

    // POST chưa về, user đổi tab.
    await w.setProps({ topic: 'tai_loc' })
    await flushPromises()
    expect(w.find('[data-testid="gate-submit-spinner"]').exists()).toBe(false) // watch reset

    // POST của tab CŨ về muộn.
    resolvePost({ data: JOB })
    await flushPromises()
    await vi.advanceTimersByTimeAsync(5_000)
    await flushPromises()

    // Tab mới phải SẠCH: không skeleton_queued do callback lậu dựng, không poll job cũ.
    expect(w.find('[data-testid="gate-skeleton"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-result"]').exists()).toBe(false)
    expect(client.api.aiJob).not.toHaveBeenCalled()
  })

  it('POST #5 rejects (409 AI_ALREADY_DONE) SAU khi đổi tab → im lặng, KHÔNG re-probe/dè phase tab mới', async () => {
    // ApiError import động để giữ file gọn như các test hiện có.
    const { ApiError } = await import('../src/api/client.js')
    let rejectPost
    client.api.requestInterpretation.mockImplementation(
      () => new Promise((_res, rej) => { rejectPost = rej }),
    )
    const w = mountGate('duyen')
    await flushPromises()

    await w.find('[data-testid="gate-ask"]').trigger('click')
    await w.setProps({ topic: 'tai_loc' })
    await flushPromises()
    // probe của watch tab mới: đúng 1 lần tính tới đây.
    const probesBefore = client.api.savedInterpretation.mock.calls.length

    rejectPost(new ApiError(409, 'AI_ALREADY_DONE', 'done', {}))
    await flushPromises()
    await vi.advanceTimersByTimeAsync(2_000)
    await flushPromises()

    // callback lậu bị gen chặn TRƯỚC khi kịp gọi probeSaved → không probe lần 2,
    // tab mới giữ nguyên idle (không failed, không saved dỏm).
    expect(client.api.savedInterpretation).toHaveBeenCalledTimes(probesBefore)
    expect(w.find('[data-testid="gate-failed"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-ask"]').isDisabled()).toBe(false)
  })
})
