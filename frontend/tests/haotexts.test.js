// useHaoTexts + HaoDongBlock — SPEC-3XU (03-api §2b + 04-ui §S3 luật hiển thị):
// - nguồn #3: data.hao_texts (1 pt/hào động) prime từ S2 → S3 zero-fetch;
// - deep-link/quẻ cũ: gọi #2b GET /api/hexagrams/{id}/hao-texts (đủ 6 pt vi=1..6)
//   rồi LỌC theo changing_lines của draw;
// - 0 hào động → chỉ Đại ý, không khung trống/"—null—" (04-ui S3);
// - quẻ biến KHÔNG ra UI — dữ liệu biến không tồn tại trong contract FE.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { useHaoTexts, _resetHaoTextsForTests } from '../src/composables/useHaoTexts.js'
import HaoDongBlock from '../src/components/HaoDongBlock.vue'
import * as client from '../src/api/client.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { ...real.api, haoTexts: vi.fn() } }
})

const HAO6 = [
  { vi: 1, hao: 'Sơ cửu', han: '初九：拔茅茹，以其彙。征吉。', quoc_am: 'Sơ cửu: bạc mao nho, dĩ kỳ vị. Chinh cát.', nghia: 'Rút cỏ chung mầm, đi thì tốt.' },
  { vi: 2, hao: 'Lục nhị', han: '六二：包荒，用馮河。', quoc_am: 'Lục nhị: bao hoang, dụng bằng hà.', nghia: 'Bao dung chỗ hoang.' },
  { vi: 3, hao: 'Cửu tam', han: '九三：無平不陂。', quoc_am: 'Cửu tam: vô bình bất pha.', nghia: 'Không phẳng nào mãi không dốc.' },
  { vi: 4, hao: 'Lục tứ', han: '六四：顚飛。', quoc_am: 'Lục tứ: điên phi.', nghia: 'Lộn cánh rơi.' },
  { vi: 5, hao: 'Lục ngũ', han: '六五：帝乙歸妹。', quoc_am: 'Lục ngũ: đế ất quy muội.', nghia: 'Vua gả em gái.' },
  { vi: 6, hao: 'Thượng lục', han: '上六：城復于隍。', quoc_am: 'Thượng lục: thành phục vu hoàng.', nghia: 'Thành lở xuống hào.' },
]

beforeEach(() => {
  vi.clearAllMocks()
  _resetHaoTextsForTests()
})

describe('useHaoTexts', () => {
  it('prime từ #3 (chỉ hào động) → get lọc theo changing, giữ thứ tự sơ→thượng', () => {
    const h = useHaoTexts()
    h.prime(11, [HAO6[1], HAO6[5]]) // đúng shape #3: 2 pts vi=2,6
    expect(h.get(11, [2, 6]).map((t) => t.vi)).toEqual([2, 6])
    // caller đưa changing lộn xộn vẫn trả sơ→thượng
    expect(h.get(11, [6, 2]).map((t) => t.vi)).toEqual([2, 6])
    // changing rỗng → mảng rỗng (0 hào động = hợp lệ)
    expect(h.get(11, [])).toEqual([])
  })

  it('ensure từ #2b (đủ 6) → cache 1 lần, các lần sau không gọi lại API; lọc changing', async () => {
    client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 11, hao: HAO6 } })
    const h = useHaoTexts()
    const a = await h.ensure(11, [1, 5])
    expect(client.api.haoTexts).toHaveBeenCalledTimes(1)
    expect(client.api.haoTexts).toHaveBeenCalledWith(11)
    expect(a.map((t) => t.vi)).toEqual([1, 5])
    const b = await h.ensure(11, [2]) // cache hit → vẫn 1 call
    expect(client.api.haoTexts).toHaveBeenCalledTimes(1)
    expect(b.map((t) => t.vi)).toEqual([2])
  })

  it('#2b 404 NOT_FOUND → cache rỗng (S3 render Đại ý đơn, không crash — 04-ui §4)', async () => {
    client.api.haoTexts.mockRejectedValue(new client.ApiError(404, 'NOT_FOUND', '', {}))
    const h = useHaoTexts()
    await expect(h.ensure(99, [1])).resolves.toEqual([])
  })

  it('mất mạng → null + không poison cache (retry được)', async () => {
    client.api.haoTexts.mockRejectedValueOnce(new client.ApiError(0, 'NETWORK', '', {}))
    const h = useHaoTexts()
    await expect(h.ensure(11, [1])).resolves.toBeNull()
    client.api.haoTexts.mockResolvedValueOnce({ data: { hexagram_id: 11, hao: HAO6 } })
    const ok = await h.ensure(11, [1])
    expect(ok).toHaveLength(1)
    expect(client.api.haoTexts).toHaveBeenCalledTimes(2)
  })

  it('đảm bảo không có field quẻ biến trong dữ liệu render (gate t_04394e77)', async () => {
    client.api.haoTexts.mockResolvedValue({ data: { hexagram_id: 11, hao: HAO6 } })
    const out = await useHaoTexts().ensure(11, [1, 6])
    expect(JSON.stringify(out)).not.toMatch(/bien|biến|symbol_bien/i)
  })
})

describe('HaoDongBlock (props 1 phần tử hao_texts)', () => {
  it('render nhãn hao + han font han + quoc_am + nghia', () => {
    const w = mount(HaoDongBlock, { props: { text: HAO6[1] } })
    expect(w.attributes('data-testid')).toBe('hao-dong-block')
    expect(w.attributes('data-vi')).toBe('2')
    expect(w.find('[data-testid="hao-dong-label"]').text()).toBe('Lục nhị')
    expect(w.find('[data-testid="hao-dong-han"]').classes()).toContain('han')
    expect(w.find('[data-testid="hao-dong-han"]').text()).toContain('包荒')
    expect(w.find('[data-testid="hao-dong-quocam"]').text()).toContain('bao hoang')
    expect(w.find('[data-testid="hao-dong-nghia"]').text()).toContain('Bao dung')
  })
})
