<script setup>
// LuanHomNay — [UI-POLISH t_fc6387df] vùng "Luận hôm nay" S3 (04-ui §S3 + FE-3XU):
// nhịp đọc 3 tầng — đại ý (tiểu dẫn) → kicker "Từ hào" (tách nhịp) → khối từ hào
// (kết). Chỉ layout/display; data qua props, không gọi API, không đổi logic.
// 0 hào động = trạng thái hợp lệ: chỉ Đại ý, không kicker, không khung trống.
import { computed } from 'vue'
import HaoDongBlock from './HaoDongBlock.vue'

const props = defineProps({
  daiCi: { type: String, default: '' },
  haoDong: { type: Array, default: () => [] }, // phần tử hao_texts #2b/#3, đã lọc sơ→thượng
})
const hasHao = computed(() => props.haoDong.length > 0)
</script>

<template>
  <section data-testid="luan-hom-nay" class="mt-8">
    <h2 class="han text-h2 font-semibold text-ink">Luận hôm nay</h2>
    <p data-testid="luan-dai-y" class="text-body mt-2 leading-relaxed">{{ daiCi }}</p>
    <template v-if="hasHao">
      <h3 data-testid="luan-hao-label" class="chip-kicker mt-6">Từ hào</h3>
      <div data-testid="luan-hao-list" class="mt-3 space-y-4">
        <HaoDongBlock v-for="t in haoDong" :key="t.vi" :text="t" />
      </div>
    </template>
  </section>
</template>

<style scoped>
/* kicker nhịp đọc — token qua @apply (SFC style chạy qua PostCSS pipeline);
   [UI-POLISH] dùng muted (6.6:1) thay gold (2.9:1 trên paper2 — KHÔNG đạt sàn AA
   cho chữ nhỏ 13px); chất Đông giữ bởi chữ hoa + khoảng tự + serif quẻ hán. */
.chip-kicker {
  @apply text-small font-semibold uppercase tracking-[0.08em] text-muted;
}
</style>
