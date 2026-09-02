// HOME-FE-V3 (t_a7026e13) — streak thuần từ lịch sử gieo, KHÔNG đồng hồ máy.
// Neo server_date_vn (#1) + ràng buộc a4 (dev-lead): hôm nay CHƯA gieo → chuỗi đếm từ
// hôm qua lùi về trước; API chưa có streak field → FE tự suy từ drawn_date #4 và chỉ
// in số khi suy được; suy không được → caller dùng dòng fallback KHÔNG con số.
// drawn_date là "YYYY-MM-DD" giờ VN (Draw.php:43) → so bằng ngày lịch, parse UTC để
// khỏi lệch do múi giờ máy khách.

/** "YYYY-MM-DD" (hoặc prefix 10 ký tự) → epoch-day (số nguyên ngày). */
export function epochDay(isoDate) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(isoDate || ''))
  if (!m) return null
  const y = Number(m[1])
  const mo = Number(m[2])
  const d = Number(m[3])
  if (!y || mo < 1 || mo > 12 || d < 1 || d > 31) return null
  return Math.floor(Date.UTC(y, mo - 1, d) / 86400000)
}

/**
 * Chuỗi ngày liền mạch kết thúc tại anchor (hôm nay nếu đã gieo, ngược lại hôm qua).
 * @param {string[]} drawnDates  các drawn_date "YYYY-MM-DD" (lịch sử #4, gồm cả hôm nay nếu đã gieo)
 * @param {string} serverDateVn  ngày hôm nay theo server VN (#1)
 * @returns {{count:number, dates:string[]}} count 0 = không suy được (thiếu dữ liệu → fallback)
 */
export function streakFromDates(drawnDates, serverDateVn) {
  const today = epochDay(serverDateVn)
  if (today == null) return { count: 0, dates: [] }
  const set = new Map()
  for (const s of drawnDates || []) {
    const e = epochDay(s)
    if (e != null) set.set(e, String(s).slice(0, 10))
  }
  const anchor = set.has(today) ? today : today - 1
  if (!set.has(anchor)) return { count: 0, dates: [] }
  const dates = []
  let e = anchor
  while (set.has(e)) {
    dates.unshift(set.get(e))
    e -= 1
  }
  return { count: dates.length, dates }
}
