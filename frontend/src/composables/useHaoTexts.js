// useHaoTexts — SPEC-3XU (03-api §2b + 04-ui §S3). Cache module như useHexagrams:
// #3 embed sẵn data.hao_texts (chỉ hào động) → prime từ S2 = S3 zero-fetch;
// deep-link/quẻ cũ gọi #2b (đủ 6, vi=1..6) rồi LỌC theo changing_lines của draw.
// Luật hiển thị: ≥1 hào động → Đại ý + từng khối TỪ HÀO xếp sơ→thượng; 0 hào động là
// trạng thái hợp lệ → trả [] (FE render Đại ý đơn, không khung trống). Lỗi #2b
// (404/mạng) → [] / null, KHÔNG poison cache — 04-ui §4: FE không trắng màn.
import { api, ApiError } from '../api/client.js'

const cache = new Map() // hexagram_id → mảng hao_texts đầy đủ đã biết (6 hoặc chỉ động)
const inflight = new Map()

function filterByChanging(all, changing) {
  const set = new Set((changing || []).map(Number))
  return (all || []).filter((t) => set.has(Number(t.vi))).slice().sort((a, b) => a.vi - b.vi)
}

export function useHaoTexts() {
  function get(id, changing) {
    return filterByChanging(cache.get(Number(id)), changing)
  }
  function prime(id, haoTexts) {
    if (id != null && Array.isArray(haoTexts)) cache.set(Number(id), haoTexts)
    return haoTexts
  }
  /** Mảng từ hào của các hào động (sơ→thượng). 404 → [] (quẻ chưa seed = hợp lệ).
   *  Lỗi khác (mạng/500) → null để caller phân biệt "chưa tải được" vs "không có". */
  async function ensure(id, changing) {
    const key = Number(id)
    const hit = cache.get(key)
    if (hit) return filterByChanging(hit, changing)
    if (!inflight.has(key)) {
      // Promise.resolve().then: mọi lỗi ĐỒNG BỘ (api chưa mock, client ném sync) cũng
      // đi vào .catch — S3 không bao giờ trắng vì vùng từ hào (04-ui §4).
      inflight.set(
        key,
        Promise.resolve()
          .then(() => api.haoTexts(key))
          .then((r) => {
            const all = r.data?.hao || []
            cache.set(key, all)
            inflight.delete(key)
            return all
          })
          .catch((e) => {
            inflight.delete(key) // không poison — retry được (04-ui §4)
            if (e instanceof ApiError && e.status === 404) {
              cache.set(key, []) // 404 = id ngoài 1..64/chưa seed — ổn định, cache rỗng
              return []
            }
            return null
          }),
      )
    }
    const all = await inflight.get(key)
    return all == null ? null : filterByChanging(all, changing)
  }
  return { get, prime, ensure }
}

export function _resetHaoTextsForTests() {
  cache.clear()
  inflight.clear()
}
