<script setup>
// BottomTabs — 3 tab mobile <768px (NAV-SPEC §1b): Home / Gieo / Sổ quẻ.
// [BUG02 FIX t_b548bbd6] KHÔNG còn tự định vị (fixed bottom-9 cũ giả định disclaimer
// 1 dòng = sai khi wording wrap 2 dòng ở 375 → đè 19px). App.vue đặt tab + DisclaimerBar
// chung một stack flex-col fixed bottom-0 — chiều cao tự co giãn, không thể đè nhau.
// md:hidden giữ nguyên: desktop stack chỉ còn disclaimer → hành vi ≥768 không đổi.
</script>

<template>
  <nav
    class="md:hidden relative z-[45] bg-paper/95 backdrop-blur-sm border-t border-paper2"
    aria-label="Điều hướng nhanh"
  >
    <div class="grid grid-cols-3 text-small">
      <RouterLink data-testid="tab-home" to="/" class="tab-i no-underline">
        <span aria-hidden="true">卦</span>Home
      </RouterLink>
      <RouterLink data-testid="tab-draw" to="/draw" class="tab-i no-underline">
        <span aria-hidden="true">筮</span>Gieo quẻ
      </RouterLink>
      <RouterLink data-testid="tab-library" to="/cua-ban" class="tab-i no-underline">
        <span aria-hidden="true">冊</span>Sổ quẻ
      </RouterLink>
    </div>
  </nav>
</template>

<style scoped>
.tab-i {
  @apply flex flex-col items-center justify-center gap-0.5 py-2 text-muted font-medium;
}
.tab-i span {
  @apply han text-h2 leading-none;
}
.tab-i.router-link-active {
  @apply text-cinnabar font-bold;
}
</style>
