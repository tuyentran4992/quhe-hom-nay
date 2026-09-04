// useDrawLuans — [RL-FE t_47c88de0] CHỦ DUY NHẤT của GET #13 /api/draws/{id}/luans.
// Luật race §D nhà: một nguồn dữ liệu fetch ở ĐÚNG 1 nơi — DetailView chỉ cần SỐ N
// cho nút, AskedLuansSheet cần danh sách; cả hai đọc qua đây, không ai tự fetch.
// Cache Map draw_id→rows trong phiên (mở sheet lần 2 = 0 re-fetch, body card D3) +
// inflight dedupe (2 caller song song / remount dồn → 1 request) — cùng khuôn
// useHexagrams đã sống. Lỗi KHÔNG poison cache: lần sau gọi lại được (retry).
import { api } from '../api/client.js'

const cache = new Map() // draw_id → rows[] (data đã sort sẵn bởi BE: mới nhất đầu)
const inflight = new Map() // draw_id → Promise

export function useDrawLuans() {
  function getCached(drawId) {
    return cache.get(Number(drawId)) ?? null
  }
  function ensure(drawId) {
    const key = Number(drawId)
    const hit = cache.get(key)
    if (hit) return Promise.resolve(hit)
    if (inflight.has(key)) return inflight.get(key)
    const p = api
      .drawLuans(key)
      .then((r) => {
        const rows = (r && r.data) || []
        cache.set(key, rows)
        inflight.delete(key)
        return rows
      })
      .catch((e) => {
        inflight.delete(key) // không poison — retry được (card mục 5)
        throw e
      })
    inflight.set(key, p)
    return p
  }
  return { getCached, ensure }
}

// Giờ tương đối cho item list (LABELS.md mục 2, 24h, theo GIỜ MÁY KHÁCH — luật nhà):
//   cùng ngày   → «hôm nay · HH:mm» · hôm qua → «hôm qua · HH:mm» · xa hơn → «DD/MM · HH:mm»
// finished_at null/không parse được → null (BỎ DÒNG, không bịa giờ).
// `now` inject được cho test xác định (mốc 23:59→00:00 qua ngày là edge thật).
export function formatLuanTime(iso, now = new Date()) {
  if (!iso) return null
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return null
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  const time = `${hh}:${mm}`
  const day = (x) => `${x.getFullYear()}-${x.getMonth()}-${x.getDate()}`
  // hôm qua = kalender ngày liền trước (không tính 24h trôi — «hôm qua» là ngày)
  const y = new Date(now.getTime())
  y.setDate(y.getDate() - 1)
  if (day(d) === day(now)) return `hôm nay · ${time}`
  if (day(d) === day(y)) return `hôm qua · ${time}`
  const dd = String(d.getDate()).padStart(2, '0')
  const mo = String(d.getMonth() + 1).padStart(2, '0')
  return `${dd}/${mo} · ${time}`
}

export function _resetDrawLuansCacheForTests() {
  cache.clear()
  inflight.clear()
}
