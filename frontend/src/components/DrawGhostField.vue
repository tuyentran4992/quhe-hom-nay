<script setup>
// DrawGhostField — UXR-4a A1/ĐX6 (t_0c74b51e): "sân gieo" TĨNH chờ hào nảy.
// 6 slot ghost + 3 xu tái dùng ĐÚNG token .ms-stage/.ms-slot/.coin của MagicSequence
// (viền #a88042/0.3 · son #b33a2b · jade #3e5c48 · xu #e8cf8d→#8e6b22) — hình học
// khớp để hào thật nảy đúng chỗ. aria-hidden (không dữ liệu), opacity thấp,
// KHÔNG animation/transition → không reveal gì trước 1500ms (C-08 không bị chạm).
// dương-âm đan là trang trí theo hình quẻ, không mang nghĩa bói toán.
const GHOST_LINES = [1, 0, 0, 0, 1, 1]
</script>

<template>
  <div data-testid="draw-ghost-field" class="ghost-stage" aria-hidden="true">
    <div v-for="(yang, i) in GHOST_LINES" :key="i" class="ghost-slot" data-testid="draw-ghost-slot">
      <span class="ghost-line" :class="yang ? 'ghost-line--yang' : 'ghost-line--yin'" />
    </div>
    <div class="ghost-coins">
      <span v-for="c in 3" :key="c" class="ghost-coin" data-testid="draw-ghost-coin" />
    </div>
  </div>
</template>

<style scoped>
/* hình học BẮM .ms-stage MagicSequence.vue:97-104 (340px, slot 34px, gap 13, line 9px) */
.ghost-stage {
  position: relative;
  width: min(340px, 92vw);
  padding: 14px 10px 10px;
  border: 1px solid rgb(168 128 42 / 0.3);
  border-radius: 14px;
  background: linear-gradient(180deg, rgb(247 242 231 / 0.6), rgb(239 230 211 / 0.45));
  display: flex;
  flex-direction: column-reverse;
  gap: 13px;
}
.ghost-slot {
  position: relative;
  height: 34px;
  display: grid;
  align-items: center;
}
.ghost-line {
  display: block;
  width: 82%;
  height: 9px;
  margin: 0 auto;
  border-radius: 3px;
  opacity: 0.14;
}
.ghost-line--yang {
  background: #b33a2b;
}
.ghost-line--yin {
  background: linear-gradient(90deg, #3e5c48 0 38%, transparent 38% 62%, #3e5c48 62% 100%);
}
.ghost-coins {
  position: absolute;
  left: 50%;
  bottom: 18px;
  transform: translateX(-50%);
  display: flex;
  gap: 5px;
  opacity: 0.18;
}
.ghost-coin {
  width: 26px;
  height: 26px;
  flex: none;
  border-radius: 50%;
  background: radial-gradient(120% 120% at 32% 26%, #e8cf8d, #caa44f 52%, #8e6b22 100%);
  border: 1px solid rgb(94 70 22 / 0.55);
}
</style>
