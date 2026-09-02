// VS3-S2 (t_68f2bfff) — copy có fallback, KHÔNG nag (SPEC-VS3 §S2, proposal G2).
// clipboard.writeText chết trên http/permission (đúng lane preview 530) → trước đây
// im lặng mất người không dấu vết. Giờ: tier writeText → tier textarea+execCommand
// → 'fail' (caller bắn V3 clipboard_denied — dữ liệu đủ để growth thấy, không toast).
// execCommand deprecated nhưng còn sống trên mọi Chromium/WebKit/Safari hiện hành —
// chấp nhận làm fallback cuối (SPEC §Dự phòng).

/** Class node tạm — test assert removeNode, không sót lại trong DOM. */
const TMP_CLASS = 'qhn-copy-tmp'

/** Tier 2: textarea tạm offscreen + execCommand('copy'). return true khi clipboard nhận. */
function execCommandCopy(text) {
  const ta = document.createElement('textarea')
  ta.className = TMP_CLASS
  ta.value = text
  // không readonly (SPEC §S2): 1 số UA không select() được ô readonly
  // offscreen tuyệt đối — không flash, không cuộn trang
  ta.style.cssText = 'position:fixed;top:0;left:-9999px;width:1px;height:1px;opacity:0;'
  try {
    document.body.appendChild(ta)
    ta.focus()
    ta.select()
    ta.setSelectionRange(0, text.length)
    return document.execCommand('copy') === true
  } catch {
    return false // browser đã bỏ hẳn API → 'fail' (caller bắn V3), không vỡ UX
  } finally {
    ta.remove()
  }
}

/**
 * Dán `text` vào clipboard, tự fallback. KHÔNG toast/alert ở tầng này (S2: im lặng).
 * @returns {Promise<'copy'|'copy_fallback'|'fail'>}
 */
export async function copyToClipboard(text) {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
      return 'copy'
    }
  } catch {
    /* http/permission — rơi xuống tier 2, không nag */
  }
  return execCommandCopy(text) ? 'copy_fallback' : 'fail'
}
