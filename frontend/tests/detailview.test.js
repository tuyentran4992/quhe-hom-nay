// DetailView S3 — shape THẬT: cache #3 (draw+tách hexagram, prime vào useHexagrams),
// deep-link /que/{drawId}: draw hôm nay → #2; draw quá khứ → resolve qua #4 history
// (contract không có GET /draws/{id}) rồi #2. 3 tab free_content, TopicGate theo tab.
// FE-3XU — vùng "Luận hôm nay" 04-ui §S3: 0 hào động → chỉ Đại ý; ≥1 → Đại ý + khối
// TỪ HÀO sơ→thượng (prime #3 zero-fetch · deep-link gọi #2b rồi lọc changing_lines).
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import DetailView from '../src/views/DetailView.vue'
import * as client from '../src/api/client.js'
import { useDevice } from '../src/composables/useDeviceApi.js'
import { _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { useHexagrams, _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'
import { useHaoTexts, _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { me: vi.fn(), today: vi.fn(), hexagram: vi.fn(), history: vi.fn(), haoTexts: vi.fn(), requestInterpretation: vi.fn(), aiJob: vi.fn() } }
})

const DRAW42 = { id: 42, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 7, 7, 7], changing_lines: [2], created_at: 't' }
const DRAW7 = { id: 7, hexagram_id: 3, drawn_date: '2026-08-29', lines_rolled: [8, 7, 7, 8, 9, 7], changing_lines: [5], created_at: 't' }
const HX11 = {
  id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', lines: [1, 1, 1, 0, 0, 0],
  keywords: ['thái', 'giao thái', 'hanh thông', 'quẻ tốt'],
  dai_ci: 'Trời xuống đất lên, giao nhau nên thông.',
  vv_nien: 'Năm của người biết chờ đúng lúc.',
  free_content: { congViec: 'việc-A', tinhDuyen: 'duyen-B', taiLoc: 'loc-C' },
  ban_goc: { quaTu: { han: '乾：元，亨，利，貞。', am: 'Kiền: nguyên, hanh, lợi, trinh.', nghia: 'nghia-G' }, haoTu: [{ vi: 1, hao: 'Sơ Cửu', han: 'h', am: 'a', nghia: 'n' }] },
}
const HX3 = { id: 3, han: '屯', ten: 'Thuỷ Sơn Truân', symbol: '䷂', lines: [0, 1, 0, 0, 0, 1], keywords: ['truân'], dai_ci: 'd', vv_nien: 'v', free_content: { congViec: 'cv3', tinhDuyen: 'td3', taiLoc: 'tl3' }, ban_goc: {} }

function mk() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/que/:drawId', name: 'detail', component: DetailView },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
    ],
  })
}

async function mountAt(path) {
  const r = mk()
  const w = mount({ template: '<RouterView />' }, { global: { plugins: [r] } })
  await r.push(path)
  await r.isReady()
  await flushPromises()
  await flushPromises() // FE-3XU: load() chờ song song #2 + #2b (thêm chuỗi await)
  return { r, w }
}

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  _resetHaoTextsForTests()
  document.body.innerHTML = ''
  client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: DRAW42 })
  client.api.hexagram.mockImplementation(async (id) => ({ data: id === 11 ? HX11 : HX3 }))
})

describe('DetailView từ cache S2 (draw hôm nay)', () => {
  it('đi từ S2 (đã prime #3) → KHÔNG gọi #2, render bảng hào + 3 tab free_content', async () => {
    useDevice().load() // #1 trả draw hôm nay; KHÔNG có today.hexagram trong shape thật
    useHexagrams().prime({ ...HX11 }) // S2 gọi prime khi #3 về → cache theo id quẻ
    const { w } = await mountAt('/que/42')
    // draw match today + hx 11 đã prime → không fetch #2 lần nữa
    expect(client.api.hexagram).not.toHaveBeenCalled()
    expect(w.find('[data-testid="detail-hexagram-name"]').text()).toContain('Địa Thiên Thái')
    expect(w.find('[data-testid="detail-free-slot"]').text()).toContain('việc-A')
    expect(w.find('[data-testid="detail-linechart"]').exists()).toBe(true)
  })

  it('refresh trực tiếp /que/42 (chưa prime) → tra #2 theo today_draw.hexagram_id=11', async () => {
    const { w } = await mountAt('/que/42')
    expect(client.api.hexagram).toHaveBeenCalledWith(11)
    expect(w.find('[data-testid="detail-changing-lines"]').text()).toContain('Hào 2 động')
    expect(w.find('[data-testid="detail-dai-ci"]').text()).toContain('giao nhau nên thông')
    expect(w.find('[data-testid="detail-vv-nien"]').text()).toContain('biết chờ')
    expect(w.find('[data-testid="detail-keywords"]').text()).toContain('thái')
  })

  it('đổi tab → free-slot đổi theo free_content (congViec→tinhDuyen→taiLoc)', async () => {
    const { w } = await mountAt('/que/42')
    // selector theo TEST-FIELDS.md FE-0: kebab-case
    await w.find('[data-testid="detail-tab-tinh-duyen"]').trigger('click')
    expect(w.find('[data-testid="detail-free-slot"]').text()).toContain('duyen-B')
    await w.find('[data-testid="detail-tab-tai-loc"]').trigger('click')
    expect(w.find('[data-testid="detail-free-slot"]').text()).toContain('loc-C')
  })

  it('TopicGate theo tab: congViec→xuat_hanh (chưa unlock → gate-locked + CTA S4)', async () => {
    const { w } = await mountAt('/que/42')
    const gate = w.find('[data-testid="topic-gate"]')
    expect(gate.attributes('data-topic')).toBe('xuat_hanh')
    expect(w.find('[data-testid="gate-locked"]').exists()).toBe(true)
    expect(w.find('[data-testid="gate-cta-paywall"]').exists()).toBe(true)
  })

  it('accordion Bản gốc render ban_goc.quaTu han/am/nghia từ API #2', async () => {
    const { w } = await mountAt('/que/42')
    await w.find('[data-testid="detail-original-toggle"]').trigger('click')
    expect(w.find('[data-testid="detail-original-body"]').text()).toContain('Kiền: nguyên, hanh, lợi, trinh.')
    expect(w.find('[data-testid="detail-original-body"]').text()).toContain('nghia-G')
  })
})

describe('DetailView deep-link quẻ quá khứ (idempotent, không có GET /draws/{id})', () => {
  it('/que/7 không phải quẻ hôm nay → resolve qua #4 history → #2 đúng hexagram_id, lines_rolled thật', async () => {
    client.api.history.mockResolvedValue({ data: [DRAW42, DRAW7], meta: { count: 2 } })
    const { w } = await mountAt('/que/7')
    expect(client.api.history).toHaveBeenCalled()
    expect(client.api.hexagram).toHaveBeenCalledWith(3)
    expect(w.find('[data-testid="detail-hexagram-name"]').text()).toContain('Thuỷ Sơn Truân')
    // hào động 5 phải hiện trên chart (lines_rolled từ draws, không phải hx.lines)
    expect(w.find('[data-testid="detail-changing-lines"]').text()).toContain('Hào 5 động')
  })

  it('/que/999 không tồn tại của device → detail-error + link về S1, không trắng', async () => {
    client.api.history.mockResolvedValue({ data: [DRAW42], meta: { count: 1 } })
    const { w } = await mountAt('/que/999')
    expect(w.find('[data-testid="detail-error"]').exists()).toBe(true)
    expect(w.find('[data-testid="detail-error"]').text()).toContain('Về trang chính')
  })

  it('mất mạng khi load → detail-error (FE không trắng màn — 04-ui §4)', async () => {
    client.api.me.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const { w } = await mountAt('/que/42')
    expect(w.find('[data-testid="detail-error"]').exists()).toBe(true)
  })
})

// ===== FE-3XU: vùng "Luận hôm nay" — luật hiển thị 04-ui §S3 =====
const HAO6 = [
  { vi: 1, hao: 'Sơ cửu', han: '初九：拔茅茹。', quoc_am: 'Sơ cửu: bạc mao nho.', nghia: 'Rút cỏ chung mầm.' },
  { vi: 2, hao: 'Lục nhị', han: '六二：包荒。', quoc_am: 'Lục nhị: bao hoang.', nghia: 'Bao dung chỗ hoang.' },
  { vi: 3, hao: 'Cửu tam', han: '九三：無平不陂。', quoc_am: 'Cửu tam: vô bình bất pha.', nghia: 'Không phẳng mãi.' },
  { vi: 4, hao: 'Lục tứ', han: '六四：顚飛。', quoc_am: 'Lục tứ: điên phi.', nghia: 'Lộn cánh rơi.' },
  { vi: 5, hao: 'Lục ngũ', han: '六五：帝乙歸妹。', quoc_am: 'Lục ngũ: đế ất quy muội.', nghia: 'Vua gả em.' },
  { vi: 6, hao: 'Thượng lục', han: '上六：城復于隍。', quoc_am: 'Thượng lục: thành phục vu hoàng.', nghia: 'Thành lở xuống hào.' },
]
const DRAW_STATIC = { id: 55, hexagram_id: 11, drawn_date: '2026-08-30', lines_rolled: [7, 8, 7, 8, 7, 8], changing_lines: [], created_at: 't' }

describe('DetailView vùng Luận hôm nay (FE-3XU, 04-ui §S3)', () => {
  it('refresh /que/42 (hào động 2, chưa prime) → gọi #2b đúng id, render khối Lục nhị + Đại ý', async () => {
    client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 11, hao: HAO6 } })
    const { w } = await mountAt('/que/42')
    expect(client.api.haoTexts).toHaveBeenCalledWith(11)
    const blocks = w.findAll('[data-testid="hao-dong-block"]')
    expect(blocks.length).toBe(1)
    expect(blocks[0].attributes('data-vi')).toBe('2')
    expect(blocks[0].text()).toContain('六二：包荒。')
    expect(blocks[0].text()).toContain('bao hoang')
    expect(blocks[0].text()).toContain('Bao dung chỗ hoang')
    // Đại ý quẻ gốc luôn hiện (1 khối)
    expect(w.find('[data-testid="luan-hom-nay"]').text()).toContain('giao nhau nên thông')
  })

  it('≥2 hào động (draw 7 changing [6,2] từ #4) → xếp sơ→thượng đúng thứ tự', async () => {
    client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 3, hao: HAO6 } })
    client.api.history.mockResolvedValue({ data: [{ ...DRAW7, changing_lines: [6, 2], lines_rolled: [8, 8, 7, 7, 9, 9] }], meta: { count: 1 } })
    const { w } = await mountAt('/que/7')
    const vis = w.findAll('[data-testid="hao-dong-block"]').map((b) => b.attributes('data-vi'))
    expect(vis).toEqual(['2', '6']) // sơ→thượng dù changing [6,2]
  })

  it('0 hào động → CHỈ Đại ý, không hao-dong-block, không "—null—"/khung trống', async () => {
    client.api.me.mockResolvedValue({ device_id: 'd', is_new_device: false, server_date_vn: '2026-08-30', entitlements: [], today_draw: DRAW_STATIC })
    const { w } = await mountAt('/que/55')
    expect(w.find('[data-testid="luan-dai-y"]').exists()).toBe(true)
    expect(w.find('[data-testid="luan-dai-y"]').text()).toContain('giao nhau nên thông')
    expect(w.findAll('[data-testid="hao-dong-block"]').length).toBe(0)
    expect(w.find('[data-testid="luan-hom-nay"]').text()).not.toMatch(/null|undefined/)
    // 0 hào động = hợp lệ → không xin #2b làm gì (không có hào nào để lấy từ)
    expect(client.api.haoTexts).not.toHaveBeenCalled()
  })

  it('đi từ S2 (đã prime #3) → KHÔNG gọi #2b, vẫn đủ khối từ hào', async () => {
    useHexagrams().prime({ ...HX11 })
    useHaoTexts().prime(11, [HAO6[1]]) // S2 prime từ data.hao_texts #3
    const { w } = await mountAt('/que/42')
    expect(client.api.haoTexts).not.toHaveBeenCalled()
    const blocks = w.findAll('[data-testid="hao-dong-block"]')
    expect(blocks.length).toBe(1)
    expect(blocks[0].text()).toContain('包荒')
  })

  it('#2b lỗi mạng → không crash: Đại ý vẫn hiện, vùng từ hào im (04-ui §4), text không lộ "quẻ biến"', async () => {
    client.api.haoTexts.mockRejectedValue(new client.ApiError(0, 'NETWORK', 'mạng lỗi', {}))
    const { w } = await mountAt('/que/42')
    expect(w.find('[data-testid="luan-dai-y"]').text()).toContain('giao nhau nên thông')
    expect(w.findAll('[data-testid="hao-dong-block"]').length).toBe(0)
    expect(w.text()).not.toMatch(/quẻ biến|symbol_bien/)
  })
})
