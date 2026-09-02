<script>
export default { name: 'DrawView' }
</script>
<script setup>
// S2 Draw — 04-ui §2.S2 (BẤT BIẾN C-08): call #3 song song với animation,
// UI KHÔNG reveal kết quả trước 1500ms. Lỗi DRAW_LIMIT_REACHED → về S1 + toast (§4).
// FE-1: #3 về → prime cache #2 (data.hexagram tách data.draw) để S3 zero-fetch;
// lỗi khác 409 (mạng/500) → draw-error + nút thử lại, không trắng hành động (§4).
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useHexagrams } from '../composables/useHexagrams.js'
import { useHaoTexts } from '../composables/useHaoTexts.js'
import MagicSequence from '../components/MagicSequence.vue'
import { MAGIC_SEQUENCE_MS, AUTO_PUSH_S3_MS } from '../constants.js'

const emit = defineEmits(['revealed'])
const router = useRouter()
const hxlib = useHexagrams()
const haolib = useHaoTexts()
const phase = ref('idle') // idle | rolling
const result = ref(null) // { draw, hexagram }
const pending = ref(false)
const rollErr = ref('') // lỗi #3 không phải 409 — rỗng = không lỗi
const done = ref(false) // MagicSequence đã qua mốc 1500ms (C-08: không reveal sớm)

// hào tạm cho nghi thức khi API chưa kịp về (reveal chỉ dùng số liệu THẬT)
const PLACEHOLDER = [7, 8, 7, 7, 7, 7]
const lines = computed(() => result.value?.draw?.lines_rolled || PLACEHOLDER)

let routed = false
async function roll() {
  if (phase.value !== 'idle') return
  phase.value = 'rolling'
  pending.value = true
  rollErr.value = ''
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
  <section class="min-h-[80dvh] flex flex-col items-center justify-center px-gutter relative" data-testid="draw-frame">
    <!-- NAV-SPEC §1c: màn nghi thức KHÔNG có shell nav → thay bằng nút Về góc trái
         để người dùng không bị lạc (muốn về home/Sổ quẻ trước đây phải dùng back trình duyệt). -->
    <RouterLink
      data-testid="draw-back"
      to="/"
      class="absolute top-4 left-4 no-underline text-small text-muted hover:text-ink"
      aria-label="Về trang chính"
      >← Về</RouterLink
    >
    <template v-if="phase === 'idle'">
      <p class="text-muted text-body mb-6 text-center max-w-sm">
        Một quẻ mỗi ngày. Hơi thở đều, nghĩ một câu hỏi rõ ràng.
      </p>
      <button type="button" class="btn-cinnabar text-h2" data-testid="draw-start" @click="roll">
        Tâm tĩnh, chạm để gieo
      </button>
      <div v-if="rollErr" data-testid="draw-error" class="mt-4 text-center" role="alert">
        <p class="text-cinnabar text-small">{{ rollErr }}</p>
        <button type="button" data-testid="draw-retry" class="btn-line mt-2" @click="roll">Gieo lại</button>
      </div>
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
        <p class="text-body text-muted">{{ result.hexagram.ten }} — đang vào bảng giải…</p>
      </div>
    </div>
  </section>
</template>
