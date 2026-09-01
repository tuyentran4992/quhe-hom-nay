// [UI-POLISH t_fc6387df] DetailView — trục VISUAL theo SOUL (finish-gate):
// 1) CTA "Lễ tùy tâm ủng hộ" = phần tử NỔI BẬT NHẤT hàng hành động (btn-cinnabar
//    + hover/focus-visible/disabled qua class nền), testid donate-cta-open giữ nguyên.
// 2) HAI nút kia (Chia sẻ thẻ quẻ / Xin luận sâu) nhẹ hơn MỘT BẠC: share = outline;
//    gate-ask chỉ bị giáng cấp khi donate THỰC SỰ hiện (has-donate-cta) — không
//    làm yếu CTA 29k ở trạng thái thường (paywall logic giữ nguyên).
// 3) Chip trạng thái thống nhất 1 kiểu thẻ: "Chỉ mục N" + changing-lines + "Luận sâu
//    miễn phí hôm nay" — cùng class `chip-status`; detail-changing-lines giữ testid+text.
// 4) Nhịp đọc: tên quẻ serif cỡ h1 (token có sẵn), label "Từ hào" tách khối.
// TDD RED — viết trước khi code.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DetailView from '../src/views/DetailView.vue'
import * as client from '../src/api/client.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: {
      me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), history: vi.fn(), haoTexts: vi.fn(),
      requestInterpretation: vi.fn(), aiJob: vi.fn(), track: vi.fn(),
    },
  }
})

const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái'], dai_ci: 'Trời xuống đất lên, giao nhau nên thông.', vv_nien: 'v',
  free_content: { congViec: 'cv', tinhDuyen: 'td', taiLoc: 'tl' }, ban_goc: {},
}
const HAO2 = [{ vi: 2, hao: 'Lục nhị', han: '六二：包荒。', quoc_am: 'Lục nhị: bao hoang.', nghia: 'Bao dung chỗ hoang.' }]
const me = (over = {}) => ({
  device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30',
  entitlements: [], today_draw: DRAW42, free_deep: false, ...over,
})

function mkRoutes() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: DetailView },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
      { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
    ],
  })
}
const wrappers = []
afterEach(() => { wrappers.splice(0).forEach((w) => w.unmount()) })

async function mountView(over = {}) {
  client.api.me.mockResolvedValue(me(over))
  const r = mkRoutes()
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
  wrappers.push(w)
  await r.push('/que/42')
  await r.isReady()
  await flushPromises()
  await flushPromises()
  return w
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  document.body.innerHTML = ''
  client.api.hexagram.mockResolvedValue({ data: HX11 })
  client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 11, hao: HAO2 } })
  client.api.track.mockResolvedValue(null)
})

describe('UI-POLISH 1 — phân cấp nút hàng hành động', () => {
  it('donate-cta-open dùng btn-cinnabar (ấn ngo) + hover/focus-visible/disabled — nút nổi bật nhất', async () => {
    const w = await mountView({ free_deep: true })
    const btn = w.find('[data-testid="donate-cta-open"]')
    const cls = btn.attributes('class')
    expect(cls).toContain('btn-cinnabar')
    expect(cls).toMatch(/hover:/)
    expect(cls).toMatch(/focus-visible:/)
    // btn-cinnabar nền đã có disabled:opacity-50 disabled:cursor-not-allowed (styles.css)
    expect(btn.text()).toContain('Lễ tùy tâm ủng hộ')
    expect(btn.attributes('type')).toBe('button')
  })

  it('share-card-open nhẹ hơn một bậc: outline (class `btn-outline` border-inset, KHÔNG nền độn), không giống donate', async () => {
    const w = await mountView({ free_deep: true })
    const share = w.find('[data-testid="share-card-open"]')
    expect(share.attributes('class')).toContain('btn-outline')
    expect(share.attributes('class')).not.toContain('btn-cinnabar')
    expect(share.attributes('class')).not.toMatch(/(^| )bg-paper2( |$)/)
  })

  it('donate hiện → row mang class has-donate-cta (hợp đồng giáng cấp gate-ask xuống outline qua scoped CSS DetailView)', async () => {
    const w = await mountView({ free_deep: true })
    const row = w.find('[data-testid="detail-actions"]')
    expect(row.exists()).toBe(true)
    expect(row.attributes('class')).toContain('has-donate-cta')
    expect(row.find('[data-testid="donate-cta-open"]').exists()).toBe(true)
    expect(row.find('[data-testid="share-card-open"]').exists()).toBe(true)
    expect(row.find('[data-testid="topic-gate"]').exists()).toBe(true)
  })

  it('donate ẨN (free_deep false) → row KHÔNG mang has-donate-cta: CTA trả phí giữ nguyên trọng lượng', async () => {
    const w = await mountView({ free_deep: false, entitlements: [] })
    const row = w.find('[data-testid="detail-actions"]')
    expect(row.attributes('class')).not.toContain('has-donate-cta')
    expect(w.find('[data-testid="donate-cta-open"]').exists()).toBe(false)
  })
})

describe('UI-POLISH 3 — chip trạng thái thống nhất 1 kiểu', () => {
  it('3 chip cùng class nền `chip-status`: chỉ mục + hào động + miễn phí (freeDeep)', async () => {
    const w = await mountView({ free_deep: true })
    const idx = w.find('[data-testid="detail-chip-index"]')
    const chg = w.find('[data-testid="detail-changing-lines"]')
    const free = w.find('[data-testid="detail-chip-free"]')
    expect(idx.text()).toContain('Chỉ mục 11')
    expect(chg.text()).toContain('Hào 2 động') // testid + text cũ GIỮ NGUYÊN
    expect(free.text()).toMatch(/miễn phí/i)
    for (const c of [idx, chg, free]) expect(c.attributes('class')).toContain('chip-status')
  })

  it('freeDeep false → KHÔNG chip miễn phí; các chip còn vẫn 1 kiểu', async () => {
    const w = await mountView({ free_deep: false })
    expect(w.find('[data-testid="detail-chip-free"]').exists()).toBe(false)
    expect(w.find('[data-testid="detail-chip-index"]').attributes('class')).toContain('chip-status')
    expect(w.find('[data-testid="detail-changing-lines"]').attributes('class')).toContain('chip-status')
  })

  it('0 hào động → chip changing-lines biến mất (changingLabel rỗng), chip chỉ mục vẫn hiện', async () => {
    client.api.me.mockResolvedValue({ ...me(), today_draw: { ...DRAW42, id: 55, changing_lines: [], lines_rolled: [7, 8, 7, 8, 7, 8] } })
    const r = mkRoutes()
    const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
    wrappers.push(w)
    await r.push('/que/55')
    await r.isReady()
    await flushPromises()
    await flushPromises()
    expect(w.find('[data-testid="detail-changing-lines"]').exists()).toBe(false)
    expect(w.find('[data-testid="detail-chip-index"]').exists()).toBe(true)
  })
})

describe('UI-POLISH 2 — nhịp đọc', () => {
  it('tên quẻ là display serif cỡ h1 (token han + text-h1), không còn cỡ text-h2 lẫn chip', async () => {
    const w = await mountView()
    const h1 = w.find('[data-testid="detail-hexagram-name"]')
    expect(h1.attributes('class')).toContain('han')
    expect(h1.attributes('class')).toContain('text-h1')
    expect(h1.text()).toContain('Địa Thiên Thái')
    expect(h1.text()).toContain('泰')
  })

  it('có ≥1 hào động → label "Từ hào" (kicker) tách khỏi Đại ý; 0 hào động → không label', async () => {
    const w = await mountView()
    const label = w.find('[data-testid="luan-hao-label"]')
    expect(label.exists()).toBe(true)
    expect(label.text()).toContain('Từ hào')
    expect(label.attributes('class')).toContain('chip-kicker')
    // Đại ý nằm TRƯỚC label "Từ hào" trong DOM (nhịp tiểu dẫn → đại ý → kết)
    const html = w.html()
    expect(html.indexOf('data-testid="luan-dai-y"')).toBeLessThan(html.indexOf('data-testid="luan-hao-label"'))
  })
})

describe('UI-POLISH 4 — chống thoái hóa hợp đồng cũ', () => {
  it('testid chủ lực giữ nguyên: detail-free-slot, detail-tabs, topic-gate, luan-hom-nay, hao-dong-block', async () => {
    const w = await mountView({ free_deep: true })
    for (const t of ['detail-tabs', 'detail-free-slot', 'topic-gate', 'luan-hom-nay', 'luan-dai-y', 'hao-dong-block', 'detail-linechart'])
      expect(w.find(`[data-testid="${t}"]`).exists(), t).toBe(true)
  })

  it('đổi tab vẫn đổi free-slot + donate click vẫn push paywall mode=donate (flow không đổi)', async () => {
    const r = mkRoutes()
    client.api.me.mockResolvedValue(me({ free_deep: true }))
    const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
    wrappers.push(w)
    await r.push('/que/42')
    await r.isReady()
    await flushPromises()
    await flushPromises()
    await w.find('[data-testid="detail-tab-tinh-duyen"]').trigger('click')
    expect(w.find('[data-testid="detail-free-slot"]').text()).toContain('td')
    await w.find('[data-testid="donate-cta-open"]').trigger('click')
    await flushPromises()
    expect(r.currentRoute.value.query.mode).toBe('donate')
  })
})
