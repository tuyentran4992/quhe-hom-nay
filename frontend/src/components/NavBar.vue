<script setup>
// NavBar — header chung desktop ≥768px (NAV-SPEC t_53f6274b §1a, mount App.vue shell).
// nav-donate CHỈ render khi free_deep === true (lệ gating PaywallView:25 — cờ mặc định
// TRUE theo SỬA 02/09). Item active tô đậm qua router-link-active.
import { onMounted } from 'vue'
import { useDevice } from '../composables/useDeviceApi.js'
import { DONATE_LABEL, DONATE_HREF } from '../constants.js'
const d = useDevice()
// free_deep đến từ #1 qua store dùng chung — shell mount trước view nên tự load
// (load() cache toàn module: view load lại không thành 2 request).
onMounted(() => {
  d.load().catch(() => {})
})
</script>

<template>
  <header class="hidden md:block sticky top-0 z-40 bg-paper/95 backdrop-blur-sm border-b border-paper2">
    <div class="wrap mx-auto max-w-[1180px] px-gutter h-14 flex items-center gap-6">
      <RouterLink data-testid="nav-brand" to="/" class="han font-bold text-h2 no-underline text-ink tracking-[0.12em]">
        Quẻ Hôm Nay
      </RouterLink>
      <nav class="ml-auto flex items-center gap-5 text-small" aria-label="Điều hướng chính">
        <RouterLink data-testid="nav-draw" to="/draw" class="nav-i no-underline">Gieo quẻ</RouterLink>
        <RouterLink data-testid="nav-library" to="/cua-ban" class="nav-i no-underline">Sổ quẻ</RouterLink>
        <RouterLink
          v-if="d.freeDeep.value"
          data-testid="nav-donate"
          :to="DONATE_HREF"
          class="no-underline font-semibold text-gold hover:underline"
          >{{ DONATE_LABEL }}</RouterLink
        >
      </nav>
    </div>
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
