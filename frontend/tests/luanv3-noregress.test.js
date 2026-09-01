// LUAN-V3-FE (card t_9d4a39c8) — chốt hành vi V2 sau verdict anh Tuyền 16:0x:
// heading cổ hóa BỊ HỦY hoàn toàn, lane FE duy nhất còn lại = chứng minh 2 case
// render KHÔNG cần sửa gì (CEO: "không có gì đổi trên API/UI, chỉ verify 2 case render").
// 1) CROSS-TAB: router luận theo mục KHÁC tab đang bấm → UI đứng yên, không đổi tab,
//    không có text hardcode tên mục gây mâu thuẫn (§5.5 amended).
// 2) KHONG_THUOC_NAO: bài về chuyện ngoài 3 mục (T-C bỏ dòng danh mục) → FE không
//    bịa "Chủ đề luận sâu"/"Góc nhìn sẵn có" — 0 chuỗi đó trong vùng kết quả.
// Marker render vẫn là bộ V2 @ eaced06 — test này là CHỐT CHUÔNG hồi quy, không phải
// test tính năng mới.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import TopicGate from '../src/components/TopicGate.vue'
import * as client from '../src/api/client.js'
import { useDevice, _resetDeviceForTests } from '../src/composables/useDeviceApi.js'

vi.mock('../src/api/client.js', async (orig) => {
  const real = await orig()
  return {
    ...real,
    api: { me: vi.fn(), today: vi.fn(), requestInterpretation: vi.fn(), aiJob: vi.fn() },
  }
})

const JOB = { job_uuid: 'j-1', status: 'queued' }
let wrapper = null
let r = null

function mountGate(topic = 'duyen') {
  r = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/l/:drawId/:topic', name: 'detail', component: { template: '<div/>' } },
      { path: '/mo-khoa/:topic', name: 'paywall', component: { template: '<div/>' } },
    ],
  })
  wrapper = mount(TopicGate, {
    props: { drawId: 42, topic },
    global: { plugins: [r] },
  })
  return wrapper
}

// Nối gót driveToDone của luanv2.test.js: bấm xin luận → poll → done với payload bài.
async function driveToDone(w, resultText) {
  client.api.aiJob.mockResolvedValue({ data: { job_uuid: 'j-1', status: 'done', result: resultText } })
  vi.useFakeTimers()
  await w.find('[data-testid="gate-ask"]').trigger('click')
  await vi.advanceTimersByTimeAsync(2000)
  await flushPromises()
  vi.useRealTimers()
  return w
}

beforeEach(async () => {
  vi.clearAllMocks()
  _resetDeviceForTests()
  client.api.me.mockResolvedValue({
    device_id: 'd', is_new_device: false, server_date_vn: '2026-09-01',
    entitlements: ['duyen', 'tai_loc', 'xuat_hanh'], today_draw: null,
  })
  client.api.requestInterpretation.mockResolvedValue({ data: JOB })
  await useDevice().load()
})
afterEach(() => {
  if (wrapper) wrapper.unmount()
  wrapper = null
  vi.useRealTimers()
})

describe('LUAN-V3 case CROSS-TAB — router luận mục khác tab: UI đứng yên', () => {
  // Bài T-B giả định: khách bấm tab Tình duyên, router tin câu hỏi → luận hẳn về tài chính.
  const CROSS = [
    '[Hoàn cảnh]',
    'Chuyện tiền nong của anh đang dồn cục: dòng tiền cuối tháng lệch hẳn sang hướng hao tán.',
    '',
    '[Vì sao khuyên vậy]',
    'Hào lục tam nói giữ của thì phải xét người giữ, không phải xét số tiền.',
    '',
    '[Việc nên làm cụ thể tuần này — tối đa 3 gạch đầu dòng]',
    '- Khóa chi tiêu tự động 15% lương vào cuối tuần này.',
  ].join('\n')

  it('heading vùng vẫn là "Luận sâu · Tình duyên" theo TAB, không lật theo mục router luận', async () => {
    const w = mountGate('duyen')
    await flushPromises()
    await driveToDone(w, CROSS)
    const h3 = w.find('[data-testid="topic-gate"] h3')
    expect(h3.text()).toBe('Luận sâu · Tình duyên')
    // payload vẫn gửi topic của tab như cũ (§5.5: không đổi payload)
    expect(client.api.requestInterpretation).toHaveBeenCalledWith(
      expect.objectContaining({ topic: 'duyen', draw_id: 42 }),
    )
  })

  it('không đổi tab: router vẫn ở route cũ sau khi bài cross-mục render xong', async () => {
    const w = mountGate('duyen')
    await flushPromises()
    const before = r.currentRoute.value.fullPath
    await driveToDone(w, CROSS)
    expect(r.currentRoute.value.fullPath).toBe(before)
  })

  it('FE không thêm text hardcode tên mục (chip/dòng "Luận theo: …") — chỉ marker V2 render', async () => {
    const w = mountGate('duyen')
    await flushPromises()
    await driveToDone(w, CROSS)
    const html = w.find('[data-testid="gate-result"]').text()
    expect(html).not.toMatch(/Luận theo/i)
    expect(html).not.toContain('Tài lộc') // FE không tự dán nhãn mục của router
    // marker thô không bao giờ lộ
    expect(html).not.toContain('[Hoàn cảnh]')
    expect(w.findAll('[data-testid="luan-heading"]').map((n) => n.text())).toEqual([
      'Hoàn cảnh', 'Vì sao khuyên vậy', 'Việc nên làm cụ thể tuần này',
    ])
  })
})

describe('LUAN-V3 case KHONG_THUOC_NAO — ngoài 3 mục: FE không bịa khối danh mục', () => {
  // T-C amended: V2 wording, bỏ dòng danh mục → bài về chuyện ngoài 3 mục (sức khỏe
  // gia đình), vẫn 3 marker V2, không có dòng "Chủ đề…"/"Góc nhìn…".
  const OUTSIDE = [
    '[Hoàn cảnh]',
    'Chuyện bà nhà anh ốm là chuyện ngoài ba mục thường luận — quẻ bàn về khí lực, không về đôi lứa.',
    '',
    '[Vì sao khuyên vậy]',
    'Quẻ cần nói thẳng: gốc là nghỉ ngơi, không phải là đoán trước.',
    '',
    '[Việc nên làm cụ thể tuần này — tối đa 3 gạch đầu dòng]',
    '- Xếp lịch khám trong 3 ngày tới.',
  ].join('\n')

  it('không render "Chủ đề luận sâu"/"Góc nhìn sẵn có" trong vùng kết quả', async () => {
    const w = mountGate('duyen')
    await flushPromises()
    await driveToDone(w, OUTSIDE)
    const res = w.find('[data-testid="gate-result"]').text()
    expect(res).not.toContain('Chủ đề luận sâu')
    expect(res).not.toContain('Góc nhìn')
    expect(res).not.toMatch(/\[(Hoàn cảnh|Vì sao|Việc)/) // 0 marker thô rò
  })

  it('bài trơn không marker (BE trả kiểu cũ) → một khối, không heading bịa, đủ chữ', async () => {
    const w = mountGate('duyen')
    await flushPromises()
    await driveToDone(w, 'Chuyện này ngoài ba mục mình luận. Anh cứ nghỉ sớm vài bữa đã.')
    const heads = w.findAll('[data-testid="luan-heading"]')
    expect(heads.length).toBe(0)
    expect(w.find('[data-testid="luan-body"]').text()).toContain('nghỉ sớm')
  })
})
