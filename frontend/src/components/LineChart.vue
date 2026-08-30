<script setup>
// LineChart — 04-ui §3: vẽ 6 hào. Props: lines (rolled 6..9 HOẶC binary 0/1, dưới→trên),
// changing (1-based). Thứ tự DOM TRÊN→DƯỚI = hào 6→1 (TESTIDS.md #5).
// Dương (7/9/1) liền cinnabar; âm (6/8/0) đứt bamboo; hào động (6/9) chấm gold + "động".
import { computed } from 'vue'

const props = defineProps({
  lines: { type: Array, required: true },
  changing: { type: Array, default: () => [] },
  size: { type: String, default: 'md' }, // sm | md | lg (shrine)
})

// binary hoá: rolled 6..9 → 1 dương / 0 âm; nếu đã là 0/1 thì giữ
const bits = computed(() => props.lines.map((v) => (v > 1 ? (v === 7 || v === 9 ? 1 : 0) : v)))
// DOM top = hào 6 → iterate position 6..1
const rows = computed(() =>
  [6, 5, 4, 3, 2, 1].map((pos) => ({
    pos,
    bit: bits.value[pos - 1],
    mov: props.changing.includes(pos) && props.lines[pos - 1] > 1 && (props.lines[pos - 1] === 6 || props.lines[pos - 1] === 9),
  })),
)
const w = computed(() => ({ sm: 'w-16', md: 'w-24', lg: 'w-40' }[props.size] || 'w-24'))
const h = computed(() => ({ sm: 'h-2', md: 'h-2', lg: 'h-3' }[props.size] || 'h-2'))
</script>

<template>
  <div class="flex flex-col items-center gap-1.5" role="img" aria-label="Sáu hào quẻ">
    <div
      v-for="r in rows"
      :key="r.pos"
      class="ln flex items-center gap-1"
      :class="[r.bit ? 'ln--yang' : 'ln--yin', r.mov ? 'ln--mov' : '']"
      :data-line="r.bit"
      :data-position="r.pos"
    >
      <template v-if="r.bit">
        <span class="block rounded-sm bg-cinnabar" :class="[w, h]" />
      </template>
      <template v-else>
        <span class="block rounded-sm bg-bamboo" :class="[w, h]" style="width: 44%" />
        <span class="block rounded-sm bg-bamboo" :class="[w, h]" style="width: 44%" />
      </template>
      <span v-if="r.mov" class="dot" aria-hidden="true" />
      <span v-if="r.mov" class="text-small font-semibold text-cinnabar ml-1">động</span>
    </div>
  </div>
</template>

<style scoped>
.dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #a8802a; /* token gold — chấm hào động (mockup v2 #178) */
  box-shadow: 0 0 0 2px rgb(247 242 231 / 0.9);
}
</style>
