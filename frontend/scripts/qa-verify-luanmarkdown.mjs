// t_127a3094 — ACCEPTANCE gate BUG-V3-2: với 6 văn bản THẬT từ DB (ai_jobs 50–55),
// parseLuan() phải trả đúng 3 heading sạch VÀ KHÔNG khối nào (heading lẫn text)
// còn marker thô / dấu thăng / ngoặc vuông lọt ra mắt người dùng.
import { readFileSync, readdirSync } from 'node:fs'
import { parseLuan, LUAN_HEADINGS } from '../src/utils/luanRender.js'

const dir = '/data/agents/qa-engineer/outbox/t_b8a95f0a/evidence/t5'
let fail = 0
for (const f of readdirSync(dir).filter((x) => x.startsWith('DB_')).sort()) {
  const text = readFileSync(`${dir}/${f}`, 'utf8')
  const blocks = parseLuan(text)
  const headings = blocks.map((b) => b.heading)
  const outHash = blocks.filter((b) => /^#{1,4}\s/m.test(b.text)).length
  const outBracket = blocks.filter((b) => /[\[\]]/.test(b.text) || /[\[\]]/.test(b.heading)).length
  const ok = JSON.stringify(headings) === JSON.stringify(LUAN_HEADINGS) && !outHash && !outBracket
  if (!ok) fail++
  console.log(`${ok ? 'PASS' : 'FAIL'} ${f} headings=${JSON.stringify(headings)} out_hash_lines=${outHash} out_brackets=${outBracket}`)
}
process.exit(fail ? 1 : 0)
