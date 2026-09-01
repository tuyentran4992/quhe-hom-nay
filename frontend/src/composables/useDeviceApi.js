// useDeviceApi — 04-ui §3: logic gọi #1/#10, không đặt trong .vue.
// state.me chia sẻ toàn module (Paywall sau khi paid → TopicGate thấy entitlement mới);
// loading/error per-instance để mỗi màn tự render trạng thái riêng (FE không trắng màn).
import { ref, computed, reactive } from 'vue'
import { api } from '../api/client.js'

const state = reactive({ me: null })

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
      state.me = d
      return d
    } catch (e) {
      error.value = e
      throw e
    } finally {
      loading.value = false
    }
  }
  async function refresh() {
    // #10 alias nhẹ — chỉ cập nhật 3 field đọc nhanh
    const r = await api.today()
    state.me = { ...(state.me || { device_id: '', is_new_device: false }), ...r.data }
    return state.me
  }
  return { me: computed(() => state.me), todayDraw, entitlements, serverDateVn, freeDeep, loading, error, load, refresh }
}

export function _resetDeviceForTests() {
  state.me = null
}
