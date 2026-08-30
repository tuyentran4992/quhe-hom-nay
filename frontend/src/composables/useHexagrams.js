// useHexagrams — 03-api §2 cache module: #1/#3/#4 KHÔNG embed hexagram (draw §3.2 thuần),
// mọi màn cần symbol/ten/free_content phải tra #2 theo hexagram_id. Cache toàn module
// (≤64 quẻ, 1 request/quẻ cho cả phiên) + prime() từ #3 để S2→S3 zero-fetch.
// In-flight dedupe: 2 ensure cùng id lúc đang tải → dùng chung 1 promise.
import { api } from '../api/client.js'

// Map thường (không reactive): cache đọc theo kiểu imperative — màn hình gọi ensure()
// rồi mới render dữ liệu; object trả về phải GIỮ NGUYÊN reference với caller.
const cache = new Map() // hexagram_id → hexagram object §2
const inflight = new Map() // id → Promise

export function useHexagrams() {
  function get(id) {
    return cache.get(Number(id)) ?? null
  }
  function prime(hx) {
    if (hx && hx.id != null) cache.set(Number(hx.id), hx)
    return hx
  }
  async function ensure(id) {
    const key = Number(id)
    const hit = cache.get(key)
    if (hit) return hit
    if (inflight.has(key)) return inflight.get(key)
    const p = api
      .hexagram(key)
      .then((r) => {
        cache.set(key, r.data)
        inflight.delete(key)
        return r.data
      })
      .catch((e) => {
        inflight.delete(key) // không poison — lỗi rồi thì retry được
        throw e
      })
    inflight.set(key, p)
    return p
  }
  return { get, prime, ensure }
}

export function _resetHexagramCacheForTests() {
  cache.clear()
  inflight.clear()
}
