<script setup>
// TopicGate — 04-ui §3: 3 nhánh render vùng "luận sâu" S3 (04-ui §2.S3 + §4):
// 1) chưa unlock → CTA mở S4; 2) cooldown 429 → đếm ngược retry_after_seconds, nút disabled;
// 3) đã unlock → #5→#6 poll 2s, skeleton 3 đoạn; failed → "bàn cờ im tiếng" + thử lại key mới.
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useCountdown } from '../composables/useCountdown.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { AI_POLL_MS, AI_POLL_MAX_MS, TOPIC_LABELS } from '../constants.js'

const props = defineProps({ drawId: { type: Number, required: true }, topic: { type: String, required: true } })
const router = useRouter()
const device = useDevice()
const cd = useCountdown()

const phase = ref('idle') // idle | queued | running | done | failed | cooldown | cap
const result = ref('')
let pollTimer = null
let pollStart = 0

const unlocked = computed(() => device.entitlements.value.includes(props.topic))
const label = computed(() => TOPIC_LABELS[props.topic] || props.topic)

function stopPoll() {
  if (pollTimer) clearTimeout(pollTimer)
  pollTimer = null
}
onBeforeUnmount(stopPoll)

async function askFresh() {
  stopPoll()
  result.value = ''
  try {
    const r = await api.requestInterpretation({
      draw_id: props.drawId,
      topic: props.topic,
      idempotency_key: crypto.randomUUID().replace(/-/g, '').slice(0, 16), // key mới mỗi lần thử lại
    })
    pollStart = Date.now()
    phase.value = r.data.status || 'queued'
    pollTimer = setTimeout(() => poll(r.data.job_uuid), AI_POLL_MS)
  } catch (e) {
    if (e.code === 'AI_COOLDOWN') {
      phase.value = 'cooldown'
      // E5 t_0285ac01: đồng hồ chạm 0 → về 'idle' (nút enable lại, hết nhãn "00:00").
      // retry_after = 0/âm → nổ callback ngay trong start() → idle, không kẹt. `?? 90`
      // thay `|| 90` để 0 thật từ API không bị biến thành 90.
      cd.start(e.details.retry_after_seconds ?? 90, () => {
        if (phase.value === 'cooldown') phase.value = 'idle'
      })
    } else if (e.code === 'AI_GLOBAL_CAP') {
      phase.value = 'cap'
    } else if (e.code === 'UNLOCK_REQUIRED') {
      await device.refresh()
      if (!device.entitlements.value.includes(props.topic)) phase.value = 'locked'
      else askFresh()
    } else {
      phase.value = 'failed'
    }
  }
}
async function poll(uuid) {
  try {
    const r = await api.aiJob(uuid)
    const j = r.data
    if (j.status === 'done') {
      phase.value = 'done'
      result.value = j.result || ''
      return
    }
    if (j.status === 'failed') {
      phase.value = 'failed'
      return
    }
    phase.value = j.status
    if (Date.now() - pollStart > AI_POLL_MAX_MS) {
      phase.value = 'failed'
      return
    }
    pollTimer = setTimeout(() => poll(uuid), AI_POLL_MS)
  } catch {
    phase.value = 'failed'
  }
}
watch(
  () => [props.drawId, props.topic, unlocked.value],
  () => {
    phase.value = unlocked.value ? 'idle' : 'locked'
  },
  { immediate: true },
)
// khi payment xong → entitlement đổi → tự xin luận
watch(unlocked, (u) => {
  if (u && (phase.value === 'locked' || phase.value === 'idle')) askFresh()
})
</script>

<template>
  <section class="mt-6" data-testid="topic-gate" :data-topic="topic">
    <h3 class="text-h2 font-semibold text-ink mb-2">Luận sâu · {{ label }}</h3>

    <!-- nhánh 1: chưa unlock -->
    <div v-if="phase === 'locked'" data-testid="gate-locked">
      <p class="text-body text-muted mb-3">Chủ đề này cần mở khóa 29.000đ — mở một lần, đọc mãi trên thiết bị này.</p>
      <button
        type="button"
        data-testid="gate-cta-paywall"
        class="btn-cinnabar"
        @click="router.push({ name: 'paywall', params: { topic } })"
      >
        Mở khóa 29.000đ
      </button>
    </div>

    <!-- nhánh 2: cooldown -->
    <div v-else-if="phase === 'cooldown'" data-testid="gate-cooldown">
      <p class="text-body text-muted mb-2">Bạn vừa xin luận giải, nghỉ tay một lát đã.</p>
      <button type="button" class="btn-cinnabar" data-testid="gate-ask" disabled>
        Xin luận sâu — {{ cd.formatted.value }}
      </button>
    </div>
    <div v-else-if="phase === 'cap'" data-testid="gate-cap">
      <p class="text-body text-muted">Đông người đang xin luận, thử lại sau ít phút.</p>
      <button type="button" class="btn-cinnabar" data-testid="gate-ask" disabled>Xin luận sâu</button>
    </div>

    <!-- nhánh 3: đã unlock -->
    <template v-else>
      <button
        v-if="phase === 'idle'"
        type="button"
        class="btn-cinnabar"
        data-testid="gate-ask"
        @click="askFresh"
      >
        Xin luận sâu
      </button>
      <div v-else-if="phase === 'queued' || phase === 'running'" data-testid="gate-skeleton" class="space-y-2">
        <div class="sk h-4 w-11/12" />
        <div class="sk h-4 w-full" />
        <div class="sk h-4 w-3/4" />
      </div>
      <div v-else-if="phase === 'failed'" data-testid="gate-failed">
        <p class="text-body text-muted mb-2">Hôm nay bàn cờ im tiếng, thử lại nhé.</p>
        <button type="button" class="btn-cinnabar" data-testid="gate-retry" @click="askFresh">Thử lại</button>
      </div>
      <article v-else-if="phase === 'done'" data-testid="gate-result" class="prose-quhe text-body text-ink whitespace-pre-wrap">
        {{ result }}
      </article>
    </template>
  </section>
</template>

<style scoped>
.sk {
  border-radius: 9px;
  background: linear-gradient(90deg, var(--tw-gradient-from, #efe6d3), #f7f2e7, #efe6d3);
  animation: sk 1.2s ease-in-out infinite;
}
@keyframes sk {
  0% { opacity: 0.6; }
  50% { opacity: 1; }
  100% { opacity: 0.6; }
}
</style>
