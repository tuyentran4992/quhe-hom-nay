// F7-FE utils/track.js — POST /api/track (06-mkt-tracking §3 #11) fire-and-forget.
// BẤT BIẾN: fail im lặng (mạng/422/500 → nuốt), KHÔNG await ở caller — tracking không
// được chặn UX chia sẻ. Tên event NGUYÊN VĂN METRICS §1 V1-V4, KHÔNG prefix qhn_
// (ADR-002/F7-CONTRACT §1 — whitelist F2 đã review ĐẠT).

/**
 * Bắn 1 event. props ≤2KB (BE validate). Không trả promise — caller không chờ.
 * Dùng fetch thẳng (không qua req(): req ném ApiError, track phải im lặng tuyệt đối).
 */
export function track(name, props = {}) {
  try {
    fetch('/api/track', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ name, props }),
    }).catch(() => {})
  } catch {
    /* JSON tròn lặp/mạng chết — im lặng (SPEC: fail im lặng) */
  }
}

/** 4 sự kiện V1–V4 phía FE (METRICS §1). Params bắt buộc đúng từng tên field. */
export const trackShareCard = {
  /** V1 — overlay /share-card hiện. */
  open: ({ draw_id, hexagram_id, has_dynamic_line }) =>
    track('share_card_open', { draw_id, hexagram_id, has_dynamic_line: !!has_dynamic_line }),
  /** V2 — 1 khung bất kỳ render xong. frame: "9x16" | "1x1". */
  created: ({ draw_id, frame, render_ms }) =>
    track('share_card_created', { draw_id, frame, render_ms: Math.round(render_ms || 0) }),
  /** V3 — E1 (render/font/link lỗi hiển thị). reason slug ngắn. */
  error: ({ draw_id, reason }) => track('share_card_error', { draw_id, reason: String(reason || 'unknown').slice(0, 50) }),
  /** V4 — tải ảnh / copy link / native sheet SUCCESS (cancel KHÔNG bắn — E2). */
  done: ({ draw_id, method, token }) => track('share_card_done', { draw_id, method, token: token || null }),
}
