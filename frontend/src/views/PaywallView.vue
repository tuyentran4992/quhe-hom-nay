<script>
export default { name: 'PaywallView' }
</script>
<script setup>
// S4 Paywall — 04-ui §2.S4. Giá chốt 29.000đ one-time theo device — KHÔNG đồng hồ đếm
// ngược, KHÔNG "còn 2 suất". Nút 1 #7 unlock → QR + poll #9 3s timeout 5'. Nút 2 lễ tùy
// tâm (chọn 1/2/5/50k + input tay C-07) — donate KHÔNG mở khóa gì. paid → về S3 trigger #5.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { useToasts } from '../composables/useToasts.js'
import PayQr from '../components/PayQr.vue'
import { PRICE_UNLOCK_VND, PRICE_LABEL, DONATE_OPTIONS, DONATE_MIN, DONATE_MAX, TOPIC_LABELS, PAY_POLL_MS, PAY_POLL_TIMEOUT_MS } from '../constants.js'

const route = useRoute()
const router = useRouter()
const d = useDevice()
const toasts = useToasts()
const topic = computed(() => route.params.topic)
const label = computed(() => TOPIC_LABELS[topic.value] || topic.value)

const phase = ref('form') // form | qr | donated | error
const order = ref(null)
const payErr = ref('')
const payState = ref('pending') // pending | paid | expired | cancelled
const netWarn = ref(false)
let pollTimer = null
let pollStart = 0

const donatePick = ref(2000)
const donateCustom = ref('')
const donateAmount = computed(() => {
  const v = donateCustom.value !== '' ? Number(donateCustom.value) : donatePick.value
  return Number.isFinite(v) ? Math.trunc(v) : 0
})
const donateValid = computed(() => donateAmount.value >= DONATE_MIN && donateAmount.value <= DONATE_MAX)

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
function retriggerPoll() {
  pollStart = Date.now()
  poll()
}
async function donate() {
  payErr.value = ''
  if (!donateValid.value) { payErr.value = `Số lễ từ 1.000đ đến 500.000đ.`; return }
  try {
    await api.createPayment({
      kind: 'donate', amount_vnd: donateAmount.value,
      return_url: location.origin, idempotency_key: uuid(),
    })
    phase.value = 'donated'
  } catch (e) {
    payErr.value = e.code === 'NETWORK' ? 'Mất mạng — thử lại nhé.' : 'Không gửi được lễ. Thử lại sau.'
  }
}
// [MKT-F6-fix/FE] t_9bad794e §2.2 — donate_open: bắn #11 khi MỞ màn (fire-and-forget,
// catch ăn —tracking không được chặn mach chinh cua paywall; kieu JS landing PA1).
onMounted(() => {
  api.track({ name: 'donate_open', props: { topic: topic.value } }).catch(() => {})
})
onMounted(async () => { if (!d.me.value) await d.load().catch(() => {}) })
onBeforeUnmount(() => clearTimeout(pollTimer))
</script>

<template>
  <div class="wrap mx-auto max-w-xl px-gutter pt-6">
    <h1 class="han text-h1 font-semibold">Mở khóa luận sâu · {{ label }}</h1>
    <p data-testid="pay-price" class="text-h2 font-semibold text-cinnabar mt-2">{{ PRICE_LABEL }}</p>
    <p class="text-small text-muted mt-1">Trả một lần, đọc mãi trên thiết bị này.</p>

    <p v-if="payErr && phase === 'error'" data-testid="pay-error" class="text-small text-cinnabar mt-4">{{ payErr }}</p>

    <div v-if="phase === 'form'" class="mt-6 space-y-6">
      <button type="button" data-testid="pay-unlock-btn" class="btn-cinnabar w-full" @click="unlock">
        Mở khóa 29.000đ
      </button>

      <section data-testid="pay-donate-block" class="card p-4">
        <h2 class="font-semibold text-h2">Lễ tùy tâm</h2>
        <p class="text-small text-muted mt-1">Lễ là khích lệ tinh thần, không đổi lấy nội dung.</p>
        <div class="flex flex-wrap gap-2 mt-3">
          <button
            v-for="a in DONATE_OPTIONS"
            :key="a"
            type="button"
            data-testid="pay-donate-chip"
            class="px-3 py-1.5 rounded-card border"
            :class="donateCustom === '' && donatePick === a ? 'border-cinnabar text-cinnabar font-semibold' : 'border-gold/40 text-muted'"
            @click="donatePick = a; donateCustom = ''"
          >{{ a.toLocaleString('vi-VN') }}đ</button>
          <input
            v-model="donateCustom"
            type="number"
            inputmode="numeric"
            min="1000"
            max="500000"
            placeholder="Số khác"
            aria-label="Số lễ tùy tâm"
            data-testid="pay-donate-input"
            class="w-28 px-2 py-1.5 rounded-card border border-gold/40 bg-paper text-ink"
          />
        </div>
        <button type="button" data-testid="pay-donate-btn" class="btn-cinnabar mt-3" :disabled="!donateValid" @click="donate">
          Gửi lễ
        </button>
      </section>
    </div>

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

    <div v-else-if="phase === 'donated'" class="mt-6" data-testid="pay-thanks">
      <p class="text-body">Cảm ơn bạn đã gửi lễ. Nội dung mở khóa không thay đổi — lễ chỉ là khích lệ tinh thần.</p>
      <RouterLink to="/" class="btn-cinnabar inline-block mt-4">Về trang chính</RouterLink>
    </div>
  </div>
</template>
