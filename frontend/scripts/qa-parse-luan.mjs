// QA t_b8a95f0a — chay parseLuan THAT (util cua FE) tren van ban DB cua 6 bai: chung minh
// dong "### [Hoàn cảnh]" / "## [Hoàn cảnh]" KHONG duoc nhan dien marker -> ra heading '' (leak tho).
import { readFileSync, readdirSync } from 'node:fs'
import { parseLuan } from '../src/utils/luanRender.js'

const dir = '/data/agents/qa-engineer/outbox/t_b8a95f0a/evidence/t5'
for (const f of readdirSync(dir).filter((x) => x.startsWith('DB_'))) {
  const text = readFileSync(`${dir}/${f}`, 'utf8')
  const blocks = parseLuan(text)
  console.log(f, '=> headings:', JSON.stringify(blocks.map((b) => b.heading)), 'raw_hash_lines:', (text.match(/^#{1,4}\s*\[/gm) || []).length)
}
