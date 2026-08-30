// useHexagrams — cache module #2 GET /api/hexagrams/{id}: 1 request/quẻ, prime từ #3.
// FE-1: mọi màn đọc quẻ qua cache này vì draw §3.2 KHÔNG embed hexagram (03-api).
import { describe, it, expect, vi, beforeEach } from 'vitest'
import * as client from '../src/api/client.js'
import { useHexagrams, _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return { ...real, api: { hexagram: vi.fn() } }
})

const HX11 = { id: 11, han: '泰', ten: 'Địa Thiên Thái', symbol: '䷊', free_content: { congViec: 'a', tinhDuyen: 'b', taiLoc: 'c' } }

beforeEach(() => { vi.clearAllMocks(); _resetHexagramCacheForTests() })

describe('useHexagrams', () => {
  it('ensure 2 lần cùng id → api.hexagram chỉ gọi 1 lần, trả cùng object', async () => {
    client.api.hexagram.mockResolvedValue({ data: HX11 })
    const h = useHexagrams()
    const [a, b] = await Promise.all([h.ensure(11), h.ensure(11)])
    expect(client.api.hexagram).toHaveBeenCalledTimes(1)
    expect(client.api.hexagram).toHaveBeenCalledWith(11)
    expect(a.ten).toBe('Địa Thiên Thái')
    expect(b).toBe(a)
  })

  it('ensure id đã cache → không gọi API', async () => {
    client.api.hexagram.mockResolvedValue({ data: HX11 })
    const h = useHexagrams()
    await h.ensure(11)
    client.api.hexagram.mockClear()
    await h.ensure(11)
    expect(client.api.hexagram).not.toHaveBeenCalled()
    expect(h.get(11).symbol).toBe('䷊')
  })

  it('prime từ #3 → get() có ngay, ensure sau đó không fetch', async () => {
    const h = useHexagrams()
    h.prime(HX11)
    expect(h.get(11)).toBe(HX11)
    await h.ensure(11)
    expect(client.api.hexagram).not.toHaveBeenCalled()
  })

  it('fetch lỗi → không poison cache, gọi lại sẽ retry', async () => {
    client.api.hexagram.mockRejectedValueOnce(new client.ApiError(500, 'INTERNAL', 'x'))
    const h = useHexagrams()
    await expect(h.ensure(11)).rejects.toBeDefined()
    expect(h.get(11)).toBe(null)
    client.api.hexagram.mockResolvedValue({ data: HX11 })
    await h.ensure(11)
    expect(client.api.hexagram).toHaveBeenCalledTimes(2)
    expect(h.get(11).ten).toBe('Địa Thiên Thái')
  })

  it('get id chưa có → null (màn render loading, không crash)', () => {
    expect(useHexagrams().get(99)).toBe(null)
  })
})
