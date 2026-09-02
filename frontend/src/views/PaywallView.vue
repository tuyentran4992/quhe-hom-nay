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
import { parseVietQr, bankName, ckCode } from '../utils/donateQr.js'
import { PRICE_UNLOCK_VND, PRICE_LABEL, DONATE_TIERS, DONATE_DEFAULT_VND, DONATE_MIN, DONATE_MAX, TOPIC_LABELS, PAY_POLL_MS, PAY_POLL_TIMEOUT_MS } from '../constants.js'

const route = useRoute()
const router = useRouter()
const d = useDevice()
const toasts = useToasts()
const topic = computed(() => route.params.topic)
const label = computed(() => TOPIC_LABELS[topic.value] || topic.value)
// [F8-FE C4] t_03424b76 — donateMode = query AND flag. URL do user sửa được → flag OFF
// thì PHỚT LỜ query, giữ paywall 29k nguyên bản đã QA (chống giả mạo).
const donateMode = computed(() => route.query.mode === 'donate' && d.freeDeep.value)

const phase = ref('form') // form | qr | donated | error
const order = ref(null)
const payErr = ref('')
const payState = ref('pending') // pending | paid | expired | cancelled
const netWarn = ref(false)
let pollTimer = null
let pollStart = 0

const donatePick = ref(DONATE_DEFAULT_VND) // [SPEC-CHANGE boss 02/09] mức 2 trong 10/20/50/100k — CFG-FE: về constants
const donateCustom = ref('')
const donateAmount = computed(() => {
  const v = donateCustom.value !== '' ? Number(donateCustom.value) : donatePick.value
  return Number.isFinite(v) ? Math.trunc(v) : 0
})
const donateValid = computed(() => donateAmount.value >= DONATE_MIN && donateAmount.value <= DONATE_MAX)
// [DEV-DONATE-QR] đơn hiện tại là donate hay unlock — poll/templates rẽ nhánh theo kind.
const orderKind = computed(() => order.value?.kind || 'unlock')
const isDonateOrder = computed(() => orderKind.value === 'donate')
const donateCk = computed(() => parseVietQr(order.value?.qr_data)) // null = chuỗi payOS/opaque
const donateAmountLabel = computed(() =>
  `Lễ ${order.value ? order.value.amount_vnd.toLocaleString('vi-VN') : donateAmount.value.toLocaleString('vi-VN')}đ — đơn #${order.value?.order_code || ''}`)

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
      if (orderKind.value === 'donate') {
        // [DEV-DONATE-QR] t_dc6112cf — donate paid: CHỈ hiện "Cảm ơn" (màn donated).
        // KHÔNG router.replace, KHÔNG toast, KHÔNG refresh entitlement — lễ là khích lệ
        // tinh thần, không đổi lấy nội dung (BE: entitlement = row kind=unlock,paid).
        stopPoll()
        phase.value = 'donated'
        return
      }
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
// [DEV-DONATE-QR] donate lại sau expired/cancelled: tạo đơn MỚI (idempotency key mới —
// C-07 BE chặn key cũ), reset trạng thái poll.
async function donateRetry() {
  await donate()
}
async function donate() {
  payErr.value = ''
  if (!donateValid.value) { payErr.value = `Số lễ từ ${DONATE_MIN.toLocaleString('vi-VN')}đ đến ${DONATE_MAX.toLocaleString('vi-VN')}đ.`; return }
  try {
    // BỆNH CŨ: response bị vứt → nhảy thẳng 'donated' khi khách chưa chuyển đồng nào.
    // FIX: giữ order thật → màn QR + chờ webhook paid (#card t_dc6112cf §1).
    const r = await api.createPayment({
      kind: 'donate', amount_vnd: donateAmount.value,
      return_url: location.origin, idempotency_key: uuid(),
    })
    order.value = r.data
    payState.value = 'pending'
    netWarn.value = false
    phase.value = 'qr'
    pollStart = Date.now()
    schedulePoll()
  } catch (e) {
    payErr.value = e.code === 'NETWORK' ? 'Mất mạng — thử lại nhé.' : 'Không gửi được lễ. Thử lại sau.'
    phase.value = 'error'
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
  <div class="wrap mx-auto max-w-xl px-gutter pt-6" :data-testid="donateMode ? 'pay-mode-donate' : undefined">
    <h1 class="han text-h1 font-semibold">{{ donateMode ? 'Lễ tùy tâm' : `Mở khóa luận sâu · ${label}` }}</h1>
    <p v-if="!donateMode" data-testid="pay-price" class="text-h2 font-semibold text-cinnabar mt-2">{{ PRICE_LABEL }}</p>
    <p v-if="!donateMode" class="text-small text-muted mt-1">Trả một lần, đọc mãi trên thiết bị này.</p>
    <p v-if="donateMode && phase === 'form'" class="text-small text-muted mt-1">Lễ là khích lệ tinh thần, không đổi lấy nội dung.</p>

    <p v-if="payErr && phase === 'error'" data-testid="pay-error" class="text-small text-cinnabar mt-4">{{ payErr }}</p>

    <div v-if="phase === 'form'" class="mt-6 space-y-6">
      <!-- donateMode: ẨN nút unlock + mọi wording giá (C4 — CẤM 29k/unlock khi free) -->
      <button v-if="!donateMode" type="button" data-testid="pay-unlock-btn" class="btn-cinnabar w-full" @click="unlock">
        Mở khóa {{ PRICE_LABEL }}
      </button>

      <section data-testid="pay-donate-block" class="card p-4">
        <h2 class="font-semibold text-h2">{{ donateMode ? 'Mâm lễ' : 'Lễ tùy tâm' }}</h2>
        <p class="text-small text-muted mt-1">
          {{ donateMode ? 'Chọn một mức, hoặc tự ghi số lễ.' : 'Lễ là khích lệ tinh thần, không đổi lấy nội dung.' }}
        </p>
        <!-- [DEV-DONATE-QR] SHOT1 mockup: thẻ mâm lễ 2×2, tag Hán + ghi chú, aria-pressed
             (touch ≥44px, son chỉ cho trạng thái chọn — DESIGN-NOTES, không token mới).
             [FE-TIER-SYNC t_ea138b84] bản V2 đã duyệt: .han-tag GÓC TRÊN-TRÁI (left:8/top:6
             — không phải góc phải như v1), badge ✓ tròn son .ck góc trên-PHẢI (18px),
             nội dung thẻ center — theo mockup-form.html, không tự sáng tạo. -->
        <div class="grid grid-cols-2 gap-2 mt-3" role="group" aria-label="Mức lễ">
          <button
            v-for="t in DONATE_TIERS"
            :key="t.amount"
            type="button"
            data-testid="pay-donate-chip"
            :aria-pressed="donateCustom === '' && donatePick === t.amount ? 'true' : 'false'"
            class="relative flex flex-col items-center justify-center gap-0.5 min-h-[66px] px-2 py-3 rounded-card border text-center transition-shadow hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold active:translate-y-px"
            :class="donateCustom === '' && donatePick === t.amount ? 'border-cinnabar bg-cinnabar/5' : 'border-gold/40'"
            @click="donatePick = t.amount; donateCustom = ''"
          >
            <span class="han-tag absolute left-2 top-1.5 han text-gold/50 text-small" aria-hidden="true">{{ t.han }}</span>
            <span v-if="donateCustom === '' && donatePick === t.amount" class="ck absolute right-2 top-1.5 grid h-[18px] w-[18px] place-items-center rounded-full bg-cinnabar text-paper text-[11px] leading-none" aria-hidden="true">✓</span>
            <span class="text-body font-semibold" :class="donateCustom === '' && donatePick === t.amount ? 'text-cinnabar' : 'text-ink'">
              {{ t.amount.toLocaleString('vi-VN') }}<span class="text-small font-medium" :class="donateCustom === '' && donatePick === t.amount ? 'text-cinnabar' : 'text-muted'">đ</span>
            </span>
            <span class="note text-small font-normal" :class="donateCustom === '' && donatePick === t.amount ? 'text-cinnabar' : 'text-muted'">{{ t.note }}</span>
          </button>
        </div>
        <div class="flex items-center gap-2 mt-3">
          <label class="text-small text-muted shrink-0" for="donate-other">Số khác</label>
          <input
            id="donate-other"
            v-model="donateCustom"
            type="number"
            inputmode="numeric"
            :min="DONATE_MIN"
            :max="DONATE_MAX"
            placeholder="Nhập số tiền"
            aria-label="Số lễ tùy tâm"
            data-testid="pay-donate-input"
            class="w-32 px-2 py-1.5 rounded-card border border-gold/40 bg-paper text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
          />
          <span class="text-small text-muted">đồng</span>
        </div>
        <p class="text-small text-muted mt-1">Giới hạn {{ DONATE_MIN.toLocaleString('vi-VN') }} – {{ DONATE_MAX.toLocaleString('vi-VN') }}đ</p>
        <button type="button" data-testid="pay-donate-btn" class="btn-cinnabar mt-3 w-full" :disabled="!donateValid" @click="donate">
          Gửi lễ
        </button>
        <p v-if="donateMode" class="text-small text-muted text-center mt-2">đồng lễ không mở thêm nội dung</p>
      </section>
    </div>

    <!-- ═══ [DEV-DONATE-QR] NHÁNH DONATE — SHOT2 mockup: QR THẬT + badge chờ,
         cảm ơn CHỈ khi poll #9 bắt được paid ═══ -->
    <div v-else-if="phase === 'qr' && isDonateOrder" class="mt-6" data-testid="pay-donate-qr">
      <p class="text-small text-muted text-center">Lễ tùy tâm · Đơn #{{ order?.order_code }}</p>
      <p class="han text-h1 font-semibold text-center mt-1">
        Lễ <em class="not-italic text-cinnabar">{{ order?.amount_vnd.toLocaleString('vi-VN') }}</em>đ
      </p>
      <PayQr :qr-data="order?.qr_data || ''" :confirm-url="order?.confirm_url || ''" :amount-label="donateAmountLabel" />
      <!-- [SPEC-CHANGE boss 02/09] Nội dung CK = mã trung tính QH<đơn> — FE tự suy từ
           order_code (ckCode), CẤM hiển thị tên app/'Qu+Hom+Nay' (qr_data stub BE còn
           chứa — không in thô). Đối chiếu theo mã đơn. Bank suy từ BIN; chuỗi lạ format
           → chỉ hiện mã CK, không bịa bank. -->
      <div data-testid="pay-donate-ck" class="card p-3 mt-3 text-center text-small">
        <p class="text-ink">Quét mã bằng ứng dụng ngân hàng để chuyển khoản.</p>
        <p v-if="donateCk" class="text-muted mt-1">Ngân hàng <b class="text-ink">{{ bankName(donateCk.bin) }}</b>
          · Nội dung CK <b class="text-ink">{{ ckCode(order?.order_code) }}</b></p>
        <p v-else class="text-muted mt-1">Nội dung CK <b class="text-ink">{{ ckCode(order?.order_code) }}</b></p>
        <p class="text-muted mt-1">Chuyển đúng nội dung ngân hàng để được ghi nhận.</p>
      </div>
      <!-- Badge trạng thái = phần nhìn rõ nhất sau QR (DESIGN-NOTES SHOT2): VÀNG khi chờ -->
      <p
        data-testid="pay-status"
        class="mt-4 mx-auto block w-fit max-w-full px-4 py-2 rounded-card border text-center text-body"
        :class="payState === 'paid' ? 'border-bamboo/60 bg-bamboo/10 text-bamboo'
          : ['expired', 'cancelled'].includes(payState) ? 'border-cinnabar/60 bg-cinnabar/10 text-cinnabar'
          : 'border-gold/45 bg-gold/10 text-ink'"
      >
        <template v-if="['expired', 'cancelled'].includes(payState)">Đơn hết hạn — chưa nhận được lễ. </template>
        <template v-else-if="payState === 'paid'">Đã nhận được lễ — cảm ơn bạn.</template>
        <template v-else>Chưa phải đã gửi — chờ tiền về<small class="block text-small text-muted">tự động kiểm tra mỗi 3 giây</small></template>
      </p>
      <p data-testid="pay-donate-timeout-hint" class="text-small text-muted text-center mt-2">
        Đơn hết hạn sau <b>5 phút</b>. Nếu quá hạn, hãy tạo đơn mới.
        Lễ chỉ là khích lệ tinh thần, không đổi lấy nội dung.
      </p>
      <p v-if="netWarn" data-testid="pay-net-warn" class="text-small text-cinnabar text-center mt-1">
        Mất mạng khi kiểm tra đơn — giữ nguyên mã QR.
      </p>
      <button
        v-if="['expired', 'cancelled'].includes(payState)"
        type="button"
        data-testid="pay-donate-retry"
        class="btn-cinnabar w-full mt-3"
        @click="donateRetry"
      >Gửi lễ lại</button>
      <button
        v-else-if="payState !== 'paid'"
        type="button"
        data-testid="pay-donate-recheck"
        class="btn-outline w-full mt-3"
        @click="retriggerPoll"
      >Kiểm tra lại đơn<span class="block text-small font-normal opacity-80">đã chuyển khoản rồi thì ấn ngay</span></button>
      <p data-testid="pay-stub-note" class="text-small text-muted text-center mt-2">
        <template v-if="order?.stub">Thanh toán tự động đang sắp mở — giai đoạn này chưa thu tiền thật, mô hình chỉ để thử luồng.</template>
        <template v-else>Chuyển đúng nội dung ngân hàng để được ghi nhận.</template>
      </p>
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

    <div v-else-if="phase === 'donated'" class="mt-6" data-testid="pay-donate-thanks">
      <p class="text-body">Cảm ơn bạn đã gửi lễ. Lễ chỉ là khích lệ tinh thần, không đổi lấy nội dung.</p>
      <RouterLink to="/" class="btn-cinnabar inline-block mt-4">Về trang chính</RouterLink>
    </div>
  </div>
</template>
