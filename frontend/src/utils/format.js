// Tiện ích hiển thị — MŨI TÊN 04-ui/TESTIDS #4: ngày theo server_date_vn, KHÔNG đồng hồ máy.

/** "YYYY-MM-DD" (server VN) → "dd/MM/yyyy". */
export function fmtDateVn(isoDate) {
  if (!isoDate) return ''
  const [y, m, d] = String(isoDate).slice(0, 10).split('-')
  return `${d}/${m}/${y}`
}

/** VND integer (C-05 29000) → "29.000đ" (dấu chấm nghìn, kiểu VN). */
export function fmtVnd(n) {
  return `${String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`
}

/** changing_lines [2] → "Hào 2 động"; rỗng → ''. */
export function changingLabel(lines) {
  if (!lines || !lines.length) return ''
  return lines.map((l) => `Hào ${l} động`).join(', ')
}

/** RFC3339 UTC → Date giờ khách (render theo máy khách — nội quy nhà). */
export function toClientDate(iso) {
  return new Date(iso)
}
