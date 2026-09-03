// [FIX-BTNLINE t_a4978eca] — test chống tái phát class thây ma.
// Bệnh án: `btn-line` được dùng ở 3 nút CTA (DrawView draw-today/draw-retry,
// HomeTodayCard home-share-btn) nhưng CHƯA từng định nghĩa trong styles.css
// → render thật = chữ trần không viền không padding, CSS build grep = 0 hit.
// Hợp đồng: MỌI class `btn-*` xuất hiện trong frontend/src/**.vue PHẢI có
// khối định nghĩa `.btn-*` tương ứng trong src/styles.css (regex, không đoán).
import { describe, it, expect } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

// vitest chạy từ frontend/ (jsdom biến import.meta.url thành http — đọc theo cwd)
function walk(dir) {
  const out = []
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name.startsWith('.')) continue
    const p = join(dir, name)
    if (statSync(p).isDirectory()) out.push(...walk(p))
    else if (name.endsWith('.vue')) out.push(p)
  }
  return out
}

const sfcFiles = walk('src')
const css = readFileSync('src/styles.css', 'utf8')

// classes btn-* dùng trong template (class="..." :class="..." đều là chuỗi trong .vue)
const used = new Set()
for (const f of sfcFiles) {
  const src = readFileSync(f, 'utf8')
  for (const m of src.matchAll(/\bbtn-[a-z0-9][a-z0-9-]*\b/g)) used.add(m[0])
}
// classes btn-* được định nghĩa trong styles.css (khối selector .btn-x { ... })
const defined = new Set()
for (const m of css.matchAll(/(^|[,\s>+~])\.(btn-[a-z0-9][a-z0-9-]*)\s*[,{]/gm)) defined.add(m[2])

describe('btn-* class hợp đồng styles.css (FIX-BTNLINE)', () => {
  it('findings thực tế đủ 3 class hệ nút (chốt phạm vi test)', () => {
    expect([...used].sort()).toEqual(expect.arrayContaining(['btn-cinnabar', 'btn-line', 'btn-outline']))
    expect([...used].filter((c) => c.startsWith('btn-')).length).toBeGreaterThanOrEqual(3)
  })

  it('mọi class btn-* dùng trong .vue đều có định nghĩa trong styles.css', () => {
    const ghosts = [...used].filter((c) => !defined.has(c)).sort()
    expect(ghosts, `class thây ma chưa định nghĩa: ${ghosts.join(', ')}`).toEqual([])
  })

  it('.btn-line là khối thật: border + padding + hover + focus-visible', () => {
    const block = css.match(/\.btn-line\s*\{([^}]*)\}/)
    expect(block, '.btn-line phải có khối định nghĩa').not.toBeNull()
    const body = block[1]
    expect(body).toMatch(/@apply/)
    expect(body).toMatch(/\bborder\b/)
    expect(body).toMatch(/\bpx-\d|\bpy-\d/)
    expect(body).toMatch(/hover:/)
    expect(body).toMatch(/focus-visible:/)
  })

  it('.btn-line thừa hưởng hệ token .btn-outline (rounded-card, gold/50)', () => {
    const body = css.match(/\.btn-line\s*\{([^}]*)\}/)[1]
    expect(body).toMatch(/rounded-card/)
    expect(body).toMatch(/border-gold\/50/)
  })
})
