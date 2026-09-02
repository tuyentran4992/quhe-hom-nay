<script setup>
// MagicSequence — S2 B2, PA1 "Một chạm · Xu quyết từng hào" (gate t_04394e77 chốt;
// timeline/wording nguồn: mockup-3xu.html playPA1 qua utils/timeline.js).
// BẤT BIẾN C-08: reveal KHÔNG trước 1500ms — floor kẹp trong pa1Timeline; lịch PA1
// reveal 3060ms. 6 hào vẽ DƯỚI→TRÊN mỗi hào 1 cú gieo 3 xu; hào động (6|9 — C-09)
// nháy son 2 nhịp + dấu 動 tại dynoAt TRƯỚC reveal. prefers-reduced-motion: GIỮ
// nguyên mọi mốc, chỉ bỏ chuyển động thừa (không cụm xu bay). Không âm thanh.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { MAGIC_SEQUENCE_MS, COIN_LAND_SWITCH_MS } from '../constants.js'
import { pa1Timeline, statusAt, dynoLabels, isChanging } from '../utils/timeline.js'

const props = defineProps({
  durationMs: { type: Number, default: MAGIC_SEQUENCE_MS },
  lines: { type: Array, required: true }, // rolled dưới→trên (03-api §3.2)
  symbol: { type: String, default: '' },
  ten: { type: String, default: '' },
})
const emit = defineEmits(['done'])

const tl = pa1Timeline(props.durationMs) // floor C-08 kẹp cứng bên trong
const reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false
const now = ref(0) // ms kể từ chạm (tick 20ms — fake-timer thân thiện)
const flyCount = ref(0) // cụm xu đã tung (lũy kế 0..6)
const revealed = ref(false)
let t0 = 0
let timers = []
let ticker = null

const rows = computed(() =>
  Array.from({ length: 6 }, (_, i) => ({
    pos: i + 1,
    rolled: props.lines[i],
    yang: props.lines[i] === 7 || props.lines[i] === 9,
    mov: isChanging(props.lines[i]),
  })),
)
const dynos = computed(() => dynoLabels(props.lines))
const status = computed(() => statusAt(now.value, props.ten, props.symbol, dynos.value))
const shown = (i) => now.value >= tl.drawAt[i]
const flyActive = (i) => !reduced && i < flyCount.value && now.value - tl.flyAt[i] < 900 // bay+đáp+fade
const dynoOn = computed(() => now.value >= tl.dynoAt)
const upPx = (i) => 210 - i * 22 - 46 // slot i tính từ đáy stage (slot 1 thấp nhất)

onMounted(() => {
  t0 = Date.now()
  if (!reduced) {
    for (let i = 0; i < 6; i++) timers.push(setTimeout(() => (flyCount.value = i + 1), tl.flyAt[i]))
  }
  timers.push(
    setTimeout(() => {
      revealed.value = true
      emit('done') // mốc C-08 — DrawView chỉ push S3 khi có cả kết quả thật
    }, tl.revealAt),
  )
  ticker = setInterval(() => (now.value = Date.now() - t0), 20)
})
onBeforeUnmount(() => {
  timers.forEach(clearTimeout)
  clearInterval(ticker)
})
</script>

<template>
  <div class="ms-root flex flex-col items-center gap-3" data-testid="magic-sequence" :data-fly-count="reduced ? 0 : flyCount">
    <!-- sân nghi thức: 6 slot hào dưới→trên + cụm xu bay -->
    <div class="ms-stage" data-testid="ritual-stage">
      <div class="ms-slot" :data-position="r.pos" :class="{ 'is-shown': shown(i) }" v-for="(r, i) in rows" :key="r.pos" :data-draw-line="r.pos">
        <span class="ms-line" :class="[r.yang ? 'ms-line--yang' : 'ms-line--yin', shown(i) ? 'done' : '']" :aria-hidden="true" />
        <span v-if="r.mov" class="dyno ms-dyno" data-testid="dyno-badge" :class="{ show: dynoOn, pulse: dynoOn && !reduced }">動</span>
      </div>
      <div
        v-for="(f, i) in (reduced ? [] : Array.from({ length: flyCount }, (_, k) => k))"
        :key="`c${i}`"
        class="ms-cluster"
        :class="now - tl.flyAt[i] >= COIN_LAND_SWITCH_MS ? 'land' : 'fly'"
        :style="{ '--up': upPx(f) + 'px' }"
        :data-fly="f"
        data-testid="coin-cluster"
      >
        <span class="coin" /><span class="coin" /><span class="coin" />
      </div>
    </div>

    <p class="ms-status" data-testid="draw-status" aria-live="polite">{{ status }}</p>

    <div v-if="revealed && ten" data-testid="reveal-hexagram" class="ms-reveal">
      <div class="ms-sym han">{{ symbol }}</div>
      <p class="ms-nm han">{{ ten }}</p>
      <p v-if="dynos.length" data-testid="reveal-sub" class="ms-sub">hào {{ dynos.join('·') }} động</p>
    </div>
  </div>
</template>

<style scoped>
/* hình học bám mockup-3xu.html (slot 34px/gap13, line 9px, xu 26px, fly .3s, pulse .3s×2) */
.ms-root { min-height: 320px; }
.ms-stage {
  position: relative; width: min(340px, 92vw); padding: 14px 10px 10px;
  border: 1px solid rgb(168 128 42 / 0.3); border-radius: 14px;
  background: linear-gradient(180deg, rgb(247 242 231 / 0.6), rgb(239 230 211 / 0.45));
  box-shadow: inset 0 1px 0 rgb(247 242 231 / 0.6);
  display: flex; flex-direction: column-reverse; gap: 13px; z-index: 0;
}
.ms-slot { position: relative; height: 34px; display: grid; align-items: center; }
.ms-line {
  display: block; width: 82%; height: 9px; margin: 0 auto; border-radius: 3px; opacity: 0;
  transition: opacity 0.2s;
}
.ms-line--yang { background: #b33a2b; }
.ms-line--yin { background: linear-gradient(90deg, #3e5c48 0 38%, transparent 38% 62%, #3e5c48 62% 100%); }
.ms-line.done { opacity: 1; }
.ms-dyno {
  position: absolute; right: 12%; top: 50%; transform: translateY(-50%);
  width: 20px; height: 20px; border-radius: 50%; background: #b33a2b; color: #f7f2e7;
  font-family: 'Noto Serif TC', serif; font-size: 11px; font-weight: 700; line-height: 20px;
  text-align: center; box-shadow: 0 1px 3px rgb(30 27 24 / 0.25); opacity: 0;
}
.ms-dyno.show { opacity: 1; }
.ms-dyno.pulse { animation: ms-red-pulse 0.3s ease-in-out 2 both; }
@keyframes ms-red-pulse {
  0%, 100% { opacity: 1; transform: translateY(-50%) scale(1); }
  50% { opacity: 0.35; transform: translateY(-50%) scale(1.12); }
}
.ms-cluster {
  position: absolute; left: 50%; top: 100%; z-index: 5; display: flex; gap: 5px;
  transform: translate(-50%, 0); opacity: 0; pointer-events: none;
}
.ms-cluster.fly { animation: ms-fly-up 0.3s cubic-bezier(0.2, 0.7, 0.25, 1) both; }
.ms-cluster.land { animation: ms-land 0.26s cubic-bezier(0.3, 0.9, 0.4, 1) both; }
@keyframes ms-fly-up {
  from { transform: translate(-50%, 46px); opacity: 0; }
  60% { opacity: 1; }
  to { transform: translate(-50%, calc(-1 * var(--up))); opacity: 1; }
}
@keyframes ms-land {
  0% { transform: translate(-50%, calc(-1 * var(--up))) scale(1.06); opacity: 1; }
  55% { transform: translate(-50%, calc(-1 * var(--up) - 3px)) scale(0.99); opacity: 1; }
  100% { transform: translate(-50%, calc(-1 * var(--up))); opacity: 0; }
}
.coin {
  position: relative; width: 26px; height: 26px; flex: none; border-radius: 50%;
  background: radial-gradient(120% 120% at 32% 26%, #e8cf8d, #caa44f 52%, #8e6b22 100%);
  border: 1px solid rgb(94 70 22 / 0.55);
  box-shadow: inset 0 1px 2px rgb(255 255 255 / 0.55), 0 2px 4px rgb(30 27 24 / 0.25);
}
.coin::after {
  content: ''; position: absolute; inset: 34%; border-radius: 2px;
  background: radial-gradient(circle at 40% 35%, #7c5c18, #5a4210 72%);
}
.ms-status { font-size: 13px; color: #5c554a; letter-spacing: 0.02em; min-height: 22px; text-align: center; }
.ms-reveal { display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; }
.ms-sym { font-size: 64px; line-height: 1.15; color: #1e1b18; text-shadow: 0 2px 8px rgb(30 27 24 / 0.12); }
.ms-nm { font-size: 22px; font-weight: 700; color: #1e1b18; letter-spacing: 0.04em; }
.ms-sub { font-size: 12px; color: #a8802a; }
@media (prefers-reduced-motion: reduce) {
  .ms-cluster, .ms-line, .ms-dyno { animation: none !important; transition: none !important; }
}
</style>
