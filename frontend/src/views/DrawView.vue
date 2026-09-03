<script>
export default { name: 'DrawView' } // router.test chốt tên component (lazy resolve)
</script>
<script setup>
// S2 Draw — 04-ui §2.S2 (BẤT BIẾN C-08): call #3 song song với animation,
// UI KHÔNG reveal kết quả trước 1500ms. Lỗi DRAW_LIMIT_REACHED → về S1 + toast (§4).
// FE-1: #3 về → prime cache #2 (data.hexagram tách data.draw) để S3 zero-fetch;
// lỗi khác 409 (mạng/500) → draw-error + nút thử lại, không trắng hành động (§4).
// UXR-4a (t_0c74b51e): lớp brand + nghi thức — ĐX1 header 3 điểm thay "← Về"
// (DrawHeader.vue, App.vue KHÔNG đổi — NAV-SPEC §1c giữ), ĐX2 trạng thái đã-gieo
// đọc #1 qua store useDevice dùng chung (load() cache toàn module — không race 2
// request song song, RULES-DETAIL §D), ĐX3 preview giá trị (HOME_COPY.steps nguyên
// văn), A1/ĐX6 sân gieo ghost tĩnh (token MagicSequence, aria-hidden, không
// animation — không reveal dữ liệu → không chạm C-08), A2 triện 卦 + H1 brand,
// C1 giờ quẻ client-side new Date() tại lúc bấm (CEO bác vế Âm lịch @t_UXR3).
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useHexagrams } from '../composables/useHexagrams.js'
import { useHaoTexts } from '../composables/useHaoTexts.js'
import { useDevice } from '../composables/useDeviceApi.js'
import MagicSequence from '../components/MagicSequence.vue'
import DrawHeader from '../components/DrawHeader.vue'
import DrawGhostField from '../components/DrawGhostField.vue'
import { MAGIC_SEQUENCE_MS, AUTO_PUSH_S3_MS, HOME_COPY, DRAW_COPY } from '../constants.js'

const emit = defineEmits(['revealed'])
const router = useRouter()
const hxlib = useHexagrams()
const haolib = useHaoTexts()
const device = useDevice()
const phase = ref('idle') // idle | rolling
const result = ref(null) // { draw, hexagram }
const pending = ref(false)
const rollErr = ref('') // lỗi #3 không phải 409 — rỗng = không lỗi
const done = ref(false) // MagicSequence đã qua mốc 1500ms (C-08: không reveal sớm)
const castTime = ref('') // C1 "HH:MM" giờ MÁY KHÁCH tại lúc bấm — dấu mực, không lời bình

const todayDraw = device.todayDraw // #1 store (null khi chưa gieo/LỖI → giữ nút đỏ)
const drawn = computed(() => Boolean(todayDraw.value))

// hào tạm cho nghi thức khi API chưa kịp về (reveal chỉ dùng số liệu THẬT)
const PLACEHOLDER = [7, 8, 7, 7, 7, 7]
const lines = computed(() => result.value?.draw?.lines_rolled || PLACEHOLDER)

// sân gieo tinh (A1) đã về component riêng DrawGhostField.vue

onMounted(() => {
  // load() dedupe sẵn trong store (state.me có → return ngay) — DrawView không bắn
  // request thứ 2 song song với NavBar; lỗi #1 im lặng mặc định chưa gieo (không trắng).
  device.load().catch(() => {})
})

let routed = false
async function roll() {
  if (phase.value !== 'idle') return
  phase.value = 'rolling'
  pending.value = true
  rollErr.value = ''
  const now = new Date() // C1: ghim giờ máy khách ĐÚNG lúc bấm, không suy từ API
  castTime.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  api
    .createDraw()
    .then((r) => {
      result.value = r.data
      if (r.data?.hexagram) hxlib.prime(r.data.hexagram) // S3 đọc cache, không xin #2 lại
      if (r.data?.hexagram?.id && Array.isArray(r.data.hao_texts)) haolib.prime(r.data.hexagram.id, r.data.hao_texts) // FE-3XU: S3 zero-fetch #2b
      pending.value = false
      tryGo()
    })
    .catch((e) => {
      pending.value = false
      if (e.code === 'DRAW_LIMIT_REACHED') {
        router.replace({ name: 'home', query: { toast: 'draw_limit' } })
      } else {
        phase.value = 'idle'
        result.value = null
        rollErr.value = e.code === 'NETWORK' ? 'Mất mạng — chưa gieo được.' : 'Gieo quẻ thất bại. Thử lại nhé.'
      }
    })
}
function onDone() {
  done.value = true // MagicSequence qua mốc 1500ms — từ đây mới được reveal
  // request chậm hơn 1.5s: spinner nối mạch trên khung reveal, chưa nhảy màn
  if (!result.value) pending.value = true
  tryGo()
}
function tryGo() {
  if (routed || !done.value || !result.value) return
  routed = true
  emit('revealed')
  // B3: auto-push S3 sau khi reveal (giữ nhịp nhìn symbol — CFG-FE: nhịp về constants)
  setTimeout(() => {
    router.push({ name: 'detail', params: { drawId: result.value.draw.id } })
  }, AUTO_PUSH_S3_MS)
}
</script>

<template>
  <section class="min-h-[80dvh] flex flex-col items-center justify-center px-gutter relative pt-14" data-testid="draw-frame">
    <!-- ĐX1: header nghi thức 3 điểm thay trọn "← Về" — rolling chỉ brand (ẩn link). -->
    <DrawHeader :rolling="phase === 'rolling'" />

    <template v-if="phase === 'idle'">
      <!-- A2: triện 卦 son nhỏ — brand nằm TRONG khung hình (≤48px, motif Á đông) -->
      <span data-testid="draw-seal" class="draw-seal han mb-4" aria-hidden="true">卦</span>
      <p class="text-muted text-body mb-6 text-center max-w-sm">
        Một quẻ mỗi ngày. Hơi thở đều, nghĩ một câu hỏi rõ ràng.
      </p>

      <!-- ĐX2: đã gieo hôm nay (#1 today_draw) → nút phụ + dòng hẹn; chống bấm vào nút chết -->
      <template v-if="drawn">
        <RouterLink
          data-testid="draw-today"
          :to="`/que/${todayDraw.id}`"
          class="btn-line text-h2 no-underline text-center"
          >{{ DRAW_COPY.todayBtn }}</RouterLink
        >
        <p class="text-small text-muted mt-3 text-center">{{ DRAW_COPY.drawnNote }}</p>
      </template>
      <template v-else>
        <button type="button" class="btn-cinnabar text-h2" data-testid="draw-start" @click="roll">
          Tâm tĩnh, chạm để gieo
        </button>
        <!-- ĐX2: chip quota/miss-free TRƯỚC khi bấm (bằng chứng FREE_DEEP_LABEL + paywall OFF) -->
        <p data-testid="draw-quota-note" class="chip-status text-small text-muted mt-3">{{ DRAW_COPY.quotaNote }}</p>
      </template>
      <div v-if="rollErr" data-testid="draw-error" class="mt-4 text-center" role="alert">
        <p class="text-cinnabar text-small">{{ rollErr }}</p>
        <button type="button" data-testid="draw-retry" class="btn-line mt-2" @click="roll">Gieo lại</button>
      </div>

      <!-- ĐX3: preview giá trị — 3 dòng nguyên văn HOME_COPY.steps (boss duyệt mắt 02/09) -->
      <section v-if="!drawn" data-testid="draw-preview" class="mt-10 w-full max-w-sm text-center">
        <h2 class="text-small font-semibold text-muted uppercase tracking-[0.08em]">{{ DRAW_COPY.previewHead }}</h2>
        <ol class="list-none p-0 mt-3 flex flex-col gap-3">
          <li
            v-for="(s, i) in HOME_COPY.steps"
            :key="s.t"
            :data-testid="`draw-preview-step-${i + 1}`"
            class="text-small text-muted leading-relaxed"
          >
            <span class="han text-gold" aria-hidden="true">{{ s.no }}</span>
            <span> {{ s.t }} — {{ s.d }}</span>
          </li>
        </ol>
      </section>

      <!-- A1/ĐX6: sân gieo ghost-lines TĨNH (component riêng, token MagicSequence) -->
      <DrawGhostField class="mt-10" />
    </template>

    <div v-else class="relative w-full max-w-md flex flex-col items-center gap-6">
      <MagicSequence
        :duration-ms="MAGIC_SEQUENCE_MS"
        :lines="lines"
        :symbol="result?.hexagram?.symbol || '䷀'"
        :ten="result?.hexagram?.ten || 'Đang gieo…'"
        @done="onDone"
      />
      <p v-if="pending" data-testid="draw-spinner" class="text-small text-muted animate-pulse">
        Đang mở quẻ…
      </p>
      <div v-else-if="result && done" data-testid="draw-result" class="text-center">
        <!-- C1: giờ quẻ là DẤU MỰC (wording UXR-W mục 5) — cấm thêm lời bình giờ.
             Nhánh nút quyền chọn + link giữ thẻ = UXR-4b (B1/ĐX5), chưa ở đây. -->
        <p class="text-body text-muted">{{ result.hexagram.ten }} — {{ DRAW_COPY.castAt(castTime) }}</p>
      </div>
    </div>

    <!-- A2: H1 brand chân trang trong vùng web (ngay trên DisclaimerBar toàn cục) -->
    <h1 data-testid="draw-foot-brand" class="han text-small font-medium text-muted tracking-[0.12em] absolute bottom-2 inset-x-0 text-center">
      {{ DRAW_COPY.brand }}
    </h1>
  </section>
</template>

<style scoped>
/* triện 卦 — vuông son bo góc nhỏ, chữ trắng serif, nghiêng nhẹ như đóng tay (A2 ≤48px) */
.draw-seal {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 6px;
  background: #b33a2b;
  color: #f7f2e7;
  font-size: 26px;
  font-weight: 700;
  line-height: 1;
  transform: rotate(-3deg);
  opacity: 0.9;
  box-shadow: 0 1px 3px rgb(30 27 24 / 0.25);
}
/* sân gieo tinh: CSS về DrawGhostField.vue (component riêng) */
</style>
