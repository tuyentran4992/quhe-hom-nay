// LineChart.vue — 04-ui §3: vẽ 6 hào, props lines+changing.
// Thứ tự DOM TRÊN→DƯỚI = hào 6→1 (theo TESTIDS.md #5 mockup S1).
// BUG-1 (t_cd2a7be6): span hào âm width:44% trong row flex auto-width → 0px trên
// browser thật. Hợp đồng layout: MỌI row có .ln-bar width class cố định (definite),
// segment KHÔNG bao giờ mang % width mà parent không definite; marker ngoài bar.
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LineChart from '../src/components/LineChart.vue'
import compSrc from '../src/components/LineChart.vue?raw'

describe('LineChart', () => {
  it('render đúng 6 hào, DOM order = hào 6 xuống hào 1', () => {
    // lines_rolled dưới→trên: [7,8,7,7,7,7] → DOM top phải là phần tử cuối mảng (hào 6)
    const w = mount(LineChart, { props: { lines: [7, 8, 7, 7, 7, 7] } })
    const els = w.findAll('[data-line]')
    expect(els).toHaveLength(6)
    expect(els.map((e) => e.attributes('data-line'))).toEqual(['1', '1', '1', '1', '0', '1'])
    expect(els.map((e) => e.attributes('data-position'))).toEqual(['6', '5', '4', '3', '2', '1'])
  })

  it('dương (7/9) liền — class yang; âm (6/8) đứt — class yin', () => {
    const w = mount(LineChart, { props: { lines: [8, 7, 6, 9, 7, 8] } })
    const cls = w.findAll('[data-line]').map((e) => e.classes().join(' '))
    // vị trí 1 = 8 (âm) ... DOM cuối cùng là hào 1
    expect(cls[5]).toContain('ln--yin')
    expect(cls[4]).toContain('ln--yang')
    expect(cls[3]).toContain('ln--yin')
    expect(cls[2]).toContain('ln--yang')
  })

  it('hào động (6/9) có dot + nhãn "động", không dùng chữ cấm', () => {
    const w = mount(LineChart, { props: { lines: [7, 9, 7, 7, 7, 7], changing: [2] } })
    const mov = w.findAll('.ln--mov')
    expect(mov).toHaveLength(1)
    expect(mov[0].attributes('data-position')).toBe('2')
    expect(w.text()).toContain('động')
  })

  it('changing rỗng → không có nhãn động', () => {
    const w = mount(LineChart, { props: { lines: [7, 7, 7, 7, 7, 7], changing: [] } })
    expect(w.findAll('.ln--mov')).toHaveLength(0)
    expect(w.text()).not.toContain('động')
  })

  // ── BUG-1 fix contract (t_c09526c3) — layout không sập trên browser thật ──
  // Hợp đồng: .ln-bar = width class DEFINITE theo size + shrink-0; segment âm tính
  // bằng flex:1 1 0% (basis 0, chia trong bar definite) — CẤM width:% trên cha auto.
  const SIZE_CLASS = { sm: 'w-16', md: 'w-24', lg: 'w-40' }
  const FLEX_SEG = /^flex: 1 1 0%;?$/ // jsdom normalize có/chấm phẩy cuối

  it('mọi row có đúng 1 .ln-bar mang width class CỐ ĐỊNH theo size + shrink-0', () => {
    for (const size of ['sm', 'md', 'lg']) {
      const w = mount(LineChart, { props: { lines: [6, 8, 7, 8, 7, 7], changing: [1], size } })
      const rows = w.findAll('[data-line]')
      expect(rows).toHaveLength(6)
      for (const r of rows) {
        const bar = r.find('.ln-bar')
        expect(bar.exists()).toBe(true)
        expect(bar.classes()).toContain(SIZE_CLASS[size])
        expect(bar.classes()).toContain('shrink-0') // không bị aside chèn ép
      }
    }
  })

  it('hàng âm: ĐÚNG 2 span bamboo trong bar, flex basis 0 — hết cảnh width:44% quy về 0px', () => {
    const w = mount(LineChart, { props: { lines: [6, 8, 7, 8, 7, 7], changing: [], size: 'lg' } })
    const yinRows = w.findAll('.ln--yin')
    expect(yinRows).toHaveLength(3)
    for (const row of yinRows) {
      const segs = row.findAll('.ln-bar > .ln-seg')
      expect(segs).toHaveLength(2)
      for (const s of segs) {
        expect(s.classes().join(' ')).toContain('bg-bamboo')
        expect(s.attributes('style')).toMatch(FLEX_SEG)
      }
    }
  })

  it('hàng dương: 1 span cinnabar phủ trọn bar (basis 0 cùng công thức)', () => {
    const w = mount(LineChart, { props: { lines: [7, 7, 7, 7, 7, 7], changing: [], size: 'lg' } })
    for (const row of w.findAll('.ln--yang')) {
      const segs = row.findAll('.ln-bar > .ln-seg')
      expect(segs).toHaveLength(1)
      expect(segs[0].classes().join(' ')).toContain('bg-cinnabar')
      expect(segs[0].attributes('style')).toMatch(FLEX_SEG)
    }
  })

  it('marker động (dot + nhãn) nằm NGOÀI .ln-bar, trong aside width definite — dot không bị ép 0.84px', () => {
    const w = mount(LineChart, { props: { lines: [6, 8, 7, 8, 7, 7], changing: [1], size: 'lg' } })
    const mov = w.find('.ln--mov')
    expect(mov.exists()).toBe(true)
    expect(mov.find('.ln-bar .dot').exists()).toBe(false) // dot KHÔNG ở trong bar
    const aside = mov.find('.ln-aside')
    expect(aside.exists()).toBe(true)
    expect(aside.classes()).toContain('w-20') // definite, mọi row cùng chừa chỗ → bar thẳng cột
    expect(aside.find('.dot').exists()).toBe(true)
    expect(aside.text()).toContain('động')
    // quẻ 53: hào 1 rolled 6 = ÂM đổi → row động là yin + ln--mov
    expect(mov.classes()).toContain('ln--yin')
    expect(mov.classes()).toContain('ln--mov')
    // dot không bị shrink: lớp riêng của nó phải là flex-none/shrink-0
    expect(mov.find('.dot').classes().join(' ')).toMatch(/shrink-0|flex-none/)
  })

  it('mọi row đều có .ln-aside (kể cả không động) để 6 bar thẳng hàng tuyệt đối', () => {
    const w = mount(LineChart, { props: { lines: [6, 8, 7, 8, 7, 7], changing: [1], size: 'lg' } })
    expect(w.findAll('.ln-aside')).toHaveLength(6)
    // chỉ row động mới có dot + chữ
    expect(w.findAll('.dot')).toHaveLength(1)
    expect(w.findAll('[data-line]').filter((r) => r.text().includes('động'))).toHaveLength(1)
  })

  it('hào động có dấu nhận diện vượt chấm gold: outline cinnabar trên bar (token, không màu mới)', () => {
    // CSS .ln--mov dùng rgb(cinnabar) = 179 58 43 → tương phản 4.76:1 > 3:1 trên paper2
    expect(compSrc).toMatch(/ln--mov[\s\S]*?179\s+58\s+43/)
    expect(compSrc).toMatch(/outline/)
    // không chế màu mới: mọi hex trong component nằm trong token 04-ui §1
    const TOKEN_HEXES = ['1e1b18', 'f7f2e7', 'efe6d3', 'b33a2b', 'a8802a', '3e5c48', '5c554a']
    for (const m of compSrc.matchAll(/#([0-9a-fA-F]{6})\b/g)) {
      expect(TOKEN_HEXES).toContain(m[1].toLowerCase())
    }
  })

  it('quẻ 53 của boss: top→bottom = DƯƠNG, DƯƠNG, ÂM, DƯƠNG, ÂM, ÂM+động', () => {
    const w = mount(LineChart, { props: { lines: [6, 8, 7, 8, 7, 7], changing: [1], size: 'lg' } })
    const rows = w.findAll('[data-line]')
    const kinds = rows.map((r) => (r.classes().includes('ln--yin') ? 'yin' : 'yang'))
    expect(kinds).toEqual(['yang', 'yang', 'yin', 'yang', 'yin', 'yin'])
    expect(rows[5].classes()).toContain('ln--mov')
  })
})
