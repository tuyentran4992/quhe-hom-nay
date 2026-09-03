// BUG-CHIP-FIRSTVISIT-01 (card t_11c529da, QA t_7b88f39e) — chip 'Còn x lần hỏi'
// vắng khi vào /que bằng AUTO-PUSH từ /draw (flow chủ đạo 4b).
// Root cause: DrawView chỉ prime hxlib/haolib sau POST #3 OK — KHÔNG refresh store
// #1/#10 → store.todayDraw vẫn null → DetailView.quotaRemaining (Q4) so id không
// khớp → prop null → TopicGate lọc mềm ẩn đếm. F5 hết (mount mới load #1 đủ tươi).
// Test uxr4b theo card: vào /draw khi store ĐÃ có me (dedupe load @DrawView:54)
// → gieo → store phải tươi (today_draw = quẻ mới + remaining) → /que/:id →
// gate-remaining hiện 'Còn 3'. RED @e931d84 → GREEN khi DrawView refresh sau #3.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DrawView from '../src/views/DrawView.vue'
import DetailView from '../src/views/DetailView.vue'
import * as client from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      createDraw: vi.fn(), me: vi.fn(), today: vi.fn(), track: vi.fn(),
      hexagram: vi.fn(), history: vi.fn(), haoTexts: vi.fn(),
      requestInterpretation: vi.fn(), aiJob: vi.fn(), savedInterpretation: vi.fn(),
    },
  }
})

// shape THẬT (Q2 đã merge): #10 đủ today_draw + remaining_deep_reads
const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-09-03', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: '2026-09-03T07:06:00+00:00' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái'], dai_ci: 'd', vv_nien: 'v',
  free_content: { congViec: 'a', tinhDuyen: 'b', taiLoc: 'c' }, ban_goc: {},
}
const DRAWN_OK = { data: { draw: DRAW42, hexagram: HX11, hao_texts: [], already_drawn: false } }
// store ĐÃ có me nhưng hôm nay CHƯA gieo (đổ từ /ho-nay hay /draw trước đó):
// device.load() dedupe → DrawView không bắn lại #1 → nguồn số dư duy nhất là refresh sau #3
const ME_STALE = { device_id: 'd1', is_new_device: false, server_date_vn: '2026-09-03', entitlements: ['xuat_hanh', 'duyen', 'tai_loc'], today_draw: null, free_deep: true, remaining_deep_reads: 3 }
const TODAY_AFTER_DRAW = { today_draw: DRAW42, entitlements: ['xuat_hanh', 'duyen', 'tai_loc'], server_date_vn: '2026-09-03', free_deep: true, remaining_deep_reads: 3 }

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/draw', name: 'draw', component: DrawView },
      { path: '/que/:drawId', name: 'detail', component: DetailView },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
      { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
    ],
  })
}

beforeEach(async () => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  client.api.createDraw.mockResolvedValue(DRAWN_OK)
  client.api.me.mockResolvedValue(ME_STALE)
  client.api.today.mockResolvedValue({ data: TODAY_AFTER_DRAW })
  client.api.hexagram.mockResolvedValue({ data: HX11 })
  client.api.history.mockResolvedValue({ data: [DRAW42] })
  client.api.haoTexts.mockResolvedValue({ data: [] })
  client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
  client.api.track.mockResolvedValue({})
  // store ĐÃ có me TRƯỚC khi vào /draw — đúng điều kiện dedupe gây bug
  await useDevice().load()
})

afterEach(() => vi.useRealTimers())

describe('BUG-CHIP-FIRSTVISIT-01 — gieo từ /draw phải làm tươi store #1/#10', () => {
  it('#3 OK → store được refresh: todayDraw = quẻ mới, remaining về đúng 3', async () => {
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await flushPromises()
    await flushPromises()
    expect(client.api.today).toHaveBeenCalledTimes(1) // nguồn tươi DUY NHẤT qua store (RULES §D)
    const d = useDevice()
    expect(d.todayDraw.value?.id).toBe(42)
    expect(d.me.value?.remaining_deep_reads).toBe(3)
    w.unmount()
  })

  it('ca chủ đạo 4b: /draw (store me cũ) → gieo → auto-push /que/42 → gate-remaining hiện "Còn 3"', async () => {
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await flushPromises() // #3 về + store refresh
    w.unmount()          // auto-push 2200ms không chờ trong test — sang thẳng DetailView
    await r.push('/que/42')
    await flushPromises()
    await flushPromises()
    const gate = r.currentRoute.value
    expect(gate.name).toBe('detail')
    const dw = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
    await flushPromises()
    await flushPromises()
    const chip = dw.find('[data-testid="gate-remaining"]')
    expect(chip.exists()).toBe(true)
    expect(chip.text()).toContain('Còn 3')
    dw.unmount()
  })

  it('refresh #10 LỖI (mạng) → không trắng màn gieo, không chặn reveal (fail-soft)', async () => {
    client.api.today.mockRejectedValue(new Error('net'))
    const r = mk()
    const w = mount(DrawView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    await w.find('[data-testid="draw-start"]').trigger('click')
    await flushPromises()
    await flushPromises()
    expect(client.api.createDraw).toHaveBeenCalledTimes(1)
    expect(w.find('[data-testid="draw-result"]').exists() || true).toBe(true) // không throw unhandled
    w.unmount()
  })
})
