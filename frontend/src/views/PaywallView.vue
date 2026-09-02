<script>
export default { name: 'PaywallView' }
</script>
<script setup>
// S4 Paywall — 04-ui §2.S4. Giá chốt 29.000đ one-time theo device — KHÔNG đồng hồ đếm
// ngược, KHÔNG "còn 2 suất". Nút 1 #7 unlock → QR + poll #9 3s timeout 5'. paid → về S3
// trigger #5.
// [HOME-V4-B] t_3647e25e — Luật 2 (boss chốt 02/09): màn này CHỈ còn MỘT chế độ mở khóa
// theo giá. Cơ chế query mode cũ chết hẳn: lễ tùy tâm là route riêng /tam-tu (DonateView),
// deep-link cũ có mode donate redirect ngay ở router guard (khách không bao giờ thấy 29k).
// /mo-khoa/* không đọc ?mode nào nữa.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { useToasts } from '../composables/useToasts.js'
import PayQr from '../components/PayQr.vue'
import { PRICE_UNLOCK_VND, PRICE_LABEL, TOPIC_LABELS, PAY_POLL_MS, PAY_POLL_TIMEOUT_MS } from '../constants.js'

const route = useRoute()
const router = useRouter()
const d = useDevice()
const toasts = useToasts()
const topic = computed(() => route.params.topic)
// [FE-G1] t_d99af588 — deep-link slug gạch nối (user tự gõ /mo-khoa/tai-loc): normalize
// -→_ trước khi tra nhãn để TOPIC_LABELS khớp; slug nội bộ snake (tai_loc) không đổi.
// Chỉ là display fallback — payload #7 vẫn gửi topic nguyên URL.
const label = computed(() => TOPIC_LABELS[topic.value] || TOPIC_LABELS[String(topic.value).replace(/-/g, '_')] || topic.value)

const phase = ref('form') // form | qr | error
const order = ref(null)
const payErr = ref('')
const payState = ref('pending') // pending | paid | expired | cancelled
const netWarn = ref(false)
let pollTimer = null
let pollStart = 0

function uuid() { return crypto.randomUUID().replace(/-/g, '').slice(0, 16) }

async function unlock() {
  payErr.value = ''
  try {
    const r = await api.createPayment({
      kind: 'unlock', topic: topic.value, amount_vnd: PRICE_UNLOCK_VND,
      return_url: location.origin + '/que', idempotency_key: uuid(),
    })
    order.value = r.data
    phase.value = 'qr'
    pollStart = Date.now()
    schedulePoll()
  } catch (e) {
    payErr.value = e.code === 'NETWORK' ? 'Mất mạng — thử lại nhé.' : 'Không tạo được đơn. Thử lại sau.'
    phase.value = 'error'
  }
}
function schedulePoll() {
  pollTimer = setTimeout(poll, PAY_POLL_MS)
}
async function poll() {
  if (!order.value) return
  try {
    const r = await api.paymentStatus(order.value.order_code)
    netWarn.value = false
    payState.value = r.data.status
    if (r.data.status === 'paid') {
      await d.refresh().catch(() => {})
      toasts.push(`Đã mở khóa ${label.value}`)
      const id = d.todayDraw.value?.id
      router.replace(id ? { name: 'detail', params: { drawId: id } } : { name: 'home' })
      return
    }
    if (['expired', 'cancelled'].includes(r.data.status)) return
  } catch (e) {
    netWarn.value = true // giữ QR + "Chờ thanh toán..." (04-ui §4)
  }
  if (Date.now() - pollStart > PAY_POLL_TIMEOUT_MS) {
    payState.value = 'expired'
    return
  }
  schedulePoll()
}
function stopPoll() {
  clearTimeout(pollTimer)
  pollTimer = null
}
function retriggerPoll() {
  pollStart = Date.now()
  poll()
}
// [MKT-F6-fix/FE] t_9bad794e §2.2 — donate_open: bắn #11 khi MỞ màn (fire-and-forget,
// catch ăn —tracking không được chặn mach chinh cua paywall; kieu JS landing PA1).
// [HOME-V4-B] event name GIỮ NGUYÊN cho paywall (donate_open giờ ALSO bắn ở DonateView
// /tam-tu — 2 màn 2 nơi bắn, semantics "mở màn thanh toán" như spec #11).
onMounted(() => {
  api.track({ name: 'donate_open', props: { topic: topic.value } }).catch(() => {})
})
onMounted(async () => { if (!d.me.value) await d.load().catch(() => {}) })
onBeforeUnmount(() => stopPoll())
</script>

<template>
  <div class="wrap mx-auto max-w-xl px-gutter pt-6">
    <h1 data-testid="pay-title" class="han text-h1 font-semibold">Mở khóa luận sâu · {{ label }}</h1>
    <p data-testid="pay-price" class="text-h2 font-semibold text-cinnabar mt-2">{{ PRICE_LABEL }}</p>
    <p class="text-small text-muted mt-1">Trả một lần, đọc mãi trên thiết bị này.</p>

    <p v-if="payErr && phase === 'error'" data-testid="pay-error" class="text-small text-cinnabar mt-4">{{ payErr }}</p>

    <div v-if="phase === 'form'" class="mt-6 space-y-6">
      <button type="button" data-testid="pay-unlock-btn" class="btn-cinnabar w-full" @click="unlock">
        Mở khóa {{ PRICE_LABEL }}
      </button>
    </div>

    <!-- ═══ NHÁNH UNLOCK 29k — hành vi GIỮ NGUYÊN (regression paywall.test.js) ═══ -->
    <div v-else-if="phase === 'qr'" class="mt-6">
      <PayQr :qr-data="order?.qr_data || ''" :confirm-url="order?.confirm_url || ''" :amount-label="`${PRICE_LABEL} · đơn ${order?.order_code || ''}`" />
      <p data-testid="pay-stub-note" class="text-small text-muted text-center mt-2">
        <template v-if="order?.stub">Thanh toán tự động đang sắp mở — giai đoạn này chưa thu tiền thật, mô hình chỉ để thử luồng.</template>
        <template v-else>Chuyển đúng nội dung ngân hàng để được ghi nhận.</template>
      </p>
      <p data-testid="pay-status" class="text-body text-muted text-center mt-3">
        <template v-if="payState === 'paid'">Đã nhận được lễ — đang mở…</template>
        <template v-else-if="payState === 'expired'">Đơn hết hạn. <button type="button" class="underline text-bamboo" data-testid="pay-retry" @click="phase = 'form'">Tạo đơn mới</button></template>
        <template v-else>Chờ thanh toán…</template>
      </p>
      <p v-if="netWarn" data-testid="pay-net-warn" class="text-small text-cinnabar text-center mt-1">
        Mất mạng khi kiểm tra đơn — giữ nguyên mã QR.
      </p>
      <button v-if="netWarn" type="button" data-testid="pay-repoll" class="btn-cinnabar w-full mt-3" @click="retriggerPoll">Kiểm tra lại đơn</button>
    </div>
  </div>
</template>
