// timeline.test.js — FE-3XU PA1 (đăng sau gate t_04394e77) — nguồn bất biến:
// mockup playPA1 /data/agents/ux-ui/outbox/t_029084d1/mockup-3xu.html (1 chạm, 6 nhịp).
// Mốc chuẩn: fly 260/560/920/1280/1640/2000 · draw 560/920/1280/1640/2000/2360 ·
// dyno 2560 · reveal 3060 · S3 3600. C-08: reveal KHÔNG bao giờ trước 1500ms.
import { describe, it, expect } from 'vitest'
import { pa1Timeline, dynoLabels, isChanging, statusAt } from '../src/utils/timeline.js'

describe('PA1 timeline (mốc khớp đề xuất UX đã duyệt)', () => {
  const tl = pa1Timeline(1500)

  it('6 nhịp 360ms: hào vẽ tại 560+i*360 (dưới→trên)', () => {
    expect(tl.drawAt).toEqual([560, 920, 1280, 1640, 2000, 2360])
  })

  it('xu bay trước hào tương ứng: fly[0]=260, fly[i>0]=draw[i-1]', () => {
    expect(tl.flyAt[0]).toBe(260)
    for (let i = 1; i < 6; i++) expect(tl.flyAt[i]).toBe(tl.drawAt[i - 1])
  })

  it('dyno nháy sau hào cuối 200ms; reveal 3060; S3 3600', () => {
    expect(tl.dynoAt).toBe(2560)
    expect(tl.revealAt).toBe(3060)
    expect(tl.s3At).toBe(3600)
  })

  it('C-08 KẸP CỨNG: durationMs rác (0/500/null/âm) → reveal vẫn ≥1500 và ≥2360 (đủ 6 hào)', () => {
    for (const bad of [0, 500, -100, null, undefined]) {
      expect(pa1Timeline(bad).revealAt).toBe(3060)
    }
    // durationMs LỚN hơn lịch PA1 → dời reveal theo sàn mới, không sớm hơn
    expect(pa1Timeline(4000).revealAt).toBe(4000)
  })

  it('mọi mốc đơn điệu tăng: fly/draw xen kẽ ≤ dyno ≤ reveal ≤ S3', () => {
    const seq = [...tl.flyAt, ...tl.drawAt, tl.dynoAt, tl.revealAt, tl.s3At]
    const sorted = [...seq].sort((a, b) => a - b)
    expect(tl.drawAt.every((d, i) => d >= tl.flyAt[i])).toBe(true)
    expect(tl.dynoAt).toBeGreaterThanOrEqual(tl.drawAt[5])
    expect(tl.revealAt).toBeGreaterThan(tl.dynoAt)
    expect(tl.s3At).toBeGreaterThan(tl.revealAt)
    expect(sorted[0]).toBe(260)
  })
})

describe('nhận diện hào động từ rolled (C-09: 6/9 = động)', () => {
  it('isChanging: 6 và 9 là động; 7/8 tĩnh', () => {
    expect(isChanging(6)).toBe(true)
    expect(isChanging(9)).toBe(true)
    expect(isChanging(7)).toBe(false)
    expect(isChanging(8)).toBe(false)
  })

  it('dynoLabels: trả vị trí 1-based theo thứ tự dưới→trên', () => {
    expect(dynoLabels([9, 7, 7, 8, 8, 8])).toEqual([1])
    expect(dynoLabels([7, 6, 7, 7, 7, 9])).toEqual([2, 6])
    expect(dynoLabels([7, 8, 7, 8, 7, 8])).toEqual([])
  })
})

describe('statusAt — nhãn draw-status từng beat (wording mockup, 0 từ cấm §5)', () => {
  const tl = pa1Timeline(1500)
  it('beat 0: vê bó xu', () => {
    expect(statusAt(0, 'Địa Thiên Thái', '䷊')).toBe('Vê nhẹ bó xu…')
  })
  it('chỉ beat đầu ghi "Tung xu" (mockup runtime: fly cụm ≥2 không đổi status)', () => {
    expect(statusAt(260, 'X', '䷊')).toBe('Tung xu — hào 1')
    expect(statusAt(400, 'X', '䷊')).toBe('Tung xu — hào 1')
    expect(statusAt(560, 'X', '䷊')).toBe('Hào 1 · 6')   // draw thắng fly cùng mốc
    expect(statusAt(1000, 'X', '䷊')).toBe('Hào 2 · 6')
    expect(statusAt(1280, 'X', '䷊')).toBe('Hào 3 · 6')
    expect(statusAt(1640, 'X', '䷊')).toBe('Hào 4 · 6')
    expect(statusAt(2000, 'X', '䷊')).toBe('Hào 5 · 6')
  })
  it('mốc dyno: nhắc hào động', () => {
    expect(statusAt(2560, 'X', '䷊', [1])).toBe('Hào 1 động — dấu 動')
    expect(statusAt(2560, 'X', '䷊', [2, 6])).toBe('Hào 2·6 động — dấu 動')
    expect(statusAt(2560, 'X', '䷊', [])).toBe('Hào 6 · 6 — đủ quẻ') // quẻ tĩnh: không đổi status (mockup)
  })
  it('reveal+: tên quẻ', () => {
    expect(statusAt(3060, 'Địa Thiên Thái', '䷊')).toBe('Địa Thiên Thái ䷊')
  })
  it('không có status nào chứa từ cấm 04-ui §5', () => {
    const banned = /hóa giải|cúng|giải hạn|bùa|thay đổi vận mệnh|tâm linh|thỉnh|cốt|đồng tiền âm phủ|mở cung/i
    for (const t of [0, 260, 560, 920, 1280, 1640, 2000, 2360, 2560, 3060, 3600]) {
      expect(statusAt(t, 'Địa Thiên Thái', '䷊', [1])).not.toMatch(banned)
    }
  })
})
