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
