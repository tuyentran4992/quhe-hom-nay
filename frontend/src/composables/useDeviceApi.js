// useDeviceApi — 04-ui §3: logic gọi #1/#10, không đặt trong .vue.
// state.me chia sẻ toàn module (Paywall sau khi paid → TopicGate thấy entitlement mới);
// loading/error per-instance để mỗi màn tự render trạng thái riêng (FE không trắng màn).
import { ref, computed, reactive } from 'vue'
import { api } from '../api/client.js'

const state = reactive({ me: null })
// [BUG01 FIX t_b548bbd6 / QA-5E25-01] is_new_device là TÍN HIỆU SỰ KIỆN "device vừa
// được sinh ra lượt gọi này" — BE DeviceIdentityService.php:44 trả false cho MỌI request
// sau khi cookie đã vào DB. NavBar load() #1 bắt được true, HomeView load(true) #2 tất yếu
// nhận false → HomeView.vue:43 suy 'c', State A không ai từng thấy (browser sạch 5/5).
// Fix 1 chỗ duy nhất tại store: capture flag từ response ĐẦU TIÊN của mỗi device_id,
// force reload sau giữ độ tươi today_draw/server_date nhưng KHÔNG xoá tín hiệu khách mới.
// Đổi device_id (cookie mới/seed lại) → capture lại từ đầu. Không thêm request.
let newFlag = null // { deviceId, isNew } — sticky per device, song thọ với state.me

export function useDevice() {
  const loading = ref(!state.me) // chưa có dữ liệu → render loading ngay lần paint đầu
  const error = ref(null)
  const todayDraw = computed(() => state.me?.today_draw ?? null)
  const entitlements = computed(() => state.me?.entitlements ?? [])
  const serverDateVn = computed(() => state.me?.server_date_vn ?? '')
  // [F8-FE C1] signal "luận sâu đang FREE" — CHỈ tin key top-level free_deep từ #1/#10,
  // KHÔNG suy từ entitlements (device trả 29k cũng đủ 3 topic). Thiếu key → false.
  const freeDeep = computed(() => state.me?.free_deep === true)

  async function load(force = false) {
    if (state.me && !force) return state.me
    loading.value = true
    error.value = null
    try {
      const d = await api.me()
      if (!newFlag || newFlag.deviceId !== d.device_id) {
        // response ĐẦU TIÊN của device này → capture tín hiệu khách mới
        newFlag = { deviceId: d.device_id, isNew: d.is_new_device === true }
      }
      state.me = { ...d, is_new_device: newFlag.isNew }
      return state.me
    } catch (e) {
      error.value = e
      throw e
    } finally {
      loading.value = false
    }
  }
  async function refresh() {
    // #10 alias nhẹ — chỉ cập nhật 3 field đọc nhanh; is_new_device giữ sticky store
    // (BUG01: không được inject false đè tín hiệu khách mới khi merge)
    const r = await api.today()
    state.me = {
      ...(state.me || { device_id: '', is_new_device: newFlag?.isNew === true }),
      ...r.data,
      is_new_device: newFlag?.isNew === true,
    }
    return state.me
  }
  return { me: computed(() => state.me), todayDraw, entitlements, serverDateVn, freeDeep, loading, error, load, refresh }
}

export function _resetDeviceForTests() {
  state.me = null
  newFlag = null // BUG01: cờ sticky cũng phải reset — test cách ly device
}
