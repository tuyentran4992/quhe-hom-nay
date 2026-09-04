<script setup>
// AskedLuansSheet — [RL-FE t_47c88de0] «Đã hỏi quẻ này»: bottom sheet mobile-first
// overlay atop DetailView (D3 chốt: KHÔNG route riêng, KHÔNG pushState — back của
// trình duyệt = rời trang như overlay khác; đóng bằng nút + scrim + ESC).
// Overlay nội bộ nên DetailView/TopicGate KHÔNG unmount: mở bài xong quay lại, tab
// ?topic=, bài 'done' đang đọc, câu hỏi đang gõ, cooldown còn nguyên — đó là phép
// thử kiến trúc của card (nghiệm thu 5).
// Fetch: CHỦ DUY NHẤT = useDrawLuans (§D nhà). Sheet mount 1 lần cho quẻ nào →
// ensure() 1 request; mở lần 2 ăn cache Map trong phiên, 0 re-fetch.
// Item theo thứ tự scan D4: chip label → question (clamp 2 dòng; null →
// TOPIC_LABELS[topic]; cả hai null → BỎ DÒNG, không bịa) → giờ relative từ
// finished_at → excerpt NGUYÊN VĂN BE trả (clamp CSS chỉ là phao).
// Rỗng: sheet chỉ được mount khi ≥1 bài (nút mở đã ẩn theo F4) — không có branch
// «chưa hỏi gì» ở đây, CÓ CHỦ ĐÍCH.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import LuanArticle from './LuanArticle.vue'
import { useDrawLuans, formatLuanTime } from '../composables/useDrawLuans.js'
import { TOPIC_LABELS, ROUTER_LABELS, ROUTER_LABEL_DEFAULT, LUAN_LIST } from '../constants.js'

const props = defineProps({
  drawId: { type: Number, required: true },
})
const emit = defineEmits(['close'])

const luans = useDrawLuans()
const rows = ref([])
const loading = ref(true)
const failed = ref(false)
const openRow = ref(null) // bài đang đọc full-text (null → đang ở list)

// seq-guard (khuôn probeSaved TopicGate): retry nhanh không cho phép response cũ
// đè state mới — guard riêng của component, không đụng gen của TopicGate.
let seq = 0
async function load() {
  const my = ++seq
  loading.value = true
  failed.value = false
  try {
    const r = await luans.ensure(props.drawId)
    if (my !== seq) return
    rows.value = r
  } catch {
    if (my !== seq) return
    failed.value = true // 404/429/network → cùng khuôn failed (card mục 5)
  } finally {
    if (my === seq) loading.value = false
  }
}
onMounted(load)

function onKey(e) {
  if (e.key === 'Escape') {
    emit('close')
    return
  }
  // focus-trap nhẹ (R1-đ1): Tab rơi ra ngoài panel → kéo về element focus được
  // đầu/cuối trong panel — đủ cho aria-modal="true" đã khai, 0 lib.
  if (e.key === 'Tab' && panelEl.value) {
    const focusables = panelEl.value.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    )
    if (!focusables.length) return
    const first = focusables[0]
    const last = focusables[focusables.length - 1]
    const active = document.activeElement
    if (!panelEl.value.contains(active)) {
      e.preventDefault()
      ;(e.shiftKey ? last : first).focus()
    } else if (e.shiftKey && active === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && active === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

// a11y modal chuẩn (R1-đ1): mở = khoá cuộn body + nhớ trigger + focus title;
// đóng/unmount = trả focus về trigger + mở khoá. Self-contained trong sheet.
const panelEl = ref(null)
const titleEl = ref(null)
let triggerEl = null
let prevBodyOverflow = ''
onMounted(() => {
  triggerEl = document.activeElement instanceof HTMLElement ? document.activeElement : null
  prevBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  titleEl.value?.focus()
  window.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  document.body.style.overflow = prevBodyOverflow
  triggerEl?.focus?.()
  window.removeEventListener('keydown', onKey)
})

function close() {
  emit('close')
}
function openItem(row) {
  openRow.value = row
}
function backToList() {
  openRow.value = null
}

// Nhãn: ƯU TIÊN label BE trả (luôn có theo contract); fallback router_category →
// bảng ROUTER_LABELS (D4) → default «Điều cần bàn». Không bao giờ rỗng.
const labelOf = (row) =>
  row.label || ROUTER_LABELS[row.router_category] || ROUTER_LABEL_DEFAULT
// Dòng hỏi: question nguyên văn; null → TOPIC_LABELS[topic]; không có cả hai → null
// = BỎ DÒNG (test chốt: không "null", không trắng, không bịa).
const questionOf = (row) =>
  (row.question || '').trim() || TOPIC_LABELS[row.topic] || null
const timeOf = (row) => formatLuanTime(row.finished_at)

const items = computed(() =>
  rows.value.map((row) => ({
    row,
    label: labelOf(row),
    question: questionOf(row),
    time: timeOf(row),
  })),
)
</script>

<template>
  <div class="fixed inset-0 z-[60]" data-testid="luans-sheet" role="dialog" aria-modal="true" :aria-label="LUAN_LIST.title">
    <div data-testid="luans-scrim" class="absolute inset-0 bg-ink/50" @click="close" />
    <!-- wrapper định vị KHÔNG dùng transform (flex) — để animation translateY của
         panel không đá với translate centering (bài học anti-pattern) -->
    <div class="absolute inset-0 flex flex-col justify-end md:justify-start md:pt-16 pointer-events-none">
      <div
        ref="panelEl"
        data-testid="luans-panel"
        class="luans-panel-enter pointer-events-auto w-full md:w-[min(40rem,92vw)] mx-auto max-h-[85vh] flex flex-col rounded-t-2xl md:rounded-2xl bg-paper border border-gold/40 shadow-lift"
      >
      <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-paper2">
        <h2 ref="titleEl" tabindex="-1" data-testid="luans-title" class="han text-h2 font-semibold text-ink">{{ LUAN_LIST.title }}</h2>
        <button
          type="button"
          data-testid="luans-close"
          :aria-label="LUAN_LIST.close"
          class="min-h-[44px] min-w-[44px] shrink-0 rounded-card text-h2 text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
          @click="close"
        >×</button>
      </header>

      <!-- khối lỗi: khuôn phase failed TopicGate (copy, không sửa TopicGate) -->
      <div v-if="failed" data-testid="luans-failed" class="px-4 py-8 text-center">
        <p class="text-body text-muted mb-3">{{ LUAN_LIST.failed }}</p>
        <button type="button" data-testid="luans-retry" class="btn-cinnabar" @click="load">{{ LUAN_LIST.retry }}</button>
      </div>

      <div v-else-if="loading" data-testid="luans-loading" class="px-4 py-6 space-y-2">
        <p class="sr-only" aria-live="polite">{{ LUAN_LIST.loading }}</p>
        <div class="sk h-4 w-11/12" />
        <div class="sk h-4 w-full" />
        <div class="sk h-4 w-3/4" />
      </div>

      <template v-else>
        <!-- LIST: sống nguyên trong DOM lúc đọc bài (v-show) — quay lại giữ scrollTop -->
        <div data-testid="luans-list" class="overflow-y-auto px-4 py-3 space-y-2" v-show="!openRow">
          <button
            v-for="it in items"
            :key="it.row.id"
            type="button"
            data-testid="luans-item"
            class="luans-item w-full min-h-[44px] text-left card p-3 space-y-1 hover:border-cinnabar/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
            @click="openItem(it.row)"
          >
            <span data-testid="luans-label" class="chip-status text-muted">{{ it.label }}</span>
            <p v-if="it.question" data-testid="luans-question" class="text-body text-ink line-clamp-2">{{ it.question }}</p>
            <p v-if="it.time" data-testid="luans-time" class="text-small text-muted tabular-nums">{{ it.time }}</p>
            <p v-if="it.row.excerpt" data-testid="luans-excerpt" class="text-small text-muted line-clamp-2">{{ it.row.excerpt }}</p>
          </button>
        </div>

        <!-- detail nội bộ: full-text bằng engine chung, 0 re-fetch -->
        <div v-if="openRow" class="overflow-y-auto px-4 py-3">
          <button
            type="button"
            data-testid="luans-back"
            class="mb-2 min-h-[44px] text-small text-muted underline hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
            @click="backToList"
          >← {{ LUAN_LIST.back }}</button>
          <LuanArticle :text="openRow.result" :question="openRow.question" :label="labelOf(openRow)" />
        </div>
      </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* skeleton + fade mượn đúng khuông TopicGate đã duyệt (token có sẵn, không màu mới) */
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
.luans-panel-enter {
  animation: sheet-up 240ms ease-out both;
}
@keyframes sheet-up {
  from { transform: translateY(12px); opacity: 0.6; }
  to { transform: translateY(0); opacity: 1; }
}
@media (prefers-reduced-motion: reduce) {
  .sk { animation: none; }
  .luans-panel-enter { animation: none; }
}
</style>
