// LineChart.vue — 04-ui §3: vẽ 6 hào, props lines+changing.
// Thứ tự DOM TRÊN→DƯỚI = hào 6→1 (theo TESTIDS.md #5 mockup S1).
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LineChart from '../src/components/LineChart.vue'

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
})
