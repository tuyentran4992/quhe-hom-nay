// TopicGate E5 (card t_0285ac01) — cooldown 429 phải TỰ về 'idle' khi đồng hồ chạm 0:
// nút "Xin luận sâu" enable lại + bấm mở job mới (key idempotency mới). Defect QA: hết 90s
// nút vẫn disabled vĩnh viễn, nhãn "— 00:00", không còn đường nào gọi askFresh.
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
    api: { me: vi.fn(), today: vi.fn(), requestInterpretation: vi.fn(), aiJob: vi.fn() },
  }
})

const JOB = { job_uuid: 'j-1', status: 'queued' }
const DONE = { job_uuid: 'j-1', status: 'done', result: 'luận-A' }

let wrapper = null
function mountGate() {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
    ],
  })
  wrapper = mount(TopicGate, {
    props: { drawId: 42, topic: 'duyen' },
    global: { plugins: [r] },
  })
  return wrapper
}

beforeEach(async () => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  // device ĐÃ trả tiền unlock 'duyen' (E5: người dùng đã mua mới bị kẹt) — load XONG
  // trước mount để unlocked=true ngay tại mount, test kiểm soát 100% số lần bấm.
  client.api.me.mockResolvedValue({
    device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30',
    entitlements: ['duyen'], today_draw: null,
  })
  await useDevice().load()
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
  vi.useRealTimers()
})

describe('TopicGate cooldown E5 — hết đồng hồ phải mở khoá lại nút', () => {
  it('429 AI_COOLDOWN → gate-cooldown + nút disabled đếm mm:ss', async () => {
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
    )
    const w = mountGate()
    await flushPromises()
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    const cd = w.find('[data-testid="gate-cooldown"]')
    expect(cd.exists()).toBe(true)
    expect(w.find('[data-testid="gate-ask"]').isDisabled()).toBe(true)
    expect(cd.text()).toContain('01:30')
  })

  it('đồng hồ về 0 → phase idle: gate-cooldown biến mất, nút gate-ask enabled (reset nhãn, không "00:00")', async () => {
    vi.useFakeTimers()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
    )
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(10)
    await flushPromises()
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(true)

    await vi.advanceTimersByTimeAsync(90_000)
    await flushPromises()
    // defect cũ: ở lại cooldown vĩnh viễn với nhãn "— 00:00"
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(false)
    const btn = w.find('[data-testid="gate-ask"]')
    expect(btn.exists()).toBe(true)
    expect(btn.isDisabled()).toBe(false)
    expect(btn.text()).not.toContain('00:00')
  })

  it('sau khi cooldown tự hết → bấm nút gọi requestInterpretation LẦN 2 với idempotency key MỚI', async () => {
    vi.useFakeTimers()
    client.api.requestInterpretation
      .mockRejectedValueOnce(
        new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
      )
      .mockResolvedValue({ data: JOB })
    client.api.aiJob.mockResolvedValue({ data: DONE })
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(10)
    await vi.advanceTimersByTimeAsync(90_000)
    await flushPromises()

    expect(w.find('[data-testid="gate-ask"]').isDisabled()).toBe(false)
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(10)
    await flushPromises()
    expect(client.api.requestInterpretation).toHaveBeenCalledTimes(2)
    const k1 = client.api.requestInterpretation.mock.calls[0][0].idempotency_key
    const k2 = client.api.requestInterpretation.mock.calls[1][0].idempotency_key
    expect(k2).not.toBe(k1)
    expect(w.find('[data-testid="gate-skeleton"]').exists()).toBe(true)
  })

  it('retry_after_seconds = 0 (lạ) → không kẹt cooldown, về idle ngay', async () => {
    vi.useFakeTimers()
    client.api.requestInterpretation
      .mockRejectedValueOnce(new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 0 }))
      .mockResolvedValue({ data: JOB })
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(10)
    await flushPromises()
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-ask"]').isDisabled()).toBe(false)
  })

  it('cooldown khi unmount → không callback ma, không timer rò', async () => {
    vi.useFakeTimers()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
    )
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(10)
    w.unmount()
    await vi.advanceTimersByTimeAsync(120_000)
    await flushPromises()
    expect(client.api.requestInterpretation).toHaveBeenCalledTimes(1)
  })
})
