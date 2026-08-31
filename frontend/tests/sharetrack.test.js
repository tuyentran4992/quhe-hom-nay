// F7-FE utils/track.js — TDD RED. 06-mkt-tracking §3 #11: POST /api/track
// {name, props} fire-and-forget, fail im lặng (không throw, không chờ).
// METRICS §1 V1–V4 TÊN ĐÚNG nguyên văn, KHÔNG prefix qhn_ (ADR-002 / F7-CONTRACT §1).
import { describe, it, expect, vi, afterEach } from 'vitest'
import { track, trackShareCard } from '../src/utils/track.js'

afterEach(() => vi.restoreAllMocks())

function lastBody(fetchMock) {
  const [, opt] = fetchMock.mock.calls[fetchMock.mock.calls.length - 1]
  return JSON.parse(opt.body)
}

describe('track()', () => {
  it('POST /api/track đúng envelope {name, props}, không credentials lạ, fire-and-forget', async () => {
    const f = vi.fn().mockResolvedValue({ ok: true, status: 204 })
    globalThis.fetch = f
    const p = track('share_card_open', { draw_id: 42 })
    expect(p).toBeUndefined() // không trả promise cho caller chờ
    await vi.waitFor(() => expect(f).toHaveBeenCalledTimes(1))
    const [url, opt] = f.mock.calls[0]
    expect(url).toBe('/api/track')
    expect(opt.method).toBe('POST')
    expect(opt.headers['Content-Type']).toBe('application/json')
    expect(lastBody(f)).toEqual({ name: 'share_card_open', props: { draw_id: 42 } })
  })

  it('mạng chết (fetch reject) → im lặng, không unhandled rejection', async () => {
    globalThis.fetch = vi.fn().mockRejectedValue(new Error('offline'))
    expect(() => track('share_card_open', { a: 1 })).not.toThrow()
    await new Promise((r) => setTimeout(r, 0))
  })

  it('BE trả 422 (name ngoài whitelist) → im lặng', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, status: 422 })
    track('not_a_real_event', {})
    await new Promise((r) => setTimeout(r, 0))
  })
})

describe('trackShareCard — V1..V4 đúng METRICS §1 (params bắt buộc)', () => {
  it('V1 open(draw_id, hexagram_id, has_dynamic_line)', async () => {
    const f = vi.fn().mockResolvedValue({ ok: true })
    globalThis.fetch = f
    trackShareCard.open({ draw_id: 42, hexagram_id: 11, has_dynamic_line: true })
    await vi.waitFor(() => expect(f).toHaveBeenCalledTimes(1))
    expect(lastBody(f)).toEqual({
      name: 'share_card_open',
      props: { draw_id: 42, hexagram_id: 11, has_dynamic_line: true },
    })
  })

  it('V2 created(draw_id, frame "9x16"|"1x1", render_ms)', async () => {
    const f = vi.fn().mockResolvedValue({ ok: true })
    globalThis.fetch = f
    trackShareCard.created({ draw_id: 42, frame: '9x16', render_ms: 120 })
    await vi.waitFor(() => expect(f).toHaveBeenCalledTimes(1))
    expect(lastBody(f)).toEqual({
      name: 'share_card_created',
      props: { draw_id: 42, frame: '9x16', render_ms: 120 },
    })
  })

  it('V3 error(draw_id, reason)', async () => {
    const f = vi.fn().mockResolvedValue({ ok: true })
    globalThis.fetch = f
    trackShareCard.error({ draw_id: 42, reason: 'canvas_exception' })
    await vi.waitFor(() => expect(f).toHaveBeenCalledTimes(1))
    expect(lastBody(f)).toEqual({
      name: 'share_card_error',
      props: { draw_id: 42, reason: 'canvas_exception' },
    })
  })

  it('V4 done(draw_id, method download|copy|native, token)', async () => {
    const f = vi.fn().mockResolvedValue({ ok: true })
    globalThis.fetch = f
    trackShareCard.done({ draw_id: 42, method: 'copy', token: 'Ab3dE9fGh1' })
    await vi.waitFor(() => expect(f).toHaveBeenCalledTimes(1))
    expect(lastBody(f)).toEqual({
      name: 'share_card_done',
      props: { draw_id: 42, method: 'copy', token: 'Ab3dE9fGh1' },
    })
  })

  it('TÊN 4 event khớp từng ký tự METRICS, không prefix', async () => {
    const f = vi.fn().mockResolvedValue({ ok: true })
    globalThis.fetch = f
    trackShareCard.open({ draw_id: 1, hexagram_id: 1, has_dynamic_line: false })
    trackShareCard.created({ draw_id: 1, frame: '1x1', render_ms: 5 })
    trackShareCard.error({ draw_id: 1, reason: 'font_timeout' })
    trackShareCard.done({ draw_id: 1, method: 'download', token: 't' })
    await vi.waitFor(() => expect(f).toHaveBeenCalledTimes(4))
    expect(f.mock.calls.map((c) => JSON.parse(c[1].body).name)).toEqual([
      'share_card_open',
      'share_card_created',
      'share_card_error',
      'share_card_done',
    ])
    expect(f.mock.calls.some((c) => /qhn_/.test(c[1].body))).toBe(false)
  })
})
