<script setup>
// DrawHeader — UXR-4a ĐX1: mini-header nghi thức của /draw, thay trọn "← Về".
// KHÔNG phải shell nav (NAV-SPEC §1c giữ — App.vue không đổi): chỉ 3 điểm một hàng,
// rolling → chỉ brand, ẩn 2 link (đúng chỉ đạo ĐX1, decision t_UXR3).
// Wording DRAW_COPY nguyên văn bản chốt UXR-W mục 1.
import { DRAW_COPY } from '../constants.js'
defineProps({
  rolling: { type: Boolean, default: false },
})
</script>

<template>
  <header class="absolute top-0 inset-x-0 flex items-center justify-between px-gutter py-4" data-testid="draw-header">
    <RouterLink
      data-testid="draw-brand"
      to="/"
      class="han font-bold text-h2 no-underline text-ink tracking-[0.12em]"
      >{{ DRAW_COPY.brand }}</RouterLink
    >
    <nav v-if="!rolling" class="flex items-center gap-5 text-small" aria-label="Điều hướng màn gieo">
      <RouterLink data-testid="draw-library" to="/cua-ban" class="nav-i no-underline">{{ DRAW_COPY.library }}</RouterLink>
      <RouterLink data-testid="draw-home" to="/" class="nav-i no-underline">{{ DRAW_COPY.home }}</RouterLink>
    </nav>
  </header>
</template>

<style scoped>
.nav-i {
  @apply text-muted font-medium;
}
.nav-i.router-link-active {
  @apply text-ink font-bold;
}
</style>
