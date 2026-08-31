// MagicSequence.vue — FE-3XU PA1 "Một chạm · Xu quyết từng hào" (gate t_04394e77).
// BẤT BIẾN: reveal KHÔNG trước 1500ms (C-08) — lịch PA1 reveal 3060ms, floor vẫn kẹp.
// 6 hào vẽ DƯỚI→TRÊN tại drawAt (560+360i); xu bay cụm tại flyAt; hào động nháy + 動
// tại dynoAt=2560 TRƯỚC reveal; done emit tại revealAt; auto-push S3 thuộc DrawView.
// Mốc + wording lấy từ utils/timeline.js (nguồn: mockup-3xu.html playPA1 — AC2).
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MagicSequence from '../src/components/MagicSequence.vue'

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

const STATIC = { props: { durationMs: 1500, lines: [7, 8, 7, 7, 7, 7], symbol: '䷀', ten: 'Càn Vi Thiên' } }
const MOVING = { props: { durationMs: 1500, lines: [9, 8, 7, 7, 7, 6], symbol: '䷊', ten: 'Địa Thiên Thái' } }

const shown = (w) => w.findAll('[data-draw-line].is-shown').map((e) => e.attributes('data-position'))
const status = (w) => w.find('[data-testid="draw-status"]').text()

describe('MagicSequence PA1 (timeline mockup)', () => {
  it('beat 0: status "Vê nhẹ bó xu…", chưa có hào nào', async () => {
    const w = mount(MagicSequence, STATIC)
    expect(status(w)).toBe('Vê nhẹ bó xu…')
    expect(shown(w)).toEqual([])
  })

  it('xu bay: mỗi cụm 3 xu = 1 cú gieo, cụm i bay tại flyAt[i] (đếm lũy kế data-fly)', async () => {
    const w = mount(MagicSequence, MOVING)
    const flyCount = () => Number(w.find('[data-testid="magic-sequence"]').attributes('data-fly-count'))
    expect(flyCount()).toBe(0)
    await vi.advanceTimersByTimeAsync(260)
    expect(flyCount()).toBe(1)
    const c0 = w.findAll('[data-testid="coin-cluster"]')
    expect(c0.length).toBe(1)
    expect(c0[0].findAll('.coin').length).toBe(3) // mỗi hào đúng 1 cú gieo 3 xu
    expect(status(w)).toBe('Tung xu — hào 1')
    await vi.advanceTimersByTimeAsync(1740) // t=2000 = flyAt[4]
    expect(flyCount()).toBe(6) // đủ 6 cụm (0..5) — cụm cuối bay tại flyAt[5]=2000
  })

  it('6 hào tuần tự dưới→trên tại drawAt = 560+360i', async () => {
    const w = mount(MagicSequence, STATIC)
    await vi.advanceTimersByTimeAsync(559)
    expect(shown(w)).toEqual([])
    await vi.advanceTimersByTimeAsync(1) // 560
    expect(shown(w)).toEqual(['1'])
    await vi.advanceTimersByTimeAsync(900) // 1460 → hào 1..3 (1280) đủ, hào 4 chờ 1640
    expect(shown(w)).toEqual(['1', '2', '3'])
    await vi.advanceTimersByTimeAsync(180) // 1640
    expect(shown(w)).toEqual(['1', '2', '3', '4'])
    await vi.advanceTimersByTimeAsync(1000) // 2640 → hào 5 (2000) + 6 (2360)
    expect(shown(w)).toEqual(['1', '2', '3', '4', '5', '6'])
  })

  it('status từng beat bám wording mockup', async () => {
    const w = mount(MagicSequence, MOVING)
    await vi.advanceTimersByTimeAsync(560)
    expect(status(w)).toBe('Hào 1 · 6')
    await vi.advanceTimersByTimeAsync(1800) // 2360
    expect(status(w)).toBe('Hào 6 · 6 — đủ quẻ')
  })

  it('hào động: nháy son + dấu 動 tại dynoAt=2560, TRƯỚC reveal; status nhắc hào động', async () => {
    const w = mount(MagicSequence, MOVING)
    await vi.advanceTimersByTimeAsync(2360)
    expect(w.findAll('.dyno.show').length).toBe(0) // 2360 chưa tới dyno
    await vi.advanceTimersByTimeAsync(200) // 2560
    const dynos = w.findAll('.dyno.show')
    expect(dynos.length).toBe(2) // lines [9,...,6] → hào 1 + 6
    expect(w.find('[data-position="1"] .dyno').classes()).toContain('show')
    expect(w.find('[data-position="6"] .dyno').classes()).toContain('show')
    expect(dynos[0].text()).toBe('動')
    expect(status(w)).toBe('Hào 1·6 động — dấu 動')
    expect(w.emitted('done')).toBeFalsy() // dyno chưa reveal
  })

  it('quẻ tĩnh: không dyno, status giữ "đủ quẻ" tới reveal', async () => {
    const w = mount(MagicSequence, STATIC)
    await vi.advanceTimersByTimeAsync(2560)
    expect(w.findAll('.dyno').length).toBe(0)
    expect(status(w)).toBe('Hào 6 · 6 — đủ quẻ')
  })

  it('C-08: 1499ms chưa done/chưa reveal; reveal đúng 3060ms + tên quẻ + sub hào động', async () => {
    const w = mount(MagicSequence, MOVING)
    await vi.advanceTimersByTimeAsync(1499)
    expect(w.emitted('done')).toBeFalsy()
    expect(w.find('[data-testid="reveal-hexagram"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(1560) // t=3059
    expect(w.emitted('done')).toBeFalsy()
    await vi.advanceTimersByTimeAsync(1) // 3060
    expect(w.emitted('done')).toHaveLength(1)
    const r = w.find('[data-testid="reveal-hexagram"]')
    expect(r.exists()).toBe(true)
    expect(r.text()).toContain('Địa Thiên Thái')
    expect(status(w)).toBe('Địa Thiên Thái ䷊')
    // sub-line chỉ nhắc hào động — CẤM quẻ biến ra UI (gate t_04394e77)
    expect(r.find('[data-testid="reveal-sub"]').text()).toBe('hào 1·6 động')
    expect(w.html()).not.toContain('quẻ biến')
  })

  it('API chưa về lúc reveal → status nối mạch "Đang mở quẻ…", không hiện tên rỗng', async () => {
    const w = mount(MagicSequence, { props: { durationMs: 1500, lines: [7, 8, 7, 7, 7, 7] } })
    await vi.advanceTimersByTimeAsync(3060)
    expect(status(w)).toBe('Đang mở quẻ…')
    expect(w.find('[data-testid="reveal-hexagram"]').exists()).toBe(false)
    expect(w.emitted('done')).toHaveLength(1) // floor thời gian vẫn chạy — DrawView quyết định push
  })

  it('durationMs < 1500 vẫn kẹp floor C-08 (1499ms không done)', async () => {
    const w = mount(MagicSequence, { props: { durationMs: 500, lines: [7, 7, 7, 7, 7, 7], ten: 'X' } })
    await vi.advanceTimersByTimeAsync(1499)
    expect(w.emitted('done')).toBeFalsy()
    await vi.advanceTimersByTimeAsync(1561) // 3060
    expect(w.emitted('done')).toHaveLength(1)
  })

  it('prefers-reduced-motion: GIỮ mốc (done 3060, hào vẫn dưới→trên) — không cụm xu bay', async () => {
    window.matchMedia = vi.fn().mockImplementation((q) => ({
      matches: true, media: q, addEventListener: () => {}, removeEventListener: () => {},
    }))
    const w = mount(MagicSequence, MOVING)
    await vi.advanceTimersByTimeAsync(260)
    expect(w.findAll('[data-testid="coin-cluster"]').length).toBe(0) // không animation thừa
    await vi.advanceTimersByTimeAsync(300) // 560
    expect(shown(w)).toEqual(['1']) // mốc thời gian nguyên vẹn
    await vi.advanceTimersByTimeAsync(2500) // 3060
    expect(w.emitted('done')).toHaveLength(1)
    expect(w.findAll('.dyno.show').length).toBe(2) // thông tin hào động vẫn đủ
    delete window.matchMedia
  })
})
