<script setup>
// HomeStreakChip — widget streak chung B/C (DESIGN-NOTES §52). CHỈ hiện khi suy được
// chuỗi từ #4 drawn_date neo server_date_vn (utils/streak.js) — API chưa có streak
// field → View quyết định render hay không; ở đây không tự bịa số.
defineProps({ label: { type: String, required: true } })
</script>

<template>
  <span data-testid="home-streak-chip" class="chip-status text-gold">
    <span class="inline-block w-1.5 h-1.5 rounded-full bg-cinnabar" aria-hidden="true"></span>{{ label }}
  </span>
</template>
