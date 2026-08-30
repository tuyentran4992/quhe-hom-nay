// MagicSequence.vue — 04-ui §2.S2 BẤT BIẾN UX: reveal KHÔNG trước 1.5s (C-08),
// 6 hào vẽ lần lượt DƯỚI lên, mỗi hào ~250ms; props durationMs=1500.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MagicSequence from '../src/components/MagicSequence.vue'

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('MagicSequence (C-08)', () => {
  it('chưa đủ durationMs → chưa emit done, chưa reveal quẻ', async () => {
    const w = mount(MagicSequence, {
      props: { durationMs: 1500, lines: [7, 7, 7, 7, 7, 7], symbol: '䷀', ten: 'Càn Vi Thiên' },
    })
    await vi.advanceTimersByTimeAsync(1499)
    expect(w.emitted('done')).toBeFalsy()
    expect(w.find('[data-testid="reveal-hexagram"]').exists()).toBe(false)
  })

  it('đúng durationMs → reveal + emit done', async () => {
    const w = mount(MagicSequence, {
      props: { durationMs: 1500, lines: [7, 8, 7, 7, 7, 7], symbol: '䷊', ten: 'Địa Thiên Thái' },
    })
    await vi.advanceTimersByTimeAsync(1500)
    expect(w.emitted('done')).toHaveLength(1)
    const r = w.find('[data-testid="reveal-hexagram"]')
    expect(r.exists()).toBe(true)
    expect(r.text()).toContain('Địa Thiên Thái')
  })

  it('6 hào tuần tự dưới→trên: mỗi hào hiện ở mốc (i)*250ms', async () => {
    const w = mount(MagicSequence, { props: { durationMs: 1500, lines: [7, 7, 7, 7, 7, 7] } })
    const shown = () => w.findAll('[data-draw-line].is-shown').map((e) => e.attributes('data-position'))
    expect(shown()).toEqual([])
    await vi.advanceTimersByTimeAsync(250)
    expect(shown()).toEqual(['1']) // hào 1 (dưới) trước, mốc i*250ms
    await vi.advanceTimersByTimeAsync(999) // t=1249 → mới tới hào 4 (1000ms)
    expect(shown()).toEqual(['1', '2', '3', '4'])
    await vi.advanceTimersByTimeAsync(251) // t=1500 → đủ 6 hào
    expect(shown()).toEqual(['1', '2', '3', '4', '5', '6'])
  })

  it('durationMs nhỏ hơn 1500 vẫn bị kẹp tối thiểu 1500 (bất biến)', async () => {
    const w = mount(MagicSequence, { props: { durationMs: 500, lines: [7, 7, 7, 7, 7, 7] } })
    await vi.advanceTimersByTimeAsync(1499)
    expect(w.emitted('done')).toBeFalsy()
    await vi.advanceTimersByTimeAsync(1)
    expect(w.emitted('done')).toHaveLength(1)
  })
})
