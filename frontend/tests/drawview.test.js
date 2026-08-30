// DrawView S2 — magic sequence BẤT BIẾN: không reveal trước 1.5s kể cả API về sớm;
// API song song; DRAW_LIMIT_REACHED → về S1 toast (04-ui §4). FE-1: #3 về → PRIME cache
// hexagram (shape #3: data.draw và data.hexagram TÁCH nhau) để S3 không fetch #2 lại;
// lỗi #3 khác 409 (NETWORK/500) → draw-error, không trắng hành động, cho phép thử lại.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DrawView from '../src/views/DrawView.vue'
import MagicSequence from '../src/components/MagicSequence.vue'
import * as client from '../src/api/client.js'
import { _resetHexagramCacheForTests, useHexagrams } from '../src/composables/useHexagrams.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { createDraw: vi.fn(), me: vi.fn() } }
})

const DRAWN_OK = {
  data: {
    draw: { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' },
    hexagram: { id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0], free_content: { congViec: 'a', tinhDuyen: 'b', taiLoc: 'c' } },
    already_drawn: false,
  },
}

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/draw', name: 'draw', component: DrawView },
      { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
    ],
  })
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'] })
  _resetHexagramCacheForTests()
  // mockReset (không chỉ clear) — trả lời đúng vì createDraw được mount lại ở mỗi `it`
  client.api.createDraw.mockReset()
  client.api.me.mockReset()
})
afterEach(() => vi.useRealTimers())

describe('DrawView (C-08)', () => {
  it('khung S2 hiện nút gieo "Tâm tĩnh, chạm để gieo" (từ CẤM không xuất hiện)', async () => {
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    const btn = w.find('[data-testid="draw-start"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Tâm tĩnh, chạm để gieo')
    expect(w.text()).not.toMatch(/tâm linh|hóa giải|cúng|mở cung/)
  })

  it('bấm gieo → call #3 song song + UI không reveal trước 1500ms', async () => {
    client.api.createDraw.mockResolvedValue(DRAWN_OK)
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await flushPromises()
    expect(client.api.createDraw).toHaveBeenCalledTimes(1) // bay song song, không chờ animation
    expect(w.emitted('revealed')).toBeFalsy()
    expect(w.find('[data-testid="draw-result"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(1499)
    expect(w.find('[data-testid="draw-result"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(1)
    await flushPromises()
    expect(w.find('[data-testid="draw-result"]').exists()).toBe(true)
    // 04-ui B3: reveal xong → auto-push S3
    await vi.advanceTimersByTimeAsync(2000)
    expect(r.currentRoute.value.path).toBe('/que/42')
  })

  it('DRAW_LIMIT_REACHED → redirect S1 với flag toast', async () => {
    client.api.createDraw.mockRejectedValue(new client.ApiError(409, 'DRAW_LIMIT_REACHED', 'Hôm nay bạn đã gieo quẻ rồi. Quay lại sau 0h.', {}))
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(1600)
    await flushPromises()
    expect(r.currentRoute.value.name).toBe('home')
    expect(r.currentRoute.value.query.toast).toBe('draw_limit')
  })

  it('FE-1: #3 về → prime cache #2, S3 không cần fetch lại (useHexagrams().get có sẵn)', async () => {
    client.api.createDraw.mockResolvedValue(DRAWN_OK)
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(1600)
    await flushPromises()
    expect(useHexagrams().get(11)).toMatchObject({ ten: 'Địa Thiên Thái', symbol: '䷊' })
  })

  it('lỗi #3 không phải 409 (BE chết/NETWORK) → draw-error + nút thử lại, không trắng (AC4)', async () => {
    client.api.createDraw.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'Mất kết nối mạng.', {}))
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await vi.advanceTimersByTimeAsync(1600)
    await flushPromises()
    expect(w.find('[data-testid="draw-error"]').exists()).toBe(true)
    expect(w.find('[data-testid="draw-retry"]').exists()).toBe(true)
    // thử lại → bắn #3 lần 2, vẫn giữ C-08 (không reveal chỉ vì API về)
    client.api.createDraw.mockResolvedValue(DRAWN_OK)
    await w.find('[data-testid="draw-retry"]').trigger('click')
    expect(client.api.createDraw).toHaveBeenCalledTimes(2)
    expect(w.find('[data-testid="draw-error"]').exists()).toBe(false)
    expect(w.find('[data-testid="draw-result"]').exists()).toBe(false)
    await w.findComponent(MagicSequence).vm.$emit('done') // timer sequence mới đã qua 1500ms
    await flushPromises()
    expect(w.find('[data-testid="draw-result"]').exists()).toBe(true)
  })
})
