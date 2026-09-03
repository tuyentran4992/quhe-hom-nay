<script setup>
// DrawRevealActions — UXR-4b (t_31ef1ece): khối hành động SAU nghi thức /draw,
// tách từ DrawView.vue (chống god-file RULES). Hai nhánh:
//  - pending chậm (nghi thức đã qua mốc 1500ms mà #3 chưa về): spinner + «Thử lại»
//    + «Về trang chính» — khách không kẹt màn không hành động (card 4b ĐX5 clause cuối).
//  - kết quả (ĐX5+B1): dòng «{ten} — gieo lúc HH:MM» (C1/Dấu mực — cấm lời bình giờ)
//    + 3 quyền chọn: nút CHÍNH btn-cinnabar «Mở bảng giải», text-link MỜ «Giữ lại thẻ
//    quẻ hôm nay →» → /share-card?draw={id} (anti-2-CTA: KHÔNG phải btn thứ 2),
//    link muted «Về trang chính». Bấm share/home → emit cancel-push (hủy auto-push).
// Wording NGUYÊN VĂN UXR-W mục 4 qua DRAW_COPY (surface duy nhất).
import { DRAW_COPY } from '../constants.js'
defineProps({
  pendingSlow: { type: Boolean, default: false }, // done mà chưa có kết quả
  resultReady: { type: Boolean, default: false }, // C-08: chỉ reveal khi nghi thức đã done (kèm kết quả)
  ten: { type: String, default: '' }, // tên quẻ (kết quả THẬT — không placeholder)
  castTime: { type: String, default: '' }, // C1 "HH:MM" giờ máy khách
  drawId: { type: [Number, String], default: null },
})
defineEmits(['retry', 'goto-detail', 'cancel-push'])
</script>

<template>
  <!-- pending chậm: lối thoát khi BE/ mạng treo sau mốc nghi thức -->
  <div v-if="pendingSlow" data-testid="draw-pending-slow" class="flex flex-col items-center gap-3 text-center">
    <p data-testid="draw-spinner" class="text-small text-muted animate-pulse">{{ DRAW_COPY.spinnerPending }}</p>
    <div class="flex items-center gap-4">
      <button type="button" data-testid="draw-retry" class="btn-line" @click="$emit('retry')">{{ DRAW_COPY.retryPending }}</button>
      <RouterLink data-testid="draw-home-after" to="/" class="draw-textlink">{{ DRAW_COPY.homeAfter }}</RouterLink>
    </div>
  </div>

  <!-- ĐX5+B1: quyền chọn sau reveal — auto-push vẫn là mặc định (DrawView giữ timer).
       C-08: resultReady = done && có kết quả — KHÔNG reveal trước mốc nghi thức. -->
  <div v-else-if="resultReady && drawId != null" data-testid="draw-result" class="text-center flex flex-col items-center gap-4">
    <p class="text-body text-muted">{{ ten }} — {{ DRAW_COPY.castAt(castTime) }}</p>
    <button type="button" data-testid="draw-goto-detail" class="btn-cinnabar text-h2" @click="$emit('goto-detail')">
      {{ DRAW_COPY.detailBtn }}
    </button>
    <RouterLink :to="`/share-card?draw=${drawId}`" data-testid="draw-share-cta" class="draw-textlink" @click="$emit('cancel-push')">{{
      DRAW_COPY.shareCta
    }}</RouterLink>
    <RouterLink to="/" data-testid="draw-home-after" class="text-small text-muted no-underline" @click="$emit('cancel-push')">{{
      DRAW_COPY.homeAfter
    }}</RouterLink>
  </div>
</template>

<style scoped>
/* UXR-4b B1 — text-link MỜ (wording: không phải btn thứ 2): nhỏ hơn nút chính MỘT
   BẠC rõ rệt, muted + underline đồng mờ; mọi giá trị qua token system — không tự chế. */
.draw-textlink {
  @apply text-small text-muted underline decoration-gold/40 underline-offset-4 hover:text-ink hover:decoration-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold;
}
</style>
