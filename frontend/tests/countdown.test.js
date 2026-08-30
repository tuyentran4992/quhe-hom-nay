// useCountdown — 04-ui §4: đếm ngược retry_after_seconds, nút disabled khi còn chạy.
import { describe, it, expect, vi, afterEach } from 'vitest'
import { useCountdown } from '../src/composables/useCountdown.js'

afterEach(() => vi.useRealTimers())

describe('useCountdown', () => {
  it('start(n) → remaining giảm theo giây, running=true; hết → running=false', async () => {
    vi.useFakeTimers()
    const c = useCountdown()
    c.start(3)
    expect(c.remaining.value).toBe(3)
    expect(c.running.value).toBe(true)
    await vi.advanceTimersByTimeAsync(1000)
    expect(c.remaining.value).toBe(2)
    await vi.advanceTimersByTimeAsync(2000)
    expect(c.remaining.value).toBe(0)
    expect(c.running.value).toBe(false)
    c.stop()
  })

  it('start lại giữa chừng reset số giây; stop dừng đồng hồ', async () => {
    vi.useFakeTimers()
    const c = useCountdown()
    c.start(90)
    await vi.advanceTimersByTimeAsync(1000)
    expect(c.remaining.value).toBe(89)
    c.start(10)
    expect(c.remaining.value).toBe(10)
    c.stop()
    await vi.advanceTimersByTimeAsync(5000)
    expect(c.remaining.value).toBe(10)
    expect(c.running.value).toBe(true) // remaining > 0; timer đã tắt (đứng yên)
  })

  it('formatted mm:ss cho nút cooldown', () => {
    const c = useCountdown()
    c.start(65)
    expect(c.formatted.value).toBe('01:05')
    c.stop()
    c.start(9)
    expect(c.formatted.value).toBe('00:09')
    c.stop()
  })
})
