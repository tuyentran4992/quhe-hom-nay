// QUOTA-N/Q4 (card t_7dd7f983) — "còn x/N lần hỏi" + ẩn nút khi hết quota + dòng nghi thức.
// Wording = /data/agents/copywriter-vn/outbox/t_QUOTA-Q1/wording.md PHƯƠNG ÁN 1 (mục 1–2–3).
// Payload shape THẬT theo Q2 t_1b5a0c23 (không tự chế field):
//  - #1/#10: remaining_deep_reads (int, = max(0, N − lượt thật)) — MeController.php:52/67
//  - 429 #5: code 'quota_exceeded', details {max_deep_reads_per_draw, used, remaining}
//    — InterpretationException::quotaExceeded()
// Luật nhà: FE KHÔNG hardcode N=3; x=0 → ẩn trọn khối nút hỏi, hiện dòng nghi thức;
// bài đã luận trong phiên (in-memory) vẫn đọc được khi hết quota; không phá generator
// LUAN-RACE (gen guard) — mọi state mới chỉ được ghi khi myGen === gen.
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

const QUOTA_429 = () => new ApiError(429, 'quota_exceeded', 'Quẻ này đã dùng hết 3 lượt luận sâu.', {
  max_deep_reads_per_draw: 3, used: 3, remaining: 0,
})
// Chuỗi Q1 PA1 nguyên văn (test chấm từng câu, chống lệch一字):
const NOTE_PA1 = 'Quẻ này đã đủ 3 lời. Ngẫm kỹ bài đã luận — ngày mai gieo quẻ mới.'
const HINT_PA1 = 'Bài đã luận vẫn còn nguyên với quẻ này — lúc nào nhớ, mở lại quẻ là đọc được.'

let wrapper = null
function mountGate(props = {}) {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
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
  // shape THẬT #1 sau Q2: có remaining_deep_reads (3 = chưa hỏi lượt nào)
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
  vi.useRealTimers()
})

describe('Q4 mục 2 — nhãn "còn x/N lần hỏi" theo remaining', () => {
  it('prop remaining=3 → gate-remaining hiện, chứa 3, KHÔNG chứa "0/3"', async () => {
    const w = mountGate({ remaining: 3 })
    await flushPromises()
    const el = w.find('[data-testid="gate-remaining"]')
    expect(el.exists()).toBe(true)
    expect(el.text()).toContain('3')
    expect(el.text()).toContain('lần')
  })
  it('x=3→1 theo props: bộ đếm đổi theo giá trị prop từng lần setProps', async () => {
    const w = mountGate({ remaining: 3 })
    await flushPromises()
    for (const x of [2, 1]) {
      await w.setProps({ remaining: x })
      await flushPromises()
      expect(w.find('[data-testid="gate-remaining"]').text()).toContain(String(x))
    }
  })
  it('remaining=0 → ẨN hẳn gate-remaining (nhường khối nghi thức)', async () => {
    const w = mountGate({ remaining: 0 })
    await flushPromises()
    expect(w.find('[data-testid="gate-remaining"]').exists()).toBe(false)
  })
  it('remaining vắng (API cũ/chưa load — lọc mềm) → không hiện bộ đếm, không kẹt', async () => {
    const w = mountGate() // store lúc này còn nguyên response cũ? không — dựng lại store sạch:
    await w.unmount()
    _resetDeviceForTests()
    client.api.me.mockResolvedValue({
      device_id: 'd2', is_new_device: false, server_date_vn: '2026-09-03',
      entitlements: ['duyen'], today_draw: null,
    })
    await useDevice().load()
    const w2 = mountGate()
    await flushPromises()
    expect(w2.find('[data-testid="gate-remaining"]').exists()).toBe(false)
    expect(w2.find('[data-testid="gate-ask"]').exists()).toBe(true)
  })
  it('chưa unlock (phase locked) → không hiện bộ đếm quota', async () => {
    _resetDeviceForTests()
    client.api.me.mockResolvedValue({
      device_id: 'd3', is_new_device: false, server_date_vn: '2026-09-03',
      entitlements: [], today_draw: null, remaining_deep_reads: 3,
    })
    await useDevice().load()
    const w = mountGate({ remaining: 3 })
    await flushPromises()
    expect(w.find('[data-testid="gate-remaining"]').exists()).toBe(false)
  })
})

describe('Q4 mục 1+3 — hết quota: ẩn nút luận sâu + dòng nghi thức (Q1 PA1)', () => {
  it('429 quota_exceeded lúc bấm → gate-quota: đúng 2 chuỗi PA1, KHÔNG còn nút gọi', async () => {
    client.api.requestInterpretation.mockRejectedValue(QUOTA_429())
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    const block = w.find('[data-testid="gate-quota"]')
    expect(block.exists()).toBe(true)
    expect(w.find('[data-testid="gate-quota-note"]').text()).toBe(NOTE_PA1)
    expect(w.find('[data-testid="gate-quota-hint"]').text()).toBe(HINT_PA1)
    // ẩn TRỌN khối gọi: không nút "Xin luận sâu", không ô question, không nút retry
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-question"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-retry"]').exists()).toBe(false)
  })
  it('remaining=0 lúc mount (reload hết quota, topic chưa có bài lưu) → thẳng phase quota', async () => {
    const w = mountGate({ remaining: 0 })
    await flushPromises()
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(false)
  })
  it('remaining=0 nhưng topic CÓ bài lưu → vẫn là "Xem lại" (saved), không nuốt thành quota', async () => {
    client.api.savedInterpretation.mockResolvedValue({
      data: { exists: true, job_uuid: 'j-9', result: 'bài-lưu-A', completed_at: '2026-09-03T02:00:00Z' },
    })
    const w = mountGate({ remaining: 0 })
    await flushPromises()
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
    await w.find('[data-testid="gate-review"]').trigger('click')
    expect(w.text()).toContain('bài-lưu-A')
  })
  it('N KHÔNG hardcode: 429 details max=5 → dòng nghi thức in "đủ 5 lời"', async () => {
    client.api.requestInterpretation.mockRejectedValue(new ApiError(429, 'quota_exceeded', 'x', {
      max_deep_reads_per_draw: 5, used: 5, remaining: 0,
    }))
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-quota-note"]').text()).toContain('đủ 5 lời')
  })
})

describe('Q4 — bài đã luận trong phiên vẫn đọc được khi hết quota (in-memory)', () => {
  it('job done hiển thị bài → remaining tụt về 0 (prop đổi) → bài KHÔNG bị lấn, không quota phase', async () => {
    vi.useFakeTimers()
    client.api.requestInterpretation.mockResolvedValue({ data: { job_uuid: 'j-1', status: 'queued' } })
    client.api.aiJob.mockResolvedValue({ data: { job_uuid: 'j-1', status: 'done', result: 'bai-cua-phien' } })
    const w = mountGate({ remaining: 2 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(2000) // poll #6 tới hạn → done
    await flushPromises()
    expect(w.find('[data-testid="gate-result"]').text()).toContain('bai-cua-phien')
    // server trừ lượt sau bài đó: remaining về 0 — KHÔNG unset phase, bài còn nguyên
    await w.setProps({ remaining: 0 })
    await flushPromises()
    expect(w.find('[data-testid="gate-result"]').text()).toContain('bai-cua-phien')
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(false)
  })
  it('bài xong lượt THẬT → refresh #10 đúng 1 lần để bộ đếm tươi (store chủ duy nhất)', async () => {
    vi.useFakeTimers()
    client.api.requestInterpretation.mockResolvedValue({ data: { job_uuid: 'j-1', status: 'queued' } })
    client.api.aiJob.mockResolvedValue({ data: { job_uuid: 'j-1', status: 'done', result: 'bai-A' } })
    client.api.today.mockResolvedValue({ data: { entitlements: ['duyen'], remaining_deep_reads: 2 } })
    const w = mountGate({ remaining: 3 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    expect(client.api.today).toHaveBeenCalledTimes(1)
    await flushPromises()
    expect(w.find('[data-testid="gate-remaining"]').text()).toContain('2')
  })
})

describe('Q4 — không phá các nhánh cũ + guard LUAN-RACE', () => {
  it('cooldown 429 AI_COOLDOWN vẫn là gate-cooldown (KHÔNG bị gộp vào quota)', async () => {
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
    )
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(false)
  })
  it('cap 429 AI_GLOBAL_CAP vẫn gate-cap (code khác, UX khác)', async () => {
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_GLOBAL_CAP', 'cap', {}),
    )
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-cap"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(false)
  })
  it('đổi topic khi đang quota: tab mới còn lượt → hết cảnh kẹt (watch reset + prop mới)', async () => {
    client.api.requestInterpretation.mockRejectedValue(QUOTA_429())
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(true)
    await w.setProps({ topic: 'tai_loc', remaining: 1 })
    await flushPromises()
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
  })
  it('quota lúc POST về muộn sau đổi tab (LUAN-RACE): im lặng, không ghi phase quota lên tab mới', async () => {
    vi.useFakeTimers()
    let rejectLate
    client.api.requestInterpretation.mockImplementation(() => new Promise((_res, rej) => { rejectLate = rej }))
    const w = mountGate({ remaining: 1 })
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click') // submitting, POST treo
    await w.setProps({ topic: 'tai_loc' })                    // đổi tab → gen++
    await flushPromises()
    rejectLate(QUOTA_429())                                    // POST cũ về MUỘN
    await flushPromises()
    vi.advanceTimersByTime(100)
    await flushPromises()
    expect(w.find('[data-testid="gate-quota"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true) // tab mới: idle bình thường
    vi.useRealTimers()
  })
})
