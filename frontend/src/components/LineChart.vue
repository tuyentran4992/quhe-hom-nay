<script setup>
// LineChart — 04-ui §3: vẽ 6 hào. Props: lines (rolled 6..9 HOẶC binary 0/1, dưới→trên),
// changing (1-based). Thứ tự DOM TRÊN→DƯỚI = hào 6→1 (TESTIDS.md #5).
// Dương (7/9/1) liền cinnabar; âm (6/8/0) đứt bamboo; hào động (6/9) chấm gold + "động".
// BUG-1 fix (t_c09526c3): row giờ = [.ln-bar width class DEFINITE + .ln-aside definite].
// Segment âm KHÔNG còn width:44% — dùng flex:1 1 0% inside definite bar → browser thật
// không quy về max-content=0. Marker nằm NGOÀI bar → dot 7px không bị chèn ép còn 0.84px.
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
      <!-- bar: width class definite (px từ Tailwind) — mọi segment %/flex quy về số thật -->
      <div class="ln-bar flex gap-1 shrink-0" :class="[w, h]">
        <template v-if="r.bit">
          <span class="ln-seg block rounded-sm bg-cinnabar h-full" style="flex: 1 1 0%" />
        </template>
        <template v-else>
          <span class="ln-seg block rounded-sm bg-bamboo h-full" style="flex: 1 1 0%" />
          <span class="ln-seg block rounded-sm bg-bamboo h-full" style="flex: 1 1 0%" />
        </template>
      </div>
      <!-- aside: chỗ riêng definite cho marker động — dot không bao giờ bị bar chen -->
      <div class="ln-aside w-20 shrink-0 flex items-center gap-1">
        <template v-if="r.mov">
          <span class="dot shrink-0" aria-hidden="true" />
          <span class="text-small font-semibold text-cinnabar">động</span>
        </template>
      </div>
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
/* Nhận diện hào động vượt mức chấm gold (boss: "không phân biệt được"):
   viền cinnabar đặc quanh bar — token 04-ui §1, contrast 4.76:1 > 3:1 trên paper2. */
.ln--mov .ln-bar {
  outline: 2px solid rgb(179 58 43); /* token cinnabar */
  outline-offset: 2px;
  border-radius: 9px; /* token rounded-sm — khớp góc segment */
}
</style>
