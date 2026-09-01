// API client — contract 03-api.md. Cùng origin /api (dev: vite proxy 5173→8000).
// Envelope lỗi §0.3 → ApiError{status, code, message, details}. Mạng chết → status 0 code NETWORK.
export class ApiError extends Error {
  constructor(status, code, message, details = {}) {
    super(message || code)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.details = details || {}
  }
}

async function req(method, path, body) {
  const opt = {
    method,
    credentials: 'same-origin', // cookie qhn_device + laravel_session
    headers: { Accept: 'application/json' },
  }
  if (body !== undefined) {
    opt.headers['Content-Type'] = 'application/json'
    opt.body = JSON.stringify(body)
  }
  let res
  try {
    res = await fetch(path, opt)
  } catch {
    throw new ApiError(0, 'NETWORK', 'Mất kết nối mạng.', {})
  }
  let json = null
  try {
    json = await res.json()
  } catch {
    /* 204 / html */
  }
  if (!res.ok) {
    const e = (json && json.error) || {}
    throw new ApiError(res.status, e.code || 'INTERNAL', e.message || '', e.details || {})
  }
  return json
}

const uuid8 = () => crypto.randomUUID().replace(/-/g, '').slice(0, 16) // idempotency 8-64

export const api = {
  // #1 bootstrap phiên device
  me: () => req('GET', '/api/me'),
  // #2 tra cứu quẻ (deep-link S3)
  hexagram: (id) => req('GET', `/api/hexagrams/${id}`),
  // #2b 6 từ hào của 1 quẻ — SPEC-3XU (03-api §2b); FE lọc theo changing_lines
  haoTexts: (id) => req('GET', `/api/hexagrams/${id}/hao-texts`),
  // #3 gieo quẻ hôm nay
  createDraw: () => req('POST', '/api/draws', {}),
  // #4 sổ cá nhân
  history: (limit = 20) => req('GET', `/api/draws/history?limit=${limit}`),
  // #5 xin luận sâu — key tự sinh nếu caller không đưa.
  // LUAN-V2 D4 (§4.1, card t_b13fd2b9): question rỗng/whitespace sau trim → KHÔNG gửi
  // key `question` (KHÔNG gửi chuỗi rỗng/null) — giữ nguyên nhánh cache question NULL cũ.
  requestInterpretation: ({ draw_id, topic, idempotency_key, question }) => {
    const body = { draw_id, topic, idempotency_key: idempotency_key || uuid8() }
    const q = typeof question === 'string' ? question.trim() : ''
    if (q) body.question = q
    return req('POST', '/api/ai/interpretations', body)
  },
  // #6 poll job
  aiJob: (uuid) => req('GET', `/api/ai/jobs/${uuid}`),
  // #7 tạo đơn (unlock | donate)
  createPayment: ({ kind, topic, amount_vnd, return_url, idempotency_key }) =>
    req('POST', '/api/payments/create', {
      kind,
      topic,
      amount_vnd,
      return_url: return_url || location.origin + location.pathname,
      idempotency_key: idempotency_key || uuid8(),
    }),
  // #9 poll trạng thái đơn
  paymentStatus: (code) => req('GET', `/api/payments/${code}/status`),
  // #10 đọc nhanh hôm nay
  today: () => req('GET', '/api/me/today'),
  // #11 tracking event (06-mkt-tracking §3) — 204, lỗi nuốt được ở caller (fire-and-forget)
  track: (payload) => req('POST', '/api/track', payload),
  // #11 F7 tạo link chia sẻ thẻ (idempotent per device+draw — F7-CONTRACT §2)
  shareLinks: (draw_id) => req('POST', '/api/share-links', { draw_id }),
  // #12 F7 payload công khai thẻ /s/{token}
  shareCard: (token) => req('GET', `/api/share-links/${encodeURIComponent(token)}`),
}

export { uuid8 }
