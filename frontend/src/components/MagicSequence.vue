<script setup>
// MagicSequence — 04-ui §2.S2 B2 (C-08): animation TỐI THIỂU 1500ms, 6 hào vẽ lần lượt
// từ DƯỚI lên, mỗi hào ~250ms. durationMs nhỏ hơn 1500 bị KẸP về 1500 (bất biến).
// Reveal symbol+ten đúng mốc duration → emit done. Respect prefers-reduced-motion:
// giảm motion nhưng KHÔNG giảm thời gian hứa hẹn (vẫn reveal cùng mốc).
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { MAGIC_SEQUENCE_MS, LINE_STAGGER_MS } from '../constants.js'

const props = defineProps({
  durationMs: { type: Number, default: MAGIC_SEQUENCE_MS },
  lines: { type: Array, required: true }, // rolled dưới→trên
  symbol: { type: String, default: '' },
  ten: { type: String, default: '' },
})
const emit = defineEmits(['done'])

const total = Math.max(props.durationMs, MAGIC_SEQUENCE_MS) // kẹp cứng
const shownCount = ref(0)
const revealed = ref(false)
let timers = []

const rows = computed(() =>
  Array.from({ length: 6 }, (_, i) => ({ pos: i + 1, rolled: props.lines[i] })),
)
const bits = computed(() => rows.value.map((r) => (r.rolled === 7 || r.rolled === 9 ? 1 : 0)))

onMounted(() => {
  for (let i = 0; i < 6; i++) {
    timers.push(setTimeout(() => (shownCount.value = i + 1), (i + 1) * LINE_STAGGER_MS))
  }
  timers.push(
    setTimeout(() => {
      revealed.value = true
      emit('done')
    }, total),
  )
})
onBeforeUnmount(() => timers.forEach(clearTimeout))
</script>

<template>
  <div class="flex flex-col items-center gap-6" data-testid="magic-sequence">
    <div class="flex flex-col-reverse items-center gap-1.5" aria-hidden="true">
      <div
        v-for="(b, i) in bits"
        :key="rows[i].pos"
        class="draw-line flex w-40 gap-2 transition-opacity duration-200"
        :class="shownCount > i ? 'is-shown opacity-100' : 'opacity-0'"
        :data-draw-line="rows[i].pos"
        :data-position="rows[i].pos"
      >
        <span v-if="b" class="h-3 flex-1 rounded-sm bg-cinnabar" />
        <template v-else>
          <span class="h-3 flex-1 rounded-sm bg-bamboo" />
          <span class="w-4" />
          <span class="h-3 flex-1 rounded-sm bg-bamboo" />
        </template>
      </div>
    </div>
    <div v-if="revealed" data-testid="reveal-hexagram" class="text-center">
      <div class="han text-h1 text-ink" style="font-size: 64px; line-height: 1.2">{{ symbol }}</div>
      <p class="han text-h2 text-ink mt-2 font-semibold">{{ ten }}</p>
    </div>
  </div>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  .draw-line {
    transition: none;
  }
}
</style>
