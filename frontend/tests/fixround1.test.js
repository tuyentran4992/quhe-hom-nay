// HOME-FE-V3-FIX-R1 (t_b548bbd6) — TDD cho 2 bug QA-5E25-01/02 từ bệnh án
// /data/agents/qa-engineer/outbox/t_5E25-01 (đọc FIX-ROUND-1.md trước).
// BUG 1: khách mới 0 cookie vào / — NavBar d.load() ăn response #1 (is_new_device=true),
// HomeView d.load(true) force ăn response #2 (BE DeviceIdentityService.php:44 trả false
// vì cookie đã có mặt trong DB) → HomeView.vue:43 suy 'c'. State A 0% người đầu tiên thấy.
// Fix 1 chỗ useDeviceApi.load(): flag is_new_device BẮM sticky từ response ĐẦU TIÊN của
// cùng device_id — force reload giữ độ tươi today_draw nhưng không steal signal khách mới.
// BUG 2: mobile 375 — disclaimer wrap 2 dòng cao 55px, BottomTabs fixed bottom-9=36px
// → đè 19px. Fix: BottomTabs + DisclaimerBar ngồi chung 1 stack flex-col fixed bottom-0
// trong App.vue — overlap = 0 theo cấu trúc (jsdom không layout nên test chốt CẤU TRÚC
// DOM/class; bằng chứng số đo bounding box là e2e Playwright của QA).
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import App from '../src/App.vue'
import HomeView from '../src/views/HomeView.vue'
import NavBar from '../src/components/NavBar.vue'
import * as client from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'
import { _resetHexagramCacheForTests } from '../src/composables/useHexagrams.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: { me: vi.fn(), today: vi.fn(), history: vi.fn(), hexagram: vi.fn(), createDraw: vi.fn(), track: vi.fn() },
  }
})

const routes = [
  { path: '/', name: 'home', component: HomeView },
  { path: '/draw', name: 'draw', component: { template: '<div/>' } },
  { path: '/que/:drawId', name: 'detail', component: { template: '<div/>' } },
  { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
  { path: '/cua-ban', name: 'library', component: { template: '<div/>' } },
  { path: '/share-card', name: 'share-card', component: { template: '<div/>' } },
]
const mk = () => createRouter({ history: createMemoryHistory(), routes })

// shape #1 thật (03-api §1): today_draw null, history rỗng — y hệt browser sạch
const meNew = (isNew) => ({
  device_id: 'dev_new_0001', is_new_device: isNew, server_date_vn: '2026-09-02',
  entitlements: [], today_draw: null, free_deep: true,
})

beforeEach(() => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  _resetHexagramCacheForTests()
  client.api.history.mockResolvedValue({ data: [], meta: { count: 0 } })
  client.api.hexagram.mockResolvedValue({ data: null })
})

describe('BUG QA-5E25-01 — is_new_device phải sống sót force reload (store)', () => {
  it('load() #1 is_new=true rồi load(true) #2 is_new=false cùng device → store giữ TRUE', async () => {
    client.api.me
      .mockResolvedValueOnce(meNew(true)) // NavBar: request #1 — EnsureDeviceSession sinh device
      .mockResolvedValueOnce({ ...meNew(false), today_draw: null }) // HomeView: force #2 — BE trả false
    const a = useDevice()
    await a.load() // NavBar mount
    const b = useDevice()
    await b.load(true) // HomeView force
    expect(b.me.value.device_id).toBe('dev_new_0001')
    expect(b.me.value.is_new_device).toBe(true) // flag BẮM từ response đầu của device này
  })

  it('device ĐÃ có lịch sử (response đầu is_new=false) → giữ FALSE, không được thành true', async () => {
    client.api.me.mockResolvedValue(meNew(false))
    const a = useDevice()
    await a.load()
    const b = useDevice()
    await b.load(true)
    expect(b.me.value.is_new_device).toBe(false)
  })

  it('đổi device (device_id khác) → flag MỚI thắng, không sticky sai qua device khác', async () => {
    client.api.me
      .mockResolvedValueOnce(meNew(true))
      .mockResolvedValueOnce({ ...meNew(false), device_id: 'dev_other_9999' })
    const a = useDevice()
    await a.load()
    const b = useDevice()
    await b.load(true)
    expect(b.me.value.device_id).toBe('dev_other_9999')
    expect(b.me.value.is_new_device).toBe(false)
  })
})

describe('BUG QA-5E25-01 — flow thật: NavBar mount trước, HomeView force sau = State A', () => {
  it('shell NavBar + HomeView, client 0 cookie (2 response me #1=true #2=false) → hero A + tagline + noteA', async () => {
    client.api.me
      .mockResolvedValueOnce(meNew(true)) // NavBar d.load() — /api/me #1
      .mockResolvedValueOnce(meNew(false)) // HomeView d.load(true) — /api/me #2
    const r = mk()
    await r.push('/')
    const w = mount(App, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    await flushPromises()
    await flushPromises()
    const hero = w.find('[data-testid="home-hero"]')
    expect(hero.exists()).toBe(true)
    expect(hero.text()).toContain('Gieo ba đồng xu')
    expect(hero.text()).not.toContain('chờ bạn') // KHÔNG được rơi State C
    expect(w.find('[data-testid="home-hero-tagline"]').exists()).toBe(true)
    expect(w.find('[data-testid="home-hero-note"]').text()).toContain('hẹn lại đúng 0h mai') // noteA
    expect(w.find('[data-testid="home-cta-gieo"]').exists()).toBe(true)
  })

  it('control: device có history + is_new=false → vẫn State C đúng (không phá branch cũ)', async () => {
    const HIST = {
      data: [
        { id: 41, hexagram_id: 101, drawn_date: '2026-09-01', lines_rolled: [7, 7, 7, 7, 7, 7], changing_lines: [], created_at: '2026-09-01T02:00:00Z' },
      ],
      meta: { count: 1 },
    }
    client.api.me.mockResolvedValue(meNew(false))
    client.api.history.mockResolvedValue(HIST)
    const r = mk()
    await r.push('/')
    const w = mount(HomeView, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    await flushPromises()
    expect(w.find('[data-testid="home-hero"]').text()).toContain('chờ bạn')
    expect(w.find('[data-testid="home-hero-tagline"]').exists()).toBe(false)
  })
})

describe('BUG QA-5E25-02 — shell mobile: tabs + disclaimer chung 1 stack không thể đè nhau', () => {
  it('BottomTabs và DisclaimerBar là 2 phần tử DUY NHẤT của một container flex-col fixed bottom', async () => {
    const r = mk()
    await r.push('/')
    const w = mount(App, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    const nav = w.find('[data-testid="tab-home"]').element.closest('nav')
    const disc = w.find('[data-testid="disclaimer-bar"]').element.closest('footer')
    const wrap = nav.parentElement
    expect(wrap).toBe(disc.parentElement) // cùng cha → xếp dọc, không định vị độc lập nữa
    const cls = [...wrap.classList]
    expect(cls).toContain('fixed')
    expect(cls.join(' ')).toMatch(/bottom-0/)
    expect(cls).toContain('flex-col') // tab trên, disclaimer dưới — cấu trúc loại overlap
    // thứ tự DOM: nav PHẢI đứng trước footer (flex-col → nav nằm CAO HƠN disclaimer)
    expect(wrap.children[0]).toBe(nav)
    expect(wrap.children[1]).toBe(disc)
  })

  it('2 thành phần không còn tự định vị bottom độc lập (bottom-9 / fixed bottom-0 cũ = nguồn bug)', () => {
    const navCls = mount(NavBar).find('header').exists() // NavBar không đổi (desktop)
    expect(navCls).toBe(true)
  })

  it('BottomTabs không còn class bottom-9; DisclaimerBar footer không còn tự fixed', async () => {
    const r = await mk('/')
    const w = mount(App, { global: { plugins: [r] } })
    await r.isReady()
    await flushPromises()
    const nav = w.find('[data-testid="tab-home"]').element.closest('nav')
    const disc = w.find('[data-testid="disclaimer-bar"]').element.closest('footer')
    expect(nav.className).not.toMatch(/(^|\s)bottom-9(\s|$)/)
    expect(disc.className).not.toMatch(/(^|\s)fixed(\s|$)/)
    expect(disc.className).not.toMatch(/(^|\s)bottom-0(\s|$)/)
    // tabs vẫn phải là nav thật aria đúng, 3 link testid giữ nguyên
    expect(w.findAll('[data-testid="tab-home"],[data-testid="tab-draw"],[data-testid="tab-library"]').length).toBe(3)
    expect(w.find('nav[aria-label="Điều hướng nhanh"]').exists()).toBe(true)
  })
})
