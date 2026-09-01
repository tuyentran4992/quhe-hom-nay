// Design tokens 04-ui §1 — KHÓA, FE không tự chế. Test chống lệch token.
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import config from '../tailwind.config.js'

const t = config.theme.extend

describe('tailwind tokens = 04-ui §1', () => {
  it('đủ 7 màu token đúng hex', () => {
    expect(t.colors).toMatchObject({
      ink: '#1E1B18', paper: '#F7F2E7', paper2: '#EFE6D3',
      cinnabar: '#B33A2B', gold: '#A8802A', bamboo: '#3E5C48',
      muted: '#5C554A', // v3 boss duyệt (a670766) — WCAG AA trên paper
    })
  })

  it('font family han + body đúng', () => {
    expect(t.fontFamily.han[0]).toBe('"Noto Serif TC"')
    expect(t.fontFamily.body[0]).toBe('"Be Vietnam Pro"')
  })

  it('fontSize/borderRadius/spacing/boxShadow đúng spec', () => {
    expect(t.fontSize.body).toEqual(['16px', '1.65'])
    expect(t.fontSize.h1).toEqual(['26px', '1.3'])
    expect(t.fontSize.h2).toEqual(['20px', '1.4'])
    expect(t.fontSize.small).toEqual(['13px', '1.5'])
    expect(t.borderRadius.card).toBe('14px')
    expect(t.spacing.gutter).toBe('20px')
    expect(t.boxShadow.card).toBe('0 1px 3px rgb(30 27 24 / 0.12)')
    expect(t.boxShadow.lift).toBe('0 6px 18px rgb(30 27 24 / 0.16)')
  })

  // [UI-POLISH t_fc6387df] anti-generic gate: bản sắc Đông phải được DIỄN bằng giao diện.
  // 8 file SFC viết `class="han"` nhưng chưa từng có định nghĩa `.han` trong styles.css
  // → mọi chữ Hán/tên quẻ âm thầm rơi về sans-serif. Hợp đồng: `.han` PHẢI là class
  // thật, @apply đúng token font-han (không tự chế font mới).
  it('.han là class định nghĩa thật, @apply font-han (không phải class ma)', () => {
    // jsdom biến import.meta.url thành http — đọc theo cwd (vitest luôn chạy từ frontend/)
    const css = readFileSync('src/styles.css', 'utf8')
    expect(css).toMatch(/\.han\s*\{[^}]*@apply\s+font-han/)
  })
})
