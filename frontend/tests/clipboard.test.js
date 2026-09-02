// VS3-S2 (t_68f2bfff) — TDD: utils/clipboard.js copyToClipboard(text) ->
// 'copy' | 'copy_fallback' | 'fail'. Nguồn: SPEC-VS3 §S2 (proposal G2 — clipboard
// fail im lặng là mất người không dấu vết). Fallback textarea tạm class
// .qhn-copy-tmp, removeNode trong finally. KHÔNG toast/alert ở tầng util.
import { describe, it, expect, vi, afterEach } from 'vitest'
import { copyToClipboard } from '../src/utils/clipboard.js'

/** jsdom KHÔNG định nghĩa document.execCommand → gán fn thật (configurable), delete ở afterEach. */
function mockExecCommand(impl) {
  Object.defineProperty(document, 'execCommand', { value: vi.fn(impl), configurable: true, writable: true })
  return document.execCommand
}

afterEach(() => {
  delete navigator.clipboard
  delete document.execCommand
  // không được sót node tạm giữa các test
  expect(document.querySelectorAll('.qhn-copy-tmp').length).toBe(0)
})

describe('copyToClipboard — tier writeText', () => {
  it('writeText thành công → "copy", KHÔNG đụng execCommand', async () => {
    const write = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: write }, configurable: true })
    const exec = mockExecCommand(() => true)
    await expect(copyToClipboard('hello')).resolves.toBe('copy')
    expect(write).toHaveBeenCalledWith('hello')
    expect(exec).not.toHaveBeenCalled()
  })

  it('writeText reject → fallback execCommand(textarea select) → "copy_fallback", node tạm sạch', async () => {
    const write = vi.fn().mockRejectedValue(new Error('NotAllowedError'))
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: write }, configurable: true })
    let seen = null
    const exec = mockExecCommand((cmd) => {
      const tmp = document.querySelector('.qhn-copy-tmp')
      seen = tmp && tmp.value
      return true
    })
    await expect(copyToClipboard('cap\nhttps://x.y/s/abc')).resolves.toBe('copy_fallback')
    expect(exec).toHaveBeenCalledWith('copy')
    expect(seen).toBe('cap\nhttps://x.y/s/abc') // textarea mang ĐÚNG text
  })

  it('navigator.clipboard ABSENT (http lane) → vẫn ra "copy_fallback" nếu execCommand được', async () => {
    // jsdom không có navigator.clipboard mặc định → khỏi defineProperty
    expect(navigator.clipboard).toBeUndefined()
    const exec = mockExecCommand(() => true)
    await expect(copyToClipboard('text')).resolves.toBe('copy_fallback')
    expect(exec).toHaveBeenCalledWith('copy')
  })

  it('execCommand NÉM (browser bỏ hẳn API) → "fail" không vỡ, node tạm vẫn sạch', async () => {
    Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true })
    mockExecCommand(() => {
      throw new Error('document.execCommand is not a function')
    })
    await expect(copyToClipboard('text')).resolves.toBe('fail')
  })

  it('cả hai fail (execCommand trả false) → "fail", không throw', async () => {
    const write = vi.fn().mockRejectedValue(new Error('deny'))
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: write }, configurable: true })
    mockExecCommand(() => false)
    await expect(copyToClipboard('text')).resolves.toBe('fail')
  })
})
