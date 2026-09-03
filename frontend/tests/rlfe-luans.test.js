// [RL-FE] t_47c88de0 — AskedLuansSheet + LuanArticle + nút «Đã hỏi quẻ này» atop DetailView.
// Contract BE CHỐT trong card [RL-BE] (song song, mock đúng schema khi test):
//   GET /api/draws/{draw_id}/luans
//     → 200 {data:[{id, job_uuid, topic, router_category(str|null), label(luôn có),
//        question(str|null), excerpt(≤120 ký tự), finished_at(ISO8601|null), result}],
//        meta:{count:N}} — sort mới nhất đầu.
// Hành vi chốt bởi card RL-FE:
//   F1  sheet render 3 item mock đúng thứ tự + đủ 7 testid; question=null → TOPIC_LABELS,
//       không có chữ "null"/trắng; question+topic đều fallback null → BỎ DÒNG.
//   F2  bấm item → LuanArticle full-text (parseLuan heading/body), quay lại về list.
//   F4  quẻ 0 bài done (mock rỗng) → KHÔNG có luans-open trong DOM.
//   D3  owner fetch duy nhất = useDrawLuans (cache Map draw_id→rows + inflight): DetailView
//       ensure 1 lần lúc resolveDraw, sheet mở KHÔNG re-fetch (race rule §D nhà).
//   mục 5 state lỗi: 404/429/network → khối failed + nút «Thử lại» (khuôn TopicGate, copy).
//   ESC/scrim/nút đóng; 0 pushState — sheet là overlay nội bộ (không unmount DetailView).
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import AskedLuansSheet from '../src/components/AskedLuansSheet.vue'
import DetailView from '../src/views/DetailView.vue'
import * as client from '../src/api/client.js'
import { ApiError } from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'
import { _resetDrawLuansCacheForTests } from '../src/composables/useDrawLuans.js'
import { TOPIC_LABELS } from '../src/constants.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), history: vi.fn(),
      haoTexts: vi.fn(), requestInterpretation: vi.fn(), aiJob: vi.fn(),
      savedInterpretation: vi.fn(), track: vi.fn(), drawLuans: vi.fn(),
    },
  }
})

// ── fixtures đúng schema BE (mới nhất đầu — BE sort, FE render nguyên序) ──────
const LUAN_A = {
  id: 11, job_uuid: 'j-11', topic: 'duyen', router_category: 'tinh_duyen', label: 'Tình duyên',
  question: 'Bao giờ em có người ta?',
  excerpt: 'Chuyện tình của em đang ở đoạn chờ một lời rõ ràng…',
  finished_at: '2026-09-03T09:12:00Z',
  result: '[Hoàn cảnh]\nEm đang đợi một câu trả lời.\n\n[Việc nên làm cụ thể tuần này]\nNhắn lại cho người ấy trước cuối tuần.',
}
const LUAN_B = {
  // question NULL → dòng hỏi fallback TOPIC_LABELS[topic] (= 'Xuất hành', KHÁC label
  // router 'Đi lại' — chứng minh đúng nguồn TOPIC_LABELS chứ không phải label chip)
  id: 10, job_uuid: 'j-10', topic: 'xuat_hanh', router_category: 'di_lich', label: 'Đi lại',
  question: null, excerpt: 'Tuần này đi xa nên dời sang cuối tuần.',
  finished_at: '2026-09-02T22:04:00Z',
  result: 'Bài trơn không marker — khối heading rỗng, không mất chữ.',
}
const LUAN_C = {
  // question null + topic lạ (không có trong TOPIC_LABELS) → BỎ DÒNG hỏi, không bịa.
  // finished_at null → bỏ dòng giờ. excerpt rỗng → bỏ dòng excerpt.
  id: 9, job_uuid: 'j-9', topic: 'khong_ro', router_category: null, label: 'Điều cần bàn',
  question: null, excerpt: '', finished_at: null,
  result: 'Kết quả cũ bị model dẫn thừa trước marker.',
}
const LUANS3 = { data: [LUAN_A, LUAN_B, LUAN_C], meta: { count: 3 } }

let wrapper = null
function mountSheet(drawId = 42) {
  wrapper = mount(AskedLuansSheet, { props: { drawId } })
  return wrapper
}
async function openSheet(data) {
  client.api.drawLuans.mockResolvedValue(data)
  const w = mountSheet()
  await flushPromises()
  return w
}

// ── DetailView harness (theo khuôn detailview.test.js) ────────────────────────
const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-09-03', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái'], dai_ci: 'Trời xuống đất lên.', vv_nien: 'Năm của người biết chờ.',
  free_content: { congViec: 'cv', tinhDuyen: 'td', taiLoc: 'tl' }, ban_goc: {},
}
async function mountDetail() {
  const r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: DetailView },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
      { path: '/tam-tu', name: 'donate', component: { template: '<div/>' } },
    ],
  })
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
  await r.push('/que/42')
  await r.isReady()
  await flushPromises()
  await flushPromises()
  return { r, w }
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  _resetDrawLuansCacheForTests()
  document.body.innerHTML = ''
  client.api.me.mockResolvedValue({
    device_id: 'd', is_new_device: false, server_date_vn: '2026-09-03',
    entitlements: [], today_draw: DRAW42, remaining_deep_reads: 2, max_deep_reads_per_draw: 3,
  })
  client.api.today.mockResolvedValue({ data: {} })
  client.api.hexagram.mockResolvedValue({ data: HX11 })
  client.api.haoTexts.mockResolvedValue({ data: [] })
  client.api.history.mockResolvedValue({ data: [DRAW42] })
  client.api.savedInterpretation.mockResolvedValue({ data: { exists: false } })
  client.api.requestInterpretation.mockResolvedValue({ data: { job_uuid: 'j', status: 'queued' } })
  client.api.aiJob.mockResolvedValue({ data: { status: 'queued' } })
  client.api.track.mockResolvedValue({})
  client.api.drawLuans.mockResolvedValue({ data: [], meta: { count: 0 } })
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
  vi.useRealTimers()
})

// ══ F1 — sheet render 3 item đúng thứ tự + đủ testid + fallback/question-null ══
describe('RL-FE F1 — AskedLuansSheet list', () => {
  it('render 3 item theo đúng thứ tự BE trả (mới nhất đầu), title sống', async () => {
    const w = await openSheet(LUANS3)
    expect(w.find('[data-testid="luans-title"]').exists()).toBe(true)
    const items = w.findAll('[data-testid="luans-item"]')
    expect(items).toHaveLength(3)
    expect(items[0].text()).toContain(LUAN_A.question)
    expect(items[1].text()).toContain('Đi lại')
    expect(items[2].find('[data-testid="luans-label"]').text()).toBe('Điều cần bàn')
  })

  it('mỗi item có đủ 4 testid thành phần label/question/time/excerpt (item chuẩn)', async () => {
    const w = await openSheet(LUANS3)
    const it0 = w.findAll('[data-testid="luans-item"]')[0]
    expect(it0.find('[data-testid="luans-label"]').exists()).toBe(true)
    expect(it0.find('[data-testid="luans-question"]').exists()).toBe(true)
    expect(it0.find('[data-testid="luans-time"]').exists()).toBe(true)
    expect(it0.find('[data-testid="luans-excerpt"]').exists()).toBe(true)
    expect(it0.find('[data-testid="luans-label"]').text()).toBe('Tình duyên')
    expect(it0.find('[data-testid="luans-excerpt"]').text()).toBe(LUAN_A.excerpt)
  })

  it('question=null → dòng hỏi là TOPIC_LABELS[topic], tuyệt đối không có chữ "null"', async () => {
    const w = await openSheet(LUANS3)
    const it1 = w.findAll('[data-testid="luans-item"]')[1]
    expect(it1.find('[data-testid="luans-question"]').text()).toBe(TOPIC_LABELS.xuat_hanh)
    expect(it1.find('[data-testid="luans-label"]').text()).toBe('Đi lại') // nguồn khác → chứng minh fallback đúng bảng
    expect(w.text()).not.toMatch(/\bnull\b/)
  })

  it('question null + topic lạ → BỎ DÒNG hỏi; finished_at null → bỏ dòng giờ; excerpt rỗng → bỏ dòng excerpt (không bịa)', async () => {
    const w = await openSheet(LUANS3)
    const it2 = w.findAll('[data-testid="luans-item"]')[2]
    expect(it2.find('[data-testid="luans-question"]').exists()).toBe(false)
    expect(it2.find('[data-testid="luans-time"]').exists()).toBe(false)
    expect(it2.find('[data-testid="luans-excerpt"]').exists()).toBe(false)
    expect(it2.find('[data-testid="luans-label"]').text()).toBe('Điều cần bàn') // chip luôn còn
  })

  it('item là button thật (a11y baseline), touch target ≥44px qua class', async () => {
    const w = await openSheet(LUANS3)
    const it0 = w.findAll('[data-testid="luans-item"]')[0]
    expect(it0.element.tagName).toBe('BUTTON')
    expect(it0.attributes('class')).toMatch(/min-h-\[/)
  })
})

// ══ F2 — bấm item → LuanArticle full-text; quay lại về list, còn nguyên DOM list ══
describe('RL-FE F2 — LuanArticle full-text trong sheet', () => {
  it('bấm item → render đủ bài parseLuan (heading + body khớp mock result)', async () => {
    const w = await openSheet(LUANS3)
    await w.findAll('[data-testid="luans-item"]')[0].trigger('click')
    const art = w.find('[data-testid="luans-article"]')
    expect(art.exists()).toBe(true)
    const heads = art.findAll('[data-testid="luan-heading"]').map((h) => h.text())
    expect(heads).toEqual(['Hoàn cảnh', 'Việc nên làm cụ thể tuần này'])
    expect(art.findAll('[data-testid="luan-body"]')[0].text()).toContain('Em đang đợi một câu trả lời.')
    expect(art.findAll('[data-testid="luan-body"]')[1].text()).toContain('Nhắn lại cho người ấy trước cuối tuần.')
    // dòng «Bạn hỏi: …» = question của bài được mở
    expect(art.find('[data-testid="luans-article-question"]').text()).toContain(LUAN_A.question)
  })

  it('bài trơn không marker → không heading, không mất chữ (parseLuan nguồn duy nhất)', async () => {
    const w = await openSheet(LUANS3)
    await w.findAll('[data-testid="luans-item"]')[1].trigger('click')
    const art = w.find('[data-testid="luans-article"]')
    expect(art.findAll('[data-testid="luan-heading"]')).toHaveLength(0)
    expect(art.find('[data-testid="luan-body"]').text()).toBe(LUAN_B.result)
  })

  it('quay lại (luans-back) → về list; list KHÔNG unmount → scrollTop giữ nguyên', async () => {
    const w = await openSheet(LUANS3)
    const list = w.find('[data-testid="luans-list"]')
    list.element.scrollTop = 123 // jsdom giữ property trên element sống
    await w.findAll('[data-testid="luans-item"]')[0].trigger('click')
    expect(w.find('[data-testid="luans-list"]').isVisible()).toBe(false)
    await w.find('[data-testid="luans-back"]').trigger('click')
    expect(w.findAll('[data-testid="luans-item"]')).toHaveLength(3)
    expect(w.find('[data-testid="luans-list"]').element.scrollTop).toBe(123)
  })
})

// ══Overlay: đóng bằng nút + scrim + ESC; 0 pushState ═════════════════════════
describe('RL-FE sheet close', () => {
  it('nút đóng → emit close', async () => {
    const w = await openSheet(LUANS3)
    await w.find('[data-testid="luans-close"]').trigger('click')
    expect(w.emitted('close')).toBeTruthy()
  })
  it('ESC → emit close; keydown KHÔNG thuộc target overlay vẫn ăn', async () => {
    const w = await openSheet(LUANS3)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(w.emitted('close')).toBeTruthy()
  })
  it('click scrim (nền tối) → emit close; click trong panel KHÔNG đóng', async () => {
    const w = await openSheet(LUANS3)
    await w.find('[data-testid="luans-panel"]').trigger('click')
    expect(w.emitted('close')).toBeUndefined()
    await w.find('[data-testid="luans-scrim"]').trigger('click')
    expect(w.emitted('close')).toHaveLength(1)
  })
})

// ══ mục 5 — state lỗi: failed + «Thử lại» ═══════════════════════════════════
describe('RL-FE failed state', () => {
  it('429/network → khối luans-failed + nút Thử lại; retry thành công → render list', async () => {
    client.api.drawLuans.mockRejectedValueOnce(new ApiError(429, 'RATE_LIMITED', 'chậm lại'))
    const w = mountSheet()
    await flushPromises()
    expect(w.find('[data-testid="luans-failed"]').exists()).toBe(true)
    expect(w.findAll('[data-testid="luans-item"]')).toHaveLength(0)
    client.api.drawLuans.mockResolvedValue(LUANS3)
    await w.find('[data-testid="luans-retry"]').trigger('click')
    await flushPromises()
    expect(w.findAll('[data-testid="luans-item"]')).toHaveLength(3)
    expect(w.find('[data-testid="luans-failed"]').exists()).toBe(false)
  })
  it('404 (device-scope ẩn) → cùng khuôn failed', async () => {
    client.api.drawLuans.mockRejectedValue(new ApiError(404, 'NOT_FOUND', ''))
    const w = mountSheet()
    await flushPromises()
    expect(w.find('[data-testid="luans-failed"]').exists()).toBe(true)
  })
})

// ══ D3 — owner duy nhất + cache Map trong phiên ═════════════════════════════
describe('RL-FE fetch ownership', () => {
  it('DetailView resolveDraw → gọi drawLuans ĐÚNG 1 lần; mở sheet → không re-fetch (cache)', async () => {
    client.api.drawLuans.mockResolvedValue(LUANS3)
    const { w } = await mountDetail()
    const btn = w.find('[data-testid="luans-open"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('3 lời')
    expect(client.api.drawLuans).toHaveBeenCalledTimes(1)
    await btn.trigger('click')
    await flushPromises()
    expect(w.find('[data-testid="luans-title"]').exists()).toBe(true)
    expect(client.api.drawLuans).toHaveBeenCalledTimes(1) // sheet mở = cache hit, 0 fetch trùng
    expect(w.findAll('[data-testid="luans-item"]')).toHaveLength(3)
  })

  it('sheet mở rồi đóng rồi mở lại (remount) → vẫn đúng 1 request cho cả phiên', async () => {
    client.api.drawLuans.mockResolvedValue(LUANS3)
    const a = mountSheet()
    await flushPromises()
    expect(client.api.drawLuans).toHaveBeenCalledTimes(1)
    a.unmount()
    wrapper = null
    const b = mountSheet()
    await flushPromises()
    expect(client.api.drawLuans).toHaveBeenCalledTimes(1)
    expect(b.findAll('[data-testid="luans-item"]')).toHaveLength(3)
  })

  it('2 sheet cùng draw_id mount đồng thời → inflight dedupe = 1 fetch', async () => {
    client.api.drawLuans.mockResolvedValue(LUANS3)
    const a = mountSheet()
    const b = mountSheet()
    await flushPromises()
    expect(client.api.drawLuans).toHaveBeenCalledTimes(1)
    expect(b.findAll('[data-testid="luans-item"]')).toHaveLength(3)
    a.unmount(); b.unmount()
    wrapper = null
  })
})

// ══ F4 — rỗng: 0 bài done → ẩn HOÀN TOÀN nút (không dòng «chưa hỏi gì») ═════
describe('RL-FE F4 — quẻ trống', () => {
  it('drawLuans trả data=[] → KHÔNG có luans-open trong DOM', async () => {
    client.api.drawLuans.mockResolvedValue({ data: [], meta: { count: 0 } })
    const { w } = await mountDetail()
    expect(w.find('[data-testid="luans-open"]').exists()).toBe(false)
    expect(w.text()).not.toMatch(/chưa hỏi/i)
  })
  it('drawLuans lỗi (404/network) → nút cũng ẩn (lọc mềm, không khối lỗi ở trang)', async () => {
    client.api.drawLuans.mockRejectedValue(new ApiError(404, 'NOT_FOUND', ''))
    const { w } = await mountDetail()
    await flushPromises()
    expect(w.find('[data-testid="luans-open"]').exists()).toBe(false)
  })
})

// ══ F3 một vế — CONTRACT #13 bằng client THẬT (khuôn apiclient.test.js: không
// mock client, chỉ giả fetch) + giờ tương đối edge 23:59→00:00 (luật nhà) ══════
describe('RL-FE contract + giờ', () => {
  it('#13 GET /api/draws/{id}/luans — path đúng, envelope {data,meta}', async () => {
    const real = await vi.importActual('../src/api/client.js')
    globalThis.fetch = vi.fn().mockResolvedValue({
      status: 200, ok: true, json: async () => LUANS3,
    })
    const r = await real.api.drawLuans(42)
    expect(globalThis.fetch.mock.calls[0][0]).toBe('/api/draws/42/luans')
    expect(r.data).toHaveLength(3)
    expect(r.meta.count).toBe(3)
  })

  it('formatLuanTime: hôm nay / hôm qua / DD/MM, 24h theo GIỜ MÁY KHÁCH', async () => {
    const { formatLuanTime } = await vi.importActual('../src/composables/useDrawLuans.js')
    // máy khách giả định UTC+7: dựng Date local trực tiếp, không qua chuỗi ISO
    const now = new Date(2026, 8, 3, 9, 12) // 03/09/2026 09:12 local
    expect(formatLuanTime(new Date(2026, 8, 3, 9, 5).toISOString(), now)).toBe('hôm nay · 09:05')
    expect(formatLuanTime(new Date(2026, 8, 2, 22, 4).toISOString(), now)).toBe('hôm qua · 22:04')
    expect(formatLuanTime(new Date(2026, 8, 1, 14, 6).toISOString(), now)).toBe('01/09 · 14:06')
    expect(formatLuanTime(null, now)).toBeNull()
    expect(formatLuanTime('không phải ngày', now)).toBeNull()
  })

  it('edge 23:59→00:00: bài 23:59 hôm trước = «hôm qua» khi bây giờ 00:00', async () => {
    const { formatLuanTime } = await vi.importActual('../src/composables/useDrawLuans.js')
    const now = new Date(2026, 8, 3, 0, 0) // 03/09 00:00 local
    expect(formatLuanTime(new Date(2026, 8, 2, 23, 59).toISOString(), now)).toBe('hôm qua · 23:59')
    expect(formatLuanTime(new Date(2026, 8, 3, 0, 0).toISOString(), now)).toBe('hôm nay · 00:00')
  })
})
