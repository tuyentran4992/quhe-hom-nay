// TopicGate E5 (card t_0285ac01) — cooldown 429 phải TỰ về 'idle' khi đồng hồ chạm 0:
// nút "Xin luận sâu" enable lại + bấm mở job mới (key idempotency mới). Defect QA: hết 90s
// nút vẫn disabled vĩnh viễn, nhãn "— 00:00", không còn đường nào gọi askFresh.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { readFileSync } from 'node:fs'
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
      // ANIM-LUAN (t_74502491): main có phase 'saved' → mount probe #5b; các test cooldown
      // cũ phụ thuộc số lần gọi requestInterpretation nên probe phải resolve exists=false.
      savedInterpretation: vi.fn(),
    },
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
  // main 8a47694 có phase 'saved': mount probe #5b. Mặc định "chưa có bài lưu" để
  // các test cooldown cũ không đổi hành vi (đường hỏi bình thường).
  client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
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

// ═══════════════════════════════════════════════════════════════════════════
// ANIM-LUAN mức A (card t_74502491, BOSS-GO 02/09 mục 3) — TDD viết ĐỎ trước.
// Defect boss bắt: mạng chậm, bấm "Xin luận sâu" tưởng không ăn (main 8a47694
// chỉ đổi phase SAU khi await xong POST #5). Yêu cầu chốt (CEO design, không đổi):
//   A1. click → state chờ NGAY tick đầu: gate-ask disabled + nhãn "Đang xin luận…"
//       + spinner gate-submit-spinner (CSS thuần, token có sẵn); ô hỏi + chip GIỮ
//       nguyên trong DOM (chống layout jump).
//   A2. job done → article gate-result bật fade `luan-fade` (opacity 0→1 +
//       translateY 6px→0, 280ms ease-out) khai trong <style scoped> của chính
//       TopicGate.vue + bắt buộc @media (prefers-reduced-motion: reduce).
//   A4. spinner không màu mới: rule .gate-spinner cấm hex — chỉ currentColor/token.
// jsdom không tính CSS từ <style scoped> SFC → chốt bằng class + readFileSync
// nguồn style (pattern tokens.test.js, đọc theo cwd vì import.meta.url là http).
// ═══════════════════════════════════════════════════════════════════════════
const SRC = readFileSync('src/components/TopicGate.vue', 'utf8')

describe('ANIM-LUAN A1 — phản hồi tức thì khi bấm "Xin luận sâu"', () => {
  it('network treo (POST #5 chờ vô hạn) → ngay tick sau: gate-ask disabled + nhãn "Đang xin luận" + spinner gate-submit-spinner', async () => {
    client.api.requestInterpretation.mockReturnValue(new Promise(() => {})) // không bao giờ resolve
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    // KHÔNG await thêm gì ngoài 1 flush — đây chính là "≤100ms đầu" lập trình được
    const btn = w.find('[data-testid="gate-ask"]')
    expect(btn.exists()).toBe(true)
    expect(btn.isDisabled()).toBe(true)
    expect(btn.text()).toContain('Đang xin luận')
    expect(w.find('[data-testid="gate-submit-spinner"]').exists()).toBe(true)
    expect(client.api.requestInterpretation).toHaveBeenCalledTimes(1)
  })

  it('khi submitting: ô hỏi + chip + counter VẪN trong DOM (chống layout jump), giá trị question giữ nguyên', async () => {
    client.api.requestInterpretation.mockReturnValue(new Promise(() => {}))
    const w = mountGate()
    await flushPromises()
    const ta = w.find('[data-testid="gate-question"]')
    await ta.setValue('tiền nong đình trệ')
    await w.find('[data-testid="gate-ask"]').trigger('click')
    expect(w.find('[data-testid="gate-question"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-question"]').element.value).toBe('tiền nong đình trệ')
    expect(w.findAll('[data-testid="gate-question-chip"]').length).toBeGreaterThan(0)
    expect(w.find('[data-testid="gate-question-counter"]').exists()).toBe(true)
  })

  it('bị từ chối (429 cooldown / cap) giữa lúc submitting → rời submitting: spinner tắt, nhãn cũ, branch tương ứng hiện (hành vi cũ giữ nguyên)', async () => {
    client.api.requestInterpretation.mockRejectedValue(
      new ApiError(429, 'AI_COOLDOWN', 'cool', { retry_after_seconds: 90 }),
    )
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises() // promise reject đã về
    expect(w.find('[data-testid="gate-submit-spinner"]').exists()).toBe(false)
    expect(w.find('[data-testid="gate-cooldown"]').exists()).toBe(true)
  })

  it('nút gate-retry (branch failed) bấm khi POST #5 treo → cùng pattern submitting (spinner + disabled)', async () => {
    client.api.requestInterpretation
      .mockRejectedValueOnce(new ApiError(500, 'SERVER_ERROR', 'sập'))
      .mockReturnValue(new Promise(() => {}))
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="gate-failed"]').exists()).toBe(true)
    await w.find('[data-testid="gate-retry"]').trigger('click')
    const btn = w.find('[data-testid="gate-ask"]')
    expect(btn.isDisabled()).toBe(true)
    expect(btn.text()).toContain('Đang xin luận')
    expect(w.find('[data-testid="gate-submit-spinner"]').exists()).toBe(true)
  })
})

describe('ANIM-LUAN A2/A4 — fade bài luận + style token-only', () => {
  it('job done → article gate-result mang class luan-fade (đường phase done)', async () => {
    client.api.requestInterpretation.mockResolvedValue({ data: JOB })
    client.api.aiJob.mockResolvedValue({ data: DONE })
    vi.useFakeTimers()
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-ask"]').trigger('click')
    await vi.advanceTimersByTimeAsync(2_000) // AI_POLL_MS = 2000
    await flushPromises()
    const art = w.find('[data-testid="gate-result"]')
    expect(art.exists()).toBe(true)
    expect(art.attributes('class')).toContain('luan-fade')
  })

  it('bấm "Xem lại" branch saved → bài lưu cũng bật luan-fade (đọc lại không đốt API)', async () => {
    client.api.savedInterpretation.mockResolvedValue({
      data: { exists: true, job_uuid: 'j-9', result: '[Hoàn cảnh]\nBài cũ.\n', completed_at: '2026-09-01T10:00:00+07:00' },
    })
    const w = mountGate()
    await flushPromises()
    await w.find('[data-testid="gate-review"]').trigger('click')
    await flushPromises()
    const art = w.find('[data-testid="gate-result"]')
    expect(art.exists()).toBe(true)
    expect(art.attributes('class')).toContain('luan-fade')
    expect(client.api.requestInterpretation).not.toHaveBeenCalled()
  })

  it('scoped CSS: @keyframes luan-fade opacity 0→1 + translateY(6px→0), 280ms ease-out', () => {
    const kf = SRC.match(/@keyframes\s+luan-fade\s*\{[\s\S]*?\n\}/)
    expect(kf).not.toBeNull()
    expect(kf[0]).toMatch(/opacity:\s*0/)
    expect(kf[0]).toMatch(/opacity:\s*1/)
    expect(kf[0]).toMatch(/translateY\(\s*6px\s*\)/)
    expect(kf[0]).toMatch(/translateY\(\s*0/)
    const rule = SRC.match(/\.luan-fade\s*\{[^}]*\}/)
    expect(rule).not.toBeNull()
    expect(rule[0]).toMatch(/280ms/)
    expect(rule[0]).toMatch(/ease-out/)
    expect(rule[0]).toContain('luan-fade')
  })

  it('scoped CSS: BẮT BUỘC @media (prefers-reduced-motion: reduce) → animation: none cho fade', () => {
    const mm = SRC.match(/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{[\s\S]*?\n\}/)
    expect(mm).not.toBeNull()
    expect(mm[0]).toMatch(/luan-fade[^}]*animation:\s*none/)
  })

  it('spinner CSS thuần: rule .gate-spinner có border + @keyframes quay, KHÔNG hex màu mới (token/currentColor thôi)', () => {
    const rule = SRC.match(/\.gate-spinner\s*\{[^}]*\}/)
    expect(rule).not.toBeNull()
    expect(rule[0]).toMatch(/border/)
    expect(rule[0]).not.toMatch(/#[0-9a-fA-F]{3,8}/) // cấm hex — chỉ currentColor/plan
    const kf = SRC.match(/@keyframes\s+gate-spin\s*\{[\s\S]*?\n\}/)
    expect(kf).not.toBeNull()
    expect(kf[0]).toMatch(/rotate/)
    expect(SRC).toMatch(/\.gate-spinner\s*\{[^}]*animation:\s*gate-spin/)
  })

  it('SOUL anti-generic: spinner kế thừa màu nút (currentColor) — không bg/tint ngoài token', () => {
    const rule = SRC.match(/\.gate-spinner\s*\{[^}]*\}/)
    expect(rule[0]).toMatch(/currentColor/)
  })
})
