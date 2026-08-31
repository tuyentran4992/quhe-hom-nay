// F7-FE utils/shareCard.js — TDD RED trước. Nguồn: SPEC-THE §2 (hook ≤80, TH1/TH2/E6),
// MOCKUP-CARD (clip 1:1 = 60 ký tự), CAP-THE §2 (2 caption CHỐT, em dash U+2014),
// F7-CONTRACT §4. clip80 cắt tại ranh giới câu/dấu phẩy gần nhất, không cắt giữa từ.
import { describe, it, expect } from 'vitest'
import {
  clip80,
  firstClause,
  buildCardModel,
  renderCaption,
  CARD_DISCLAIMER,
} from '../src/utils/shareCard.js'
import { CAPTION_NATIVE, CAPTION_1X1 } from '../src/constants.js'

const NX = 'Thiên Hỏa Đồng Nhân' // tên 4 từ worst-case (CAP-THE §0)

describe('clip80 — ranh giới câu/dấu phẩy, không cắt giữa từ', () => {
  it('ngắn hơn 80 → trả nguyên văn', () => {
    const s = 'Ở chỗ nguy mà không lỗi.'
    expect(clip80(s)).toBe(s)
  })

  it('đúng 80 ký tự → nguyên văn (trần là ≤80, không phải <80)', () => {
    const s = 'x'.repeat(80)
    expect(clip80(s)).toBe(s)
    expect(clip80('y'.repeat(81))).not.toBe('y'.repeat(81))
  })

  it('dài >80 → cắt tại ranh giới câu/dấu phẩy GẦN NHẤT ≤80, giữ dấu', () => {
    const s = 'Suốt ngày lo làm cho tới, chiều tối vẫn còn e dè. Ở chỗ nguy mà không lỗi, lại càng phải giữ mình cho chắc hơn nữa đó bạn.'
    const out = clip80(s)
    expect(out.length).toBeLessThanOrEqual(80)
    // ranh giới gần nhất trước 80 là dấu phẩy sau "lỗi"
    expect(out).toBe('Suốt ngày lo làm cho tới, chiều tối vẫn còn e dè. Ở chỗ nguy mà không lỗi,')
  })

  it('không bao giờ cắt giữa từ: không có phẩy/chấm trong 80 ký tự đầu → cắt tại khoảng trắng cuối', () => {
    const s = 'từ_một hai ba bốn năm sáu bảy tám chín mười mười một mười hai mười ba mười_bốn mười_lăm mười sáu mười_bảy dài'
    const out = clip80(s)
    expect(out.length).toBeLessThanOrEqual(80)
    // mọi token còn lại phải là token NGUYÊN của bản gốc (không cắt lẹm token)
    const src = s.split(' ')
    for (const tok of out.split(' ')) expect(src).toContain(tok)
    expect(s.startsWith(out)).toBe(true)
  })

  it('không có ranh giới nào vừa (cả câu là 1 từ dài >80) → null = caller về E6 tối giản', () => {
    expect(clip80('A'.repeat(90))).toBeNull()
  })

  it('max参数 hoá: 1:1 siết 60 ký tự', () => {
    const s = 'Suốt ngày lo làm cho tới, chiều tối vẫn còn e dè. Ở chỗ nguy mà không lỗi.'
    const out = clip80(s, 60)
    expect(out.length).toBeLessThanOrEqual(60)
    expect(out).toBe('Suốt ngày lo làm cho tới, chiều tối vẫn còn e dè.')
  })

  it('rỗng/null → null', () => {
    expect(clip80('')).toBeNull()
    expect(clip80(null)).toBeNull()
  })
})

describe('firstClause — TH2 đại ý: chỉ vế đầu trước "—" hoặc ","', () => {
  it('cắt tại em dash U+2014', () => {
    expect(firstClause('Sáu hào đều dương — trời chạy mãi không nghỉ.')).toBe('Sáu hào đều dương')
  })
  it('không có dấu → nguyên văn (clip80 sẽ xử lý tiếp)', () => {
    expect(firstClause('Trời xuống đất lên, giao nhau nên thông.')).toMatch('Trời xuống đất lên')
  })
})

describe('buildCardModel — nguồn sự thật "câu nào được show" (SPEC-THE §2)', () => {
  const HX = {
    id: 1,
    han: '乾',
    ten: 'Càn Vi Thiên',
    symbol: '䷀',
    dai_ci: 'Trời xuống đất lên, giao nhau nên thông.',
    keywords: ['càn', 'tự cường', 'dẫn đầu', 'hành động'],
  }
  const DRAW = { id: 42, hexagram_id: 1, drawn_date: '2026-08-30', changing_lines: [3] }

  it('TH1 ≥1 hào động → hook = nghia hào động ĐẦU TIÊN (sơ→thượng), source hao_dong', () => {
    const m = buildCardModel({
      draw: { ...DRAW, changing_lines: [6, 2] },
      hexagram: HX,
      haoDong: [
        { vi: 2, nghia: '包荒 vào chỗ trống, không lỗi.' },
        { vi: 6, nghia: 'Thành lại sụp xuống hào.' },
      ],
    })
    expect(m.hook.source).toBe('hao_dong')
    expect(m.hook.text).toBe('包荒 vào chỗ trống, không lỗi.')
  })

  it('TH2 0 hào động → hook = vế đầu dai_ci trước "—"/",", source dai_ci', () => {
    const m = buildCardModel({ draw: { ...DRAW, changing_lines: [] }, hexagram: HX, haoDong: [] })
    expect(m.hook.source).toBe('dai_ci')
    expect(m.hook.text).toBe('Trời xuống đất lên')
  })

  it('E6 dai_ci dài quá, không cắt nổi ≤80 (1 token dính) → thẻ tối giản (symbol + tên), KHÔNG lộ text rộn', () => {
    const m = buildCardModel({
      draw: { ...DRAW, changing_lines: [] },
      hexagram: { ...HX, dai_ci: 'Dai'.repeat(40) },
      haoDong: [],
    })
    expect(m.minimal).toBe(true)
    expect(m.hook).toBeNull()
    expect(m.hook_source).toBe('minimal')
    expect(m.symbol).toBe('䷀')
    expect(m.ten).toBe('Càn Vi Thiên')
  })

  it('đủ field contract: hexagram_id, ten, drawn_date dd/MM/yyyy, keywords 4, disclaimer, KHÔNG free_content/han/quoc_am', () => {
    const m = buildCardModel({ draw: DRAW, hexagram: { ...HX, free_content: { congViec: 'X' } }, haoDong: [] })
    expect(m.hexagram_id).toBe(1)
    expect(m.drawn_date).toBe('30/08/2026')
    expect(m.keywords).toEqual(['càn', 'tự cường', 'dẫn đầu', 'hành động'])
    expect(m.disclaimer).toBe(CARD_DISCLAIMER)
    const j = JSON.stringify(m)
    expect(j).not.toContain('free_content')
    expect(j).not.toContain('"X"')
    expect(j).not.toContain('quoc_am')
  })

  it('tên 4 từ worst-case: hook feed (60) và story (80) đều ≤ trần, không cắt giữa từ', () => {
    const longNghia = `${NX} ở giữa muôn người thì rộng, mà ở một mình thì e dè, cho nên giữ lòng mình trước đã.`
    const m = buildCardModel({
      draw: { ...DRAW, changing_lines: [1] },
      hexagram: { ...HX, ten: NX, symbol: '䷌' },
      haoDong: [{ vi: 1, nghia: longNghia }],
    })
    expect(m.hook.text.length).toBeLessThanOrEqual(80)
    expect(m.hook.text_1x1.length).toBeLessThanOrEqual(60)
    // cắt tại ranh giới, không cắt giữa từ: mọi token còn lại phải là token nguyên của bản gốc
    const src = longNghia.split(' ')
    for (const tok of m.hook.text.split(' ')) expect(src).toContain(tok)
  })
})

describe('CAP-THE §2 caption CHỐT — chép nguyên văn, em dash U+2014', () => {
  it('CAPTION_NATIVE đúng từng ký tự', () => {
    expect(CAPTION_NATIVE).toBe('Hôm nay tôi là {hexagram_ten} — bạn là quẻ nào?')
  })
  it('CAPTION_1X1 đúng từng ký tự', () => {
    expect(CAPTION_1X1).toBe('{hexagram_ten} — bạn là quẻ nào?')
  })
  it('dấu gạch là em dash U+2014, không phải hyphen', () => {
    expect(CAPTION_NATIVE).toContain('\u2014')
    expect(CAPTION_NATIVE).not.toMatch(/ - /)
  })
  it('renderCaption chỉ thay {hexagram_ten}', () => {
    expect(renderCaption(CAPTION_NATIVE, NX)).toBe('Hôm nay tôi là Thiên Hỏa Đồng Nhân — bạn là quẻ nào?')
    expect(renderCaption(CAPTION_1X1, 'Địa Thiên Thái')).toBe('Địa Thiên Thái — bạn là quẻ nào?')
  })
})
