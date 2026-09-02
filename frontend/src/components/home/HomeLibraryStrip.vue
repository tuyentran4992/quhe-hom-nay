<script setup>
// HomeLibraryStrip — dải "Sổ quẻ của bạn" (mockup B: lib-grid 3 item + Xem tất cả).
// Data = history #4 TRÁM quẻ hôm nay (View lọc). Mỗi item link /que/:id. Tên quẻ
// cần #2 — dải chỉ show ngày + id (hexagram vector từ lines_rolled để không gọi
// #2 hàng loạt cho 3 ô nhỏ; QA soi link, không soi glyph).
import { epochDay } from '../../utils/streak.js'
const props = defineProps({ draws: { type: Array, required: true } })
const dd = (iso) => String(iso || '').slice(5, 10).split('-').reverse().join('/')
// lines_rolled 6/7/8 → 6 hào: 7 dương tĩnh, 8 âm tĩnh, 6 âm động, 9 dương động
const ln = (v, i) => ({ yin: v === 6 || v === 8 })
</script>

<template>
  <section data-testid="home-library-strip" class="mt-10">
    <div class="flex items-baseline justify-between mb-3">
      <h2 class="text-h2 font-semibold">Sổ quẻ của bạn</h2>
      <RouterLink data-testid="home-library-link-all" to="/cua-ban" class="text-small font-semibold text-bamboo no-underline hover:underline">Xem tất cả →</RouterLink>
    </div>
    <ul class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 list-none p-0">
      <li v-for="(x, i) in props.draws" :key="x.id">
        <RouterLink :data-testid="`home-library-item-${i + 1}`" :to="`/que/${x.id}`" class="card flex items-center gap-3 p-3 no-underline">
          <span class="shrink-0 flex flex-col-reverse gap-[2px] w-10" aria-hidden="true">
            <span v-for="(v, j) in x.lines_rolled || []" :key="j" class="ln" :class="{ yin: ln(v, j).yin }"></span>
          </span>
          <span class="min-w-0">
            <span class="block text-small text-muted">{{ dd(x.drawn_date) }}</span>
            <span class="block font-medium text-ink truncate">Quẻ của bạn · {{ dd(x.drawn_date) }}</span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.ln {
  @apply block h-[5px] bg-ink/70 rounded-[1px];
}
.ln.yin {
  background: linear-gradient(90deg, rgba(30, 27, 24, 0.7) 0 42%, transparent 42% 58%, rgba(30, 27, 24, 0.7) 58% 100%);
}
</style>
