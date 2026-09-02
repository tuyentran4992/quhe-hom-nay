// [DEV-DONATE-QR] t_dc6112cf — quá cảnh trước payOS thật: BE stub nhét payload VietQR
// dạng `vietqr/action/qr/{bin}/{account}/{amount}/{content}` vào qr_data. FE parse để
// hiển thị NỘI DUNG CHUYỂN KHOẢN đọc được cho khách (card §3). PayOS thật sẽ trả chuỗi
// khác format → parseVietQr trả null, UI fallback in nguyên chuỗi qr_data + note.
// Nguồn chuỗi: backend app/Http/Controllers/PaymentController.php::qrPayload (main 67911a3).

export function parseVietQr(data) {
  if (typeof data !== 'string' || !data.startsWith('vietqr/')) return null
  const seg = data.split('/')
  const i = seg.lastIndexOf('qr')
  if (i < 0 || seg.length - i - 1 < 4) return null
  const [bin, account, amount, ...content] = seg.slice(i + 1)
  if (!/^\d{6}$/.test(bin) || !account || !/^\d+$/.test(amount)) return null
  return { bin, account, amount: Number(amount), content: content.join('/') }
}

// BIN → tên ngân hàng (chỉ mấy BIN stub hay dùng; unknown → hiện BIN thô, không bịa).
const BIN_BANKS = { '970436': 'Vietcombank' }
export function bankName(bin) {
  return BIN_BANKS[String(bin)] || `ngân hàng BIN ${bin}`
}

// [SPEC-CHANGE boss 02/09] Nội dung CK = MÃ TRUNG TÍNH `QH<order_code>` (vd QH384721) —
// CẤM hiện tên app/'Qu+Hom+Nay' trên màn hình (tranh bì soi giai đoạn đầu; đối chiếu
// vẫn đúng vì số đơn là khóa định danh). FE TỰ suy từ order_code, KHÔNG đọc content
// trong qr_data — BE (PaymentController::qrPayload) còn hardcode 'Qu+Hom+Nay', khi nào
// đổi payload QH thì màn hình vẫn nguyên vẹn.
export function ckCode(orderCode) {
  return `QH${orderCode}`
}
