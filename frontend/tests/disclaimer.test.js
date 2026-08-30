// DisclaimerBar — wording BẮT BUỘC từng chữ (04-ui §2.S1 + §5), có mặt mọi màn.
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DisclaimerBar from '../src/components/DisclaimerBar.vue'

describe('DisclaimerBar', () => {
  it('đúng wording nguyên văn + testid override được (home-disclaimer ở S1)', () => {
    const w = mount(DisclaimerBar)
    const el = w.find('[data-testid="disclaimer-bar"]')
    expect(el.exists()).toBe(true)
    expect(el.text().trim()).toBe(
      'Sản phẩm giải trí, tham khảo văn hoá — không phải nghiên cứu hay tư vấn số mệnh.'
    )
    const h = mount(DisclaimerBar, { props: { testid: 'home-disclaimer' } })
    expect(h.find('[data-testid="home-disclaimer"]').exists()).toBe(true)
  })
})
