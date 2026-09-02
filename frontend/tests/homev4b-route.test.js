// [HOME-V4-B] t_3647e25e — router: /tam-tu là route riêng + redirect link cũ.
// B1 (grep sạch cơ chế ?mode=donate) chốt bằng test nguồn-thật, B3 bằng guard replace.
import { describe, it, expect } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { routes } from '../src/router/index.js'

function makeRouter() {
  return createRouter({ history: createMemoryHistory('/app/'), routes })
}

describe('HOME-V4-B router — /tam-tu route riêng', () => {
  it('có route /tam-tu name donate trỏ DonateView (lazy)', async () => {
    const r = makeRouter()
    await r.push('/tam-tu')
    await r.isReady()
    expect(r.currentRoute.value.name).toBe('donate')
    const comp = r.currentRoute.value.matched.at(-1).components.default
    const resolved = comp.render ? comp : await comp().then((m) => m.default)
    expect(resolved.name).toBe('DonateView')
  })

  it('deep-link cũ /mo-khoa/duyen?mode=donate → redirect /tam-tu, dùng REPLACE (không nhét history)', async () => {
    const r = makeRouter()
    await r.push('/mo-khoa/duyen?mode=donate')
    await r.isReady()
    expect(r.currentRoute.value.path).toBe('/tam-tu')
    expect(r.currentRoute.value.query).toEqual({}) // mode bị bỏ hẳn, không sót query
    // route paywall gốc (không mode) KHÔNG bị ảnh hưởng
    await r.push('/mo-khoa/tai_loc')
    expect(r.currentRoute.value.name).toBe('paywall')
  })

  it('mọi ?mode nào trên /mo-khoa/* cũng bay (guard không đọc mode cho mục đích khác)', async () => {
    const r = makeRouter()
    await r.push('/mo-khoa/duyen?mode=xyz')
    await r.isReady()
    expect(r.currentRoute.value.name).toBe('paywall') // mode lạ: bỏ qua, vẫn paywall 29k
    expect(r.currentRoute.value.query).toEqual({})
  })

  it('B1 nguồn-thật: src/ không còn chuỗi mode=donate / donateMode / query.mode donate', () => {
    for (const f of [
      'src/router/index.js', 'src/views/PaywallView.vue', 'src/constants.js',
      'src/components/NavBar.vue', 'src/views/HomeView.vue', 'src/views/DetailView.vue',
    ]) {
      const src = readFileSync(f, 'utf8')
      expect(src, f).not.toMatch(/mode=donate|mode === 'donate'|donateMode|query:\s*\{\s*mode/)
    }
  })

  it('DONATE_HREF trỏ /tam-tu (mọi nguồn sinh link cũ đi qua nó)', async () => {
    const { DONATE_HREF } = await import('../src/constants.js')
    expect(DONATE_HREF).toBe('/tam-tu')
  })
})
