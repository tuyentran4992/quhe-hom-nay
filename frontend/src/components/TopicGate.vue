<script setup>
// TopicGate — 04-ui §3: 3 nhánh render vùng "luận sâu" S3 (04-ui §2.S3 + §4):
// 1) chưa unlock → CTA mở S4; 2) cooldown 429 → đếm ngược retry_after_seconds, nút disabled;
// 3) đã unlock → #5→#6 poll 2s, skeleton 3 đoạn; failed → "bàn cờ im tiếng" + thử lại key mới.
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useCountdown } from '../composables/useCountdown.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { AI_POLL_MS, AI_POLL_MAX_MS, TOPIC_LABELS, QUESTION_MAX, QUESTION_SUGGESTIONS } from '../constants.js'

const props = defineProps({ drawId: { type: Number, required: true }, topic: { type: String, required: true } })
const router = useRouter()
const device = useDevice()
const cd = useCountdown()

const phase = ref('idle') // idle | queued | running | done | failed | cooldown | cap
const result = ref('')
// LUAN-V2 §7 (card t_b13fd2b9): ô "Bạn đang vướng chuyện gì?" — tùy chọn, tối đa
// QUESTION_MAX ký tự. Ref sống độc lập với phase → retry/fail không mất nội dung.
const question = ref('')
let pollTimer = null
let pollStart = 0

const unlocked = computed(() => device.entitlements.value.includes(props.topic))
const label = computed(() => TOPIC_LABELS[props.topic] || props.topic)
// D3: chip = gói gợi ý text cho topic của TAB hiện tại, chỉ điền vào ô — không đổi topic API.
const chips = computed(() => QUESTION_SUGGESTIONS[props.topic] || [])
const questionLen = computed(() => [...question.value].length) // đếm unicode như BE mb_strlen
// LUAN-V2 D4 (§4.1): trim TRƯỚC khi gửi — rỗng → undefined để client.js KHÔNG dựng key
// `question` (giữ nhánh cache question-NULL phía BE). BE hash trên giá trị đã trim.
const normalizedQuestion = computed(() => question.value.trim() || undefined)

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
      question: normalizedQuestion.value, // LUAN-V2 D4: trim sẵn ở component; rỗng → undefined,
      // client.js không dựng key — payload sạch kể cả đường mock/test contract.
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
      <!-- LUAN-V2 §7.1–7.2 (t_b13fd2b9): ô vướng + chip gợi ý — ĐỨNG TRƯỚC CTA
           "Xin luận sâu" (CTA vẫn là phần tử có trọng lượng thị giác cao nhất màn).
           Chip theo D3: chỉ điền text, không đổi topic API. -->
      <div v-if="phase === 'idle'" class="mb-4">
        <label for="gate-question-input" class="block text-body text-ink mb-1">
          Bạn đang vướng chuyện gì?
        </label>
        <textarea
          id="gate-question-input"
          v-model="question"
          :maxlength="QUESTION_MAX"
          placeholder="Bạn đang vướng chuyện gì? (không bắt buộc)"
          rows="3"
          data-testid="gate-question"
          class="w-full rounded-card border border-gold/40 bg-paper px-3 py-2 text-body text-ink placeholder:text-muted/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
        ></textarea>
        <div class="flex items-center justify-between gap-3 mt-1">
          <div role="group" aria-label="Gợi ý câu hỏi" class="flex flex-wrap gap-2">
            <button
              v-for="c in chips"
              :key="c"
              type="button"
              data-testid="gate-question-chip"
              class="chip-status text-muted hover:border-cinnabar hover:text-cinnabar focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
              @click="question = c"
            >{{ c }}</button>
          </div>
          <span
            data-testid="gate-question-counter"
            class="shrink-0 text-small tabular-nums"
            :class="questionLen >= QUESTION_MAX ? 'text-cinnabar' : 'text-muted'"
            aria-live="polite"
          >{{ questionLen }}/{{ QUESTION_MAX }}</span>
        </div>
      </div>
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
