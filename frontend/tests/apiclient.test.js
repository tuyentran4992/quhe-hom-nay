// api client — 03-api.md: cùng origin /api, envelope §0.3, đủ 11 endpoint nhóm FE dùng.
import { describe, it, expect, vi, afterEach } from 'vitest'
import { api, ApiError } from '../src/api/client.js'

function mockFetch(status, body) {
  return vi.fn().mockResolvedValue({
    status,
    ok: status >= 200 && status < 300,
    json: async () => body,
  })
}

afterEach(() => vi.restoreAllMocks())

describe('api client theo 03-api', () => {
  it('#1 GET /api/me — path đúng, credentials same-origin', async () => {
    globalThis.fetch = mockFetch(200, { device_id: 'x', today_draw: null, entitlements: [], server_date_vn: '2026-08-30', is_new_device: true })
    const r = await api.me()
    expect(globalThis.fetch).toHaveBeenCalledTimes(1)
    const [url, opt] = globalThis.fetch.mock.calls[0]
    expect(url).toBe('/api/me')
    expect(opt.credentials).toBe('same-origin')
    expect(r.server_date_vn).toBe('2026-08-30')
  })

  it('#3 POST /api/draws — JSON body {}', async () => {
    globalThis.fetch = mockFetch(201, { data: { draw: { id: 42 }, hexagram: { id: 11 }, already_drawn: false } })
    const r = await api.createDraw()
    const [url, opt] = globalThis.fetch.mock.calls[0]
    expect(url).toBe('/api/draws')
    expect(opt.method).toBe('POST')
    expect(JSON.parse(opt.body)).toEqual({})
    expect(r.data.draw.id).toBe(42)
  })

  it('#4 GET /api/draws/history?limit= — query passthrough', async () => {
    globalThis.fetch = mockFetch(200, { data: [], meta: { count: 0 } })
    await api.history(20)
    expect(globalThis.fetch.mock.calls[0][0]).toBe('/api/draws/history?limit=20')
  })

  it('#5 POST /api/ai/interpretations — body đúng field contract', async () => {
    globalThis.fetch = mockFetch(202, { data: { job_uuid: 'u', status: 'queued' } })
    await api.requestInterpretation({ draw_id: 42, topic: 'duyen', idempotency_key: 'k12345678' })
    const [, opt] = globalThis.fetch.mock.calls[0]
    expect(globalThis.fetch.mock.calls[0][0]).toBe('/api/ai/interpretations')
    expect(JSON.parse(opt.body)).toEqual({ draw_id: 42, topic: 'duyen', idempotency_key: 'k12345678' })
  })

  it('#7 POST /api/payments/create', async () => {
    globalThis.fetch = mockFetch(201, { data: { order_code: 1, status: 'pending' } })
    await api.createPayment({ kind: 'unlock', topic: 'duyen', amount_vnd: 29000, return_url: '/', idempotency_key: 'kkkkkkkk' })
    expect(globalThis.fetch.mock.calls[0][0]).toBe('/api/payments/create')
  })

  it('#9 GET status payment', async () => {
    globalThis.fetch = mockFetch(200, { data: { order_code: 1, status: 'paid' } })
    await api.paymentStatus(1)
    expect(globalThis.fetch.mock.calls[0][0]).toBe('/api/payments/1/status')
  })

  it('#10 GET /api/me/today', async () => {
    globalThis.fetch = mockFetch(200, { data: { today_draw: null, entitlements: [], server_date_vn: '2026-08-30' } })
    await api.today()
    expect(globalThis.fetch.mock.calls[0][0]).toBe('/api/me/today')
  })

  it('lỗi 409 DRAW_LIMIT_REACHED → ApiError.code + details.next_draw_at (envelope §0.3)', async () => {
    globalThis.fetch = mockFetch(409, { error: { code: 'DRAW_LIMIT_REACHED', message: 'Hôm nay bạn đã gieo quẻ rồi. Quay lại sau 0h.', details: { next_draw_at: '2026-08-31T17:00:00Z' } } })
    const e = await api.createDraw().catch((err) => err)
    expect(e).toBeInstanceOf(ApiError)
    expect(e.status).toBe(409)
    expect(e.code).toBe('DRAW_LIMIT_REACHED')
    expect(e.details.next_draw_at).toBe('2026-08-31T17:00:00Z')
  })

  it('lỗi 429 AI_COOLDOWN → retry_after_seconds nổi lên details', async () => {
    globalThis.fetch = mockFetch(429, { error: { code: 'AI_COOLDOWN', message: 'x', details: { retry_after_seconds: 57 } } })
    const e = await api.requestInterpretation({ draw_id: 1, topic: 'duyen', idempotency_key: 'aaaaaaaa' }).catch((err) => err)
    expect(e.code).toBe('AI_COOLDOWN')
    expect(e.details.retry_after_seconds).toBe(57)
  })

  it('mạng chết → ApiError status 0 code NETWORK (FE không trắng màn)', async () => {
    globalThis.fetch = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'))
    const e = await api.me().catch((err) => err)
    expect(e).toBeInstanceOf(ApiError)
    expect(e.status).toBe(0)
    expect(e.code).toBe('NETWORK')
  })
})
