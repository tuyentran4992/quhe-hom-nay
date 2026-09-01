// SPEC-LUAN-V2 §6(c) lane render-chính-thức (card t_dec9349a) — RED tests.
// Hợp nhất patch preview-only topicgate-marker-render.patch (t_c146e45a, chưa commit)
// + bản TopicGate QA-PASS (12f28e3 §7.4.4) THÀNH MỘT CHỖ: parser marker nằm ở
// utils/luanRender.js (logic ra util, component chỉ render — luật kiến trúc),
// TopicGate.vue chỉ dùng computed.
// Marker là HỢP ĐỒNG hiển thị với backend/app/Domain/PromptBuilder.php:105-107.
import { describe, it, expect } from 'vitest'
import { parseLuan, LUAN_HEADINGS } from '../src/utils/luanRender.js'

const FULL = [
  '[Hoàn cảnh]',
  'Quẻ chỉ ra hai người đang trái chiều.',
  '',
  '[Vì sao khuyên vậy]',
  'Hào 9 nói giữ vững thì không lỗi.',
  '',
  '[Việc nên làm cụ thể tuần này — tối đa 3 gạch đầu dòng]',
  '- Nói rõ mong muốn mỗi tối.',
  '*Chỉ mang tính tham khảo giải trí về văn hoá*',
].join('\n')

describe('§6c parseLuan — marker → heading, không lộ ngoặc vuông', () => {
  it('đủ 3 marker → 3 khối đúng thứ tự, heading sạch, thân không còn marker', () => {
    const blocks = parseLuan(FULL)
    expect(blocks.map((b) => b.heading)).toEqual(LUAN_HEADINGS)
    expect(blocks[0].text).toBe('Quẻ chỉ ra hai người đang trái chiều.')
    expect(blocks[2].text).toContain('- Nói rõ mong muốn mỗi tối.')
    expect(blocks[2].text).toContain('tham khảo giải trí')
    for (const b of blocks) expect(b.text).not.toMatch(/[\[\]]/)
  })

  it('marker kèm em-dash/extra text trên cùng dòng → strip cả cụm marker, giữ phần còn lại', () => {
    const blocks = parseLuan('[Hoàn cảnh] — bạn đang phân vân chuyện công việc.\ntiếp theo')
    expect(blocks).toHaveLength(1)
    expect(blocks[0].heading).toBe('Hoàn cảnh')
    expect(blocks[0].text).toBe('bạn đang phân vân chuyện công việc.\ntiếp theo')
  })

  it('model quên marker (bài trơn) → 1 khối heading rỗng, nguyên văn không mất chữ', () => {
    const plain = 'Bài luận không có marker gì cả,\nhai dòng.'
    const blocks = parseLuan(plain)
    expect(blocks).toHaveLength(1)
    expect(blocks[0].heading).toBe('')
    expect(blocks[0].text).toBe(plain)
  })

  it('nội dung rỗng/null → mảng rỗng (component không render gì)', () => {
    expect(parseLuan('')).toEqual([])
    expect(parseLuan(null)).toEqual([])
    expect(parseLuan('   \n  ')).toEqual([])
  })

  it('rác trước marker đầu tiên (model dẫn thừa) → KHÔNG mất chữ: thành khối heading rỗng đứng trước', () => {
    const blocks = parseLuan('Để ý thấy:\n\n[Hoàn cảnh]\nThân bài.')
    expect(blocks).toHaveLength(2)
    expect(blocks[0].heading).toBe('')
    expect(blocks[0].text).toContain('Để ý thấy:')
    expect(blocks[1].heading).toBe('Hoàn cảnh')
    expect(blocks[1].text).toBe('Thân bài.')
  })

  it('marker viết thường/thừa space vẫn nhận diện (biên model dao động)', () => {
    const blocks = parseLuan('[ hoàn cảnh ]\nabc')
    expect(blocks[0].heading).toBe('Hoàn cảnh')
    expect(blocks[0].text).toBe('abc')
  })
})

// BUG-V3-2 (QA card t_b8a95f0a, card fix t_127a3094): 2/6 bài THẬT (ai_jobs 50/51)
// model trả heading kèm prefix markdown "### [Hoàn cảnh]" / "## [Hoàn cảnh]" —
// regex cũ không khớp → cả bài thành 1 khối heading '' → người dùng thấy nguyên
// văn "### [Hoàn cảnh]" + ngoặc vuông. Hợp đồng §6c "marker KHÔNG lọt text" bắt
// FE dung hòa biến thể này; BE siết prompt song song (không được assume).
describe('BUG-V3-2 parseLuan — marker có prefix markdown ###', () => {
  it('DB_50 thật: 3 marker "### [X]" → 3 khối heading sạch, không dòng nào còn #', () => {
    const raw = [
      '### [Hoàn cảnh]',
      'Quẻ vẽ khung cảnh vướng.',
      '',
      '### [Vì sao khuyên vậy]',
      'Hào nhị nói cắn thịt mềm.',
      '',
      '### [Việc nên làm cụ thể tuần này]',
      '- **Chủ động bắt chuyện trong 3 ngày tới.**',
    ].join('\n')
    const blocks = parseLuan(raw)
    expect(blocks.map((b) => b.heading)).toEqual(LUAN_HEADINGS)
    for (const b of blocks) {
      expect(b.text).not.toMatch(/^#{1,4}\s/m)
      expect(b.text).not.toMatch(/[\[\]]/)
    }
    expect(blocks[0].text).toBe('Quẻ vẽ khung cảnh vướng.')
    expect(blocks[2].text).toContain('- **Chủ động bắt chuyện trong 3 ngày tới.**')
  })

  it('DB_51 thật: "## [X]" 2 dấu thăng + dòng trống sau heading → vẫn 3 khối', () => {
    const raw = [
      '## [Hoàn cảnh]',
      '',
      'Đứng trước dòng nước.',
      '## [Vì sao khuyên vậy]',
      'Sơ lục ướt đuôi.',
      '## [Việc nên làm cụ thể tuần này]',
      'Quan sát nhiều hơn.',
    ].join('\n')
    const blocks = parseLuan(raw)
    expect(blocks.map((b) => b.heading)).toEqual(LUAN_HEADINGS)
    expect(blocks[0].text).toBe('Đứng trước dòng nước.')
  })

  it('biên dao động: # đơn, #### 4 thăng, marker không ngoặc "### Hoàn cảnh —" vẫn nhận', () => {
    expect(parseLuan('# Hoàn cảnh\nabc')[0].heading).toBe('Hoàn cảnh')
    expect(parseLuan('#### [Việc nên làm cụ thể tuần này — tối đa 3 gạch]\nabc')[0].heading).toBe('Việc nên làm cụ thể tuần này')
    const b = parseLuan('### [Hoàn cảnh] — bạn phân vân.\ntiếp')
    expect(b[0].heading).toBe('Hoàn cảnh')
    expect(b[0].text).toBe('bạn phân vân.\ntiếp')
  })

  it('regression 100% ca cũ: marker sạch DB_52..55 (không #) vẫn ra 3 khối như V2', () => {
    const raw = ['[Hoàn cảnh]  ', 'thân 1', '', '[Vì sao khuyên vậy]  ', 'thân 2',
      '', '[Việc nên làm cụ thể tuần này – tối đa 3 gạch đầu dòng]  ', '- làm'].join('\n')
    const blocks = parseLuan(raw)
    expect(blocks.map((b) => b.heading)).toEqual(LUAN_HEADINGS)
  })

  it('# đứng đầu dòng thường (không phải marker) KHÔNG bị ăn mất chữ', () => {
    const blocks = parseLuan('#10 người hỏi hôm nay\nabc')
    expect(blocks).toHaveLength(1)
    expect(blocks[0].heading).toBe('')
    expect(blocks[0].text).toBe('#10 người hỏi hôm nay\nabc')
  })
})
