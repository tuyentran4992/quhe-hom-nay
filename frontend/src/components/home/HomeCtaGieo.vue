<script setup>
// HomeCtaGieo — CTA chính S1 (variant A/C chưa gieo). NÚT CÓ TRỌNG LƯỢNG THỊ GIÁC
// CAO NHẤT màn chưa-gieo (finish-gate): btn-cinnabar đầy đủ, không thành chip.
// BẮN cta_gieo_que khi bấm (06-mkt §50 — PA1, server là nguồn sự thật).
import { track } from '../../utils/track.js'
</script>

<template>
  <div class="mt-6">
    <RouterLink
      data-testid="home-cta-gieo"
      data-variant="new"
      to="/draw"
      class="btn-cinnabar text-h2"
      @click="track('cta_gieo_que', { placement: 'home_hero' })"
    >
      <span class="han" aria-hidden="true">筮</span> Gieo quẻ hôm nay
    </RouterLink>
    <p class="text-small text-muted mt-2">Miễn phí · 1 quẻ mỗi ngày · hẹn lại lúc 0h</p>
  </div>
</template>
