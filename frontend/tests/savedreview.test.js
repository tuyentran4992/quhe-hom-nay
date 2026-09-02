// REVIEW-LUAN FE (card t_b8df14e5, BOSS-GO 02/09 mục 1) — nút "Xem lại" + nhãn bài lưu.
// Contract BE CHỐT @ card/t_8aa93a01-be 9f4f78c (đọc trực tiếp InterpretationController::saved
// + AlreadyDoneLockTest, không đoán):
//   #5b GET /api/ai/interpretations/saved?draw_id=&topic=
//       → 200 {data:{exists:true, job_uuid, result, completed_at}} (CẤM field question — F7/PII)
//       → 402 UNLOCK_REQUIRED / 404 (draw chưa có) / 422 hình dạng.
//   POST #5 topic đã luận xong → 409 AI_ALREADY_DONE (đường 200-done-giả-man bị XÓA hẳn).
// Hành vi chốt bởi card:
//   1. unlocked + mount/đổi tab → probe saved 1 lần; exists=true → phase 'saved':
//      CHỈ nút "Xem lại" (gate-review) — ẩn ô question + chip + nút "Xin luận sâu".
//   2. Bấm Xem lại → render ĐÚNG luanBlocks như phase done + nhãn "Bài đã lưu trước đó"
//      (gate-saved-label); KHÔNG gọi POST #5 (tiết kiệm chi phí AI — mục tiêu ngầm boss).
//   3. that (AI_FILTERED/timeout/network → failed) KHÔNG khóa — gate-retry còn nguyên.
//      Chỉ 409 AI_ALREADY_DONE mới chuyển sang chế độ saved (askFresh catch, ưu tiên
//      HƠN cooldown/cap — card mục 5).
//   4. watch reset: trở lại topic đã luận → về 'saved' chứ không 'idle' (card mục 6).
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

const JOB = { job_uuid: 'j-1', status: 'queued' }
const SAVED = {
  exists: true, job_uuid: 'j-9',
  result: '[Hoàn cảnh]\nEm vướng chuyện tiền.\n\n[Việc nên làm]\nChờ qua rằm.',
  completed_at: '2026-09-01T10:00:00+07:00',
}

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
    device_id: 'd', is_new_device: false, server_date_vn: '2026-09-02',
    entitlements: ['duyen', 'tai_loc', 'xuat_hanh'], today_draw: null,
  })
  // mặc định: chưa có bài lưu → idle (đường hỏi bình thường không bị test này chặn)
  client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
  client.api.requestInterpretation.mockResolvedValue({ data: JOB })
  await useDevice().load()
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
  vi.useRealTimers()
})

// NOTE: test CONTRACT #5b thật của client.js (path/query/envelope/ApiError) nằm ở
// apiclient.test.js — file đó KHÔNG mock client, pattern chốt sẵn cho #1..#12.

describe('REVIEW-LUAN — chế độ saved khi chủ đề đã luận xong', () => {
  it('unlocked + saved exists=true → phase saved: CHỈ nút gate-review, ẩn gate-ask + gate-question + chip', async () => {
    client.api.savedInterpretation.mockResolvedValue({ data: SAVED })
    const w = mountGate()
    await flushPromises()
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-question"]').exists()).toBe(false)
    expect(w.findAll('[data-testid="gate-question-chip"]')).toHaveLength(0)
    // probe ĐÚNG 1 lần với params contract (draw_id số, topic tab hiện hành)
    expect(client.api.savedInterpretation).toHaveBeenCalledTimes(1)
    expect(client.api.savedInterpretation.mock.calls[0][0]).toEqual({ draw_id: 42, topic: 'duyen' })
  })

  it('bấm "Xem lại" → bài cũ render như done (luanBlocks) + nhãn gate-saved-label; KHÔNG gọi POST #5', async () => {
    client.api.savedInterpretation.mockResolvedValue({ data: SAVED })
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-review"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-result"]').exists()).toBe(true)
    const label = w.find('[data-testid="gate-saved-label"]')
    expect(label.exists()).toBe(true)
    expect(label.text()).toContain('Bài đã lưu trước đó')
    // render ĐÚNG như phase done: parseLuan cắt marker, heading + body sạch
    const heads = w.findAll('[data-testid="luan-heading"]').map((h) => h.text())
    expect(heads).toContain('Hoàn cảnh')
    expect(w.find('[data-testid="luan-body"]').text()).toContain('Em vướng chuyện tiền.')
    // mục tiêu ngầm boss: bấm đọc lại KHÔNG đốt tiền AI
    expect(client.api.requestInterpretation).not.toHaveBeenCalled()
    // saved API không trả question → dòng "Bạn hỏi:" ẩn (F7 — card mục 2)
    expect(w.find('[data-testid="gate-result-question"]').exists()).toBe(false)
  })

  it('saved exists=false → giữ đường hỏi bình thường (gate-ask hiện, không gate-review)', async () => {
    const w = mountGate()
    await flushPromises()
    expect(client.api.savedInterpretation).toHaveBeenCalledTimes(1)
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-question"]').exists()).toBe(true)
  })

  it('probe saved lỗi mạng/404/500 → fallback idle, khách vẫn hỏi được (không trắng màn)', async () => {
    client.api.savedInterpretation.mockRejectedValue(new ApiError(0, 'NETWORK', 'mất mạng'))
    const w = mountGate()
    await flushPromises()
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(false)
  })

  it('chưa unlock → KHÔNG probe saved (gate BE 402 đã chắc, đỡ 1 request)', async () => {
    _resetDeviceForTests()
    client.api.me.mockResolvedValue({
      device_id: 'd', is_new_device: false, server_date_vn: '2026-09-02',
      entitlements: [], today_draw: null,
    })
    await useDevice().load()
    const w = mountGate()
    await flushPromises()
    expect(client.api.savedInterpretation).not.toHaveBeenCalled()
    expect(w.find('[data-testid="gate-locked"]').exists()).toBe(true)
  })

  it('đổi tab topic đã luận → probe lại saved cho topic mới (watch, không chỉ mount)', async () => {
    client.api.savedInterpretation.mockImplementation(({ topic }) =>
      Promise.resolve({ data: topic === 'tai_loc' ? SAVED : { exists: false } }),
    )
    const w = mountGate('duyen')
    await flushPromises()
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(true)
    await w.setProps({ topic: 'tai_loc' })
    await flushPromises()
    expect(client.api.savedInterpretation).toHaveBeenCalledTimes(2)
    expect(client.api.savedInterpretation.mock.calls[1][0]).toEqual({ draw_id: 42, topic: 'tai_loc' })
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
  })

  it('card mục 6: đang xem bài (done) rồi đổi tab rồi quay lại topic đã luận → về saved, không mất bài về idle', async () => {
    vi.useFakeTimers()
    client.api.savedInterpretation.mockResolvedValue({ data: SAVED })
    client.api.aiJob.mockResolvedValue({ data: { ...JOB, status: 'done', result: 'bài vừa luận' } })
    const w = mountGate()
    await flushPromises()
    // exists=true nhưng khách chưa bấm → phase saved
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
    await w.setProps({ topic: 'xuat_hanh' })
    await flushPromises()
    await w.setProps({ topic: 'duyen' })
    await flushPromises()
    vi.useRealTimers()
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-ask"]').exists()).toBe(false)
  })
})

describe('REVIEW-LUAN — neo hành vi chuyển locked→unlocked (payment xong)', () => {
  // Watch cũ có nhánh "vừa trả tiền → tự xin luận". Gộp watch vào probe (#5b) phải
  // GIỮ đường đó khi chưa có bài lưu, và PHẢI NHẢNH: vừa mua mà topic đã luận
  // (thiết bị khác từng hỏi) → về saved, không tự đốt POST #5.
  it('locked → entitlement về (probe trắng) → TỰ xin luận như UX cũ (askFresh 1 lần)', async () => {
    _resetDeviceForTests()
    client.api.me.mockResolvedValue({
      device_id: 'd', is_new_device: false, server_date_vn: '2026-09-02',
      entitlements: [], today_draw: null,
    })
    await useDevice().load()
    client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
    const w = mountGate()
    await flushPromises()
    expect(w.find('[data-testid="gate-locked"]').exists()).toBe(true)
    expect(client.api.requestInterpretation).not.toHaveBeenCalled()
    // paywall paid → device.refresh(#10) mang entitlement mới vào
    client.api.today.mockResolvedValue({ data: { entitlements: ['duyen'] } })
    await useDevice().refresh()
    await flushPromises()
    expect(client.api.savedInterpretation).toHaveBeenCalledTimes(1)
    expect(client.api.requestInterpretation).toHaveBeenCalledTimes(1)
    expect(w.find('[data-testid="gate-skeleton"]').exists() || w.find('[data-testid="gate-ask"]').exists()).toBe(true)
  })

  it('locked → entitlement về NHƯNG topic đã có bài lưu → phase saved, KHÔNG tự POST #5', async () => {
    _resetDeviceForTests()
    client.api.me.mockResolvedValue({
      device_id: 'd', is_new_device: false, server_date_vn: '2026-09-02',
      entitlements: [], today_draw: null,
    })
    await useDevice().load()
    const w = mountGate()
    await flushPromises()
    client.api.today.mockResolvedValue({ data: { entitlements: ['duyen'] } })
    client.api.savedInterpretation.mockResolvedValue({ data: SAVED })
    await useDevice().refresh()
    await flushPromises()
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
    expect(client.api.requestInterpretation).not.toHaveBeenCalled()
  })
})

describe('REVIEW-LUAN — 409 AI_ALREADY_DONE: ưu tiên trước cooldown/cap; thất bại bất biến', () => {
  it('POST #5 → 409 AI_ALREADY_DONE → gọi lại saved lấy result, phase saved (không failed/cooldown)', async () => {
    client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
    const w = mountGate()
    await flushPromises()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(409, 'AI_ALREADY_DONE', 'chủ đề này đã luận rồi', {}),
    )
    client.api.savedInterpretation.mockResolvedValue({ data: SAVED })
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-failed"]').exists()).toBe(false)
    // probe lần 2 = savedInterpretation được gọi lại để LẤY KẾT QUẢ (card mục 4)
    expect(client.api.savedInterpretation).toHaveBeenCalledTimes(2)
  })

  it('card mục 5 — 409 có retry_after_seconds trong details (BE gắn gate trước cooldown): vẫn ưu tiên saved, KHÔNG đếm ngược', async () => {
    client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
    const w = mountGate()
    await flushPromises()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(409, 'AI_ALREADY_DONE', 'đã luận', { retry_after_seconds: 90 }),
    )
    client.api.savedInterpretation.mockResolvedValue({ data: SAVED })
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(true)
  })

  it('that thật (AI_FILTERED/500/network → failed) KHÔNG khóa: gate-retry còn nguyên, bấm lại chạy đường mở', async () => {
    const w = mountGate()
    await flushPromises()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(200, 'AI_FILTERED', 'bài bị chặn từ', {}),
    )
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-failed"]').exists()).toBe(true)
    const retry = w.find('[data-testid="gate-retry"]')
    expect(retry.exists()).toBe(true)
    // bấm Thử lại → POST #5 tiếp tục được (không bị chặn bằng 409 giả/khóa FE)
    client.api.requestInterpretation.mockResolvedValue({ data: JOB })
    await retry.trigger('click')
    await flushPromises()
    expect(client.api.requestInterpretation).toHaveBeenCalledTimes(2)
  })

  it('409 nhưng saved báo exists=false (race hi hữu) → về failed chứ không kẹt màn trắng', async () => {
    client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
    const w = mountGate()
    await flushPromises()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(409, 'AI_ALREADY_DONE', 'đã luận', {}),
    )
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-failed"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-retry"]').exists()).toBe(true)
  })
})

describe('REVIEW-LUAN — đường cooldown/cap hiện hành giữ nguyên (regression card mục 5)', () => {
  it('AI_COOLDOWN 429 → gate-cooldown đếm ngược như cũ, không phải saved', async () => {
    const w = mountGate()
    await flushPromises()
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
    )
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(false)
  })

  it('AI_GLOBAL_CAP → gate-cap như cũ', async () => {
    const w = mountGate()
    await flushPromises()
    client.api.requestInterpretation.mockRejectedValue(new ApiError(429, 'AI_GLOBAL_CAP', 'cap', {}))
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-cap"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-review"]').exists()).toBe(false)
  })
})
