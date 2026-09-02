<script setup>
// TopicGate — 04-ui §3: 3 nhánh render vùng "luận sâu" S3 (04-ui §2.S3 + §4):
// 1) chưa unlock → CTA mở S4; 2) cooldown 429 → đếm ngược retry_after_seconds, nút disabled;
// 3) đã unlock → #5→#6 poll 2s, skeleton 3 đoạn; failed → "bàn cờ im tiếng" + thử lại key mới.
// REVIEW-LUAN (card t_b8df14e5, BOSS-GO 02/09 mục 1): chủ đề ĐÃ luận xong (BE 409
// AI_ALREADY_DONE / #5b exists=true) → phase 'saved': chỉ còn nút "Xem lại", ẩn hẳn ô
// question + chip + "Xin luận sâu" — mỗi (quẻ, topic) chỉ luận 1 lần, đọc lại KHÔNG đốt
// tiền AI. Lượt THẤT BẠI không bị khóa: gate-retry giữ nguyên, chỉ 409 mới sang saved.
import { ref, computed, watch, onBeforeUnmount } from 'vue'
// LUAN-V2 §6c (t_dec9349a): lane render-chính-thức — cắt marker thô.
import { parseLuan } from '../utils/luanRender'
import { useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useCountdown } from '../composables/useCountdown.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { AI_POLL_MS, AI_POLL_MAX_MS, TOPIC_LABELS, PRICE_LABEL, QUESTION_MAX, QUESTION_SUGGESTIONS } from '../constants.js'

const props = defineProps({ drawId: { type: Number, required: true }, topic: { type: String, required: true } })
const router = useRouter()
const device = useDevice()
const cd = useCountdown()

const phase = ref('idle') // idle | submitting | queued | running | done | saved | failed | cooldown | cap | locked
const result = ref('')
// REVIEW-LUAN: bài #5b lấy về lúc probe (job_uuid + result + completed_at). `savedMeta`
// null → chưa có bài lưu. `reviewOpen` = khách đã bấm "Xem lại" chưa — hai state tách
// nhau CÓ CHỦ ĐÍCH: phase 'saved' một mình chỉ là cái nút, bài chỉ hiện khi bấm
// (đọc lại là hành động chủ ý, không tự trải bài cũ ra như vừa luận xong).
const savedMeta = ref(null)
const reviewOpen = ref(false)
let probeSeq = 0 // chống race: probe topic cũ về muộn không được đè topic mới
// LUAN-V2 §7 (card t_b13fd2b9): ô "Bạn đang vướng chuyện gì?" — tùy chọn, tối đa
// QUESTION_MAX ký tự. Ref sống độc lập với phase → retry/fail không mất nội dung.
const question = ref('')
// BUG-LUANV2-01 §7.4.4 (card t_d4cfddea): bản ĐÃ TRIM của câu hỏi lúc bấm gửi —
// snapshot để lặp lại "Bạn hỏi: …" trên đầu bài. FE-local, cố ý: 03-api §6 chốt
// payload #6 đúng 7 field và QuestionCacheTest phía BE CHỐNG lọt question ra API
// (ẩn PII, cùng nguyên tắc F7) — nên nguồn là state của chính người vừa hỏi.
// jobQuestion = kênh phụ: nếu dev-lead duyệt thêm field `question` vào #6 sau này
// (job cache-hit/replay), poll nhặt về mà không phải đổi gì thêm ở tầng render.
const askedQuestion = ref('')
const jobQuestion = ref('')
let pollTimer = null
let pollStart = 0
// LUAN-RACE-FE (card t_debf4bbf, bệnh án BUG-LUAN-RACE-20260902): generator ngữ cảnh
// (drawId, topic, unlocked). stopPoll() cũ chỉ cancel setTimeout KẾ TIẾP — mọi fetch đã
// in-flight (poll #6, POST #5) vẫn về sau và ghi phase/result của tab CŨ lên tab MỚI
// (bài topic cũ chảy sang, "chỉ xem lại được luận sâu tình duyên"). Mỗi lần đổi ngữ
// cảnh: gen++; callback mang myGen snapshot lúc bắn request, về mà myGen !== gen →
// IM LẶNG tuyệt đối (không phase, không result, không reschedule). Bài cũ không mất:
// BE đã lưu theo (draw,topic), quay lại tab → probe #5b trả saved. ĐỘC LẬP với
// probeSeq (guard riêng của probeSaved — giữ nguyên, không trộn 2 guard).
let gen = 0

const unlocked = computed(() => device.entitlements.value.includes(props.topic))
const label = computed(() => TOPIC_LABELS[props.topic] || props.topic)
// D3: chip = gói gợi ý text cho topic của TAB hiện tại, chỉ điền vào ô — không đổi topic API.
const chips = computed(() => QUESTION_SUGGESTIONS[props.topic] || [])
const questionLen = computed(() => [...question.value].length) // đếm unicode như BE mb_strlen
// LUAN-V2 D4 (§4.1): trim TRƯỚC khi gửi — rỗng → undefined để client.js KHÔNG dựng key
// `question` (giữ nhánh cache question-NULL phía BE). BE hash trên giá trị đã trim.
const normalizedQuestion = computed(() => question.value.trim() || undefined)
// §7.4.4: dòng hỏi hiển thị = snapshot đã trim lúc gửi, hoặc field question của job
// (kênh phụ tương lai) — rỗng/whitespace → null → không render (test chốt).
const displayedQuestion = computed(() => (askedQuestion.value || jobQuestion.value || '').trim() || null)
// LUAN-V2 §6c (t_dec9349a): bài luận (raw hoặc JSON envelope {result}) → cắt marker
// thô [Hoàn cảnh]/[Vì sao]/[Việc nên làm] thành heading + body sạch.
const luanBlocks = computed(() => parseLuan(result.value))

function stopPoll() {
  if (pollTimer) clearTimeout(pollTimer)
  pollTimer = null
}
// Unmount: gen++ để mọi callback về muộn chặn luôn — Vue KHÔNG tự vô hiệu hóa việc
// ghi ref sau unmount (ref vẫn sống), nên cờ này là bắt buộc chứ không phải thừa.
onBeforeUnmount(() => { gen++; stopPoll() })

// REVIEW-LUAN (card mục 2): #5b probe 1 lần mỗi lần mount/đổi tab khi đã unlock.
// exists=true → phase 'saved' (chỉ nút "Xem lại"). exists=false / lỗi mạng / 404 /
// 402 race → mặc nhiên 'idle': khách chưa mua đường hỏi thường không được bị chặn
// bởi một API phụ. seq-guard: kết quả probe cũ (topic khác) bị loại.
async function probeSaved() {
  const seq = ++probeSeq
  let d = null
  try {
    const r = await api.savedInterpretation({ draw_id: props.drawId, topic: props.topic })
    d = r && r.data
  } catch {
    d = null
  }
  if (seq !== probeSeq) return false
  if (d && d.exists) {
    enterSaved(d)
    return true
  }
  return false
}
function enterSaved(meta) {
  cd.stop()
  stopPoll()
  savedMeta.value = meta
  reviewOpen.value = false
  result.value = meta.result || ''
  // khóa kênh phụ question của lượt cũ — saved API CẤM trả question (F7), nên dòng
  // "Bạn hỏi:" trong vùng bài lưu chỉ có thể đến từ state local → đưa hết về rỗng.
  askedQuestion.value = ''
  jobQuestion.value = ''
  phase.value = 'saved'
}
// Bấm "Xem lại": KHÔNG đụng POST #5 — render thẳng kết quả đã lấy về, cùng lane
// luanBlocks như phase done (card mục 3 — mục tiêu tiết kiệm chi phí AI của boss).
function reviewSaved() {
  reviewOpen.value = true
}

async function askFresh() {
  // LUAN-RACE: snapshot gen TRƯỚC await đầu. Branch sync (đặt phase='submitting'...
  // trước await #5) giữ nguyên hành vi ANIM-LUAN A1 — chỉ mọi thứ SAU await mới phải
  // qua kiểm tra myGen === gen.
  const myGen = gen
  // ANIM-LUAN A (card t_74502491 mục 1): 'submitting' NGAY dòng đầu, TRƯỚC await #5 —
  // bản cũ chỉ đổi phase sau khi POST về nên mạng chậm tưởng bấm không ăn. Mọi lần bấm
  // CTA (gate-ask idle lẫn gate-retry failed) đều vào đây → chờ ≤100ms đầu có state.
  phase.value = 'submitting'
  stopPoll()
  result.value = ''
  // §7.4.4: snapshot BẢN GỬI ĐI (đã trim qua normalizedQuestion — D4) ngay lúc bấm —
  // whitespace-only → '' → không hiện dòng hỏi kể cả khi khách gõ lại sau đó.
  // Lượt mới = ý định mới: xóa kênh phụ của job lượt trước, chống giá trị cũ.
  askedQuestion.value = normalizedQuestion.value ?? ''
  jobQuestion.value = ''
  try {
    const r = await api.requestInterpretation({
      draw_id: props.drawId,
      topic: props.topic,
      idempotency_key: crypto.randomUUID().replace(/-/g, '').slice(0, 16), // key mới mỗi lần thử lại
      question: normalizedQuestion.value, // LUAN-V2 D4: trim sẵn ở component; rỗng → undefined,
      // client.js không dựng key — payload sạch kể cả đường mock/test contract.
    })
    if (myGen !== gen) return // đổi tab lúc POST in-flight → im lặng, không dựng poll job cũ
    pollStart = Date.now()
    phase.value = r.data.status || 'queued'
    pollTimer = setTimeout(() => poll(r.data.job_uuid, myGen), AI_POLL_MS)
  } catch (e) {
    if (myGen !== gen) return // mọi nhánh catch đều là SAU await → guard trước hết
    // REVIEW-LUAN card mục 5 — THỨ TỰ ƯU TIÊN: 409 AI_ALREADY_DONE kiểm TRƯỚC khi
    // map cooldown/cap (BE t_5f98fe73 đặt gate khóa trước cooldown nên 409 có thể
    // mang cả details của cooldown — không được để nó bị nuốt thành 'cooldown').
    // Gọi lại #5b lấy result thật (card mục 4); saved im lặng/không tồn tại → về
    // 'failed' có đường retry, KHÔNG kẹt màn trắng.
    if (e.code === 'AI_ALREADY_DONE') {
      const found = await probeSaved()
      if (myGen !== gen) return // probe về sau khi đổi tab lần nữa → bỏ, mặc watch mới
      if (!found) phase.value = 'failed'
      return
    }
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
      if (myGen !== gen) return
      if (!device.entitlements.value.includes(props.topic)) phase.value = 'locked'
      else askFresh()
    } else {
      phase.value = 'failed'
    }
  }
}
async function poll(uuid, myGen) {
  // LUAN-RACE: đầu mỗi callback — vòng poll của job cũ PHẢI chết hẳn khi gen đổi.
  if (myGen !== gen) return
  try {
    const r = await api.aiJob(uuid)
    if (myGen !== gen) return // fetch về sau khi đổi tab: không gán gì, không lên lịch tiếp
    const j = r.data
    // kênh phụ §7.4.4: nếu #6 có trả `question` (chưa — 03-api §6 chốt 7 field),
    // nhặt về hiển thị cho job replay/cache-hit; field vắng → giữ snapshot local.
    if (typeof j.question === 'string') jobQuestion.value = j.question
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
    pollTimer = setTimeout(() => poll(uuid, myGen), AI_POLL_MS)
  } catch {
    if (myGen !== gen) return
    phase.value = 'failed'
  }
}
// REVIEW-LUAN card mục 2+6 (t_b8df14e5): MỘT watch reset duy nhất cho
// (drawId, topic, unlocked) — unlocked → về 'idle' rồi probe #5b 1 lần cho key mới;
// exists=true → 'saved' (nút "Xem lại"), KHÔNG bao giờ để bài cũ ẩn mà còn đường
// bấm xin luận lần 2. Chuyển khóa cũ→mới (vừa trả tiền) và probe trắng → tự xin
// luận như UX cũ (watch `unlocked` rời nhập vào đây). Trước đây mỗi lần đổi tab
// bài cũ bị xóa về idle — đúng bệnh boss bắt sửa.
watch(
  () => [props.drawId, props.topic, unlocked.value],
  async ([, , nowUnlocked], old) => {
    gen++ // LUAN-RACE: đóng băng mọi callback in-flight của ngữ cảnh CŨ...
    stopPoll()
    const myGen = gen // ...và cả phần SAU await của chính watch này nếu tab đổi tiếp.
    const wasLocked = old ? !old[2] : false
    savedMeta.value = null
    reviewOpen.value = false
    if (!nowUnlocked) {
      phase.value = 'locked'
      return
    }
    phase.value = 'idle'
    const found = await probeSaved()
    if (found) return
    if (myGen !== gen) return // đổi tab trong lúc probe → watch cũ dừng, watch mới lo
    if (phase.value === 'idle' && wasLocked) askFresh()
  },
  { immediate: true },
)
</script>

<template>
  <section class="mt-6" data-testid="topic-gate" :data-topic="topic">
    <h3 class="text-h2 font-semibold text-ink mb-2">Luận sâu · {{ label }}</h3>

    <!-- nhánh 1: chưa unlock -->
    <div v-if="phase === 'locked'" data-testid="gate-locked">
      <p class="text-body text-muted mb-3">Chủ đề này cần mở khóa {{ PRICE_LABEL }} — mở một lần, đọc mãi trên thiết bị này.</p>
      <button
        type="button"
        data-testid="gate-cta-paywall"
        class="btn-cinnabar"
        @click="router.push({ name: 'paywall', params: { topic } })"
      >
        Mở khóa {{ PRICE_LABEL }}
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
      <!-- REVIEW-LUAN (card t_b8df14e5): chủ đề ĐÃ luận xong → CHỈ nút "Xem lại" —
           ô question/chip/"Xin luận sâu" ẩn tuyệt đối (đường 2 không tồn tại ở đây).
           CTA vẫn btn-cinnabar: nút này là phần tử có trọng lượng thị giác cao nhất
           của màn, đúng nhịp các nhánh còn lại. Bấm = render bài lưu, 0 POST #5. -->
      <div v-if="phase === 'saved'" class="flex flex-col gap-2">
        <button
          v-if="!reviewOpen"
          type="button"
          class="btn-cinnabar self-start"
          data-testid="gate-review"
          @click="reviewSaved"
        >
          Xem lại
        </button>
        <article v-else data-testid="gate-result" class="luan-fade">
          <!-- Nhãn chống nhầm "vừa luận mới": chip-status (token có sẵn) đứng ĐẦU bài. -->
          <span
            data-testid="gate-saved-label"
            class="chip-status text-muted mb-2 inline-flex"
          >Bài đã lưu trước đó</span>
          <!-- saved API không trả question (F7) → displayedQuestion rỗng có chủ đích,
               không dòng "Bạn hỏi:" — card mục 2. -->
          <p
            v-if="displayedQuestion"
            data-testid="gate-result-question"
            class="text-small text-muted mb-2"
          >Bạn hỏi: {{ displayedQuestion }}</p>
          <div data-testid="luan-rendered">
            <template v-for="(b, i) in luanBlocks" :key="i">
              <h4 v-if="b.heading" data-testid="luan-heading" class="han text-h2 font-semibold text-ink mt-4 mb-1">{{ b.heading }}</h4>
              <p v-if="b.text" data-testid="luan-body" class="prose-quhe text-body text-ink whitespace-pre-wrap">{{ b.text }}</p>
            </template>
          </div>
        </article>
      </div>
      <!-- LUAN-V2 §7.1–7.2 (t_b13fd2b9): ô vướng + chip gợi ý — ĐỨNG TRƯỚC CTA
           "Xin luận sâu" (CTA vẫn là phần tử có trọng lượng thị giác cao nhất màn).
           Chip theo D3: chỉ điền text, không đổi topic API.
           ANIM-LUAN A (t_74502491 mục 2): block hỏi SỐNG NGUYÊN trong DOM cả lúc
           'submitting' — thay nhãn/spinner trên chính nút, không mount/unmount khối
           → hết cảnh layout jump khi mạng chậm. -->
      <div v-if="phase === 'idle' || phase === 'submitting'" class="mb-4">
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
        v-if="phase === 'idle' || phase === 'submitting'"
        type="button"
        class="btn-cinnabar"
        data-testid="gate-ask"
        :disabled="phase === 'submitting'"
        :aria-busy="phase === 'submitting' ? 'true' : undefined"
        @click="askFresh"
      >
        <!-- ANIM-LUAN A1: phản hồi TỨC THÌ — disabled + nhãn "Đang xin luận…" + spinner
             CSS thuần (border + @keyframes, màu currentColor kế thừa chữ trắng trên nền
             cinnabar — không hex mới, không lib). -->
        <span v-if="phase === 'submitting'" class="inline-flex items-center gap-2">
          <span data-testid="gate-submit-spinner" class="gate-spinner" aria-hidden="true"></span>
          Đang xin luận…
        </span>
        <span v-else>Xin luận sâu</span>
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
      <article v-else-if="phase === 'done'" data-testid="gate-result" class="luan-fade">
        <!-- LUAN-V2 §7.4.4 (t_d4cfddea): câu hỏi lặp lại 1 dòng NHỎ trên đầu bài —
             token có sẵn (text-small/text-muted như counter/chip), CẤM style mới. -->
        <p
          v-if="displayedQuestion"
          data-testid="gate-result-question"
          class="text-small text-muted mb-2"
        >Bạn hỏi: {{ displayedQuestion }}</p>
        <!-- §6c: mỗi marker → heading + body, marker KHÔNG lọt text; khối trước
             marker đầu tiên (model dẫn thừa) → heading rỗng, không mất chữ. -->
        <div data-testid="luan-rendered">
          <template v-for="(b, i) in luanBlocks" :key="i">
            <h4 v-if="b.heading" data-testid="luan-heading" class="han text-h2 font-semibold text-ink mt-4 mb-1">{{ b.heading }}</h4>
            <p v-if="b.text" data-testid="luan-body" class="prose-quhe text-body text-ink whitespace-pre-wrap">{{ b.text }}</p>
          </template>
        </div>
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
/* ANIM-LUAN mức A (card t_74502491, BOSS-GO 02/09 mục 3) — 2 hiệu ứng, 0 lib, 0 màu mới:
   1) .luan-fade: bài luận (phase done VÀ bài "Xem lại" saved) mở mượt opacity 0→1 +
      trồi nhẹ 6px, 280ms ease-out — đúng một nhịp "hôi" của ấn thư đóng xuống giấy.
   2) .gate-spinner: phản hồi tức thì lúc submitting — vòng border quay, màu kế thừa
      currentColor của nhãn nút (chữ paper trên nền cinnabar), không hex ngoài token. */
.luan-fade {
  animation: luan-fade 280ms ease-out both;
}
@keyframes luan-fade {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.gate-spinner {
  display: inline-block;
  width: 0.85em;
  height: 0.85em;
  border: 2px solid currentColor;
  border-top-color: transparent; /* vòng hở 1 cung — spinner thuần CSS */
  border-radius: 9999px;
  animation: gate-spin 0.8s linear infinite;
}
@keyframes gate-spin {
  to { transform: rotate(360deg); }
}
/* A11y bắt buộc (card mục 3): khách giảm động tác → fade/spinner quay tắt hẳn,
   state chờ vẫn đọc được bằng nhãn "Đang xin luận…" + nút disabled. */
@media (prefers-reduced-motion: reduce) {
  .luan-fade { animation: none; }
  .gate-spinner { animation: none; }
}
</style>
