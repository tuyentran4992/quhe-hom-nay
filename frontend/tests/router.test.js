// FE-0 router: đủ các route theo specs/1.mvp/04-ui.md §2, lazy views, catchall.
// [HOME-V4-B] t_3647e25e — thêm /tam-tu (name donate) = route thứ 7.
import { describe, it, expect } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'
import { routes } from '../src/router/index.js'

function makeRouter() {
  return createRouter({ history: createMemoryHistory('/app/'), routes })
}

describe('router 04-ui §2', () => {
  const paths = routes.filter((r) => r.path !== '/:pathMatch(.*)*').map((r) => r.path)

  it('đủ 5 route S1..S5 + overlay F7 /share-card + /tam-tu HOME-V4-B đúng path spec', () => {
    expect(paths).toEqual(expect.arrayContaining(['/', '/draw', '/que/:drawId', '/mo-khoa/:topic', '/cua-ban', '/share-card', '/tam-tu']))
    expect(paths.length).toBe(7)
  })

  it('có catchall redirect về home', () => {
    const c = routes.find((r) => r.path === '/:pathMatch(.*)*')
    expect(c).toBeTruthy()
    expect(c.redirect).toBe('/')
  })

  it.each([
    ['/', 'HomeView'],
    ['/draw', 'DrawView'],
    ['/que/42', 'DetailView'],
    ['/mo-khoa/duyen', 'PaywallView'],
    ['/tam-tu', 'DonateView'], // [HOME-V4-B] t_3647e25e
    ['/cua-ban', 'LibraryView'],
  ])('resolve %s → %s', async (path, name) => {
    const r = makeRouter()
    await r.push(path)
    await r.isReady()
    const last = r.currentRoute.value.matched.at(-1)
    // lazy component đã resolve → kiểm tra qua __name hoặc name of component object
    const comp = last.components.default
    const resolved = comp.render ? comp : await comp().then((m) => m.default)
    expect(resolved.name).toBe(name)
  })

  it('route không biết → rơi catchall về home', async () => {
    const r = makeRouter()
    await r.push('/khong-ton-tai')
    await r.isReady()
    expect(r.currentRoute.value.matched.at(-1).path).toBe('/')
  })
})
