<script setup>
// HomeTodayCard — State B: quẻ hôm nay (nhận data qua props, logic API ở HomeView —
// component chỉ render, chống god-file). Hào vẽ bằng .ln (6 dòng vector, `.mov` chấm
// hào động) thay glyph ䷊ phụ thuộc font. Giờ "Đã gieo lúc HH:MM" render theo máy
// khách từ created_at RFC3339 (nội quy nhà — test mốc 23:59→00:00 bên QA).
import { fmtDateVn, changingLabel } from '../../utils/format.js'
const props = defineProps({
  draw: { type: Object, required: true },
  hx: { type: Object, default: null }, // #2 đã tra (null = pending/lỗi → home-hexagram-pending)
  streakLabel: { type: String, default: '' },
})
// lines hexagram §2: 1=dương liên, 0=âm đoạn; changing_lines[2] = hào 2 (đáy→ngọn)
const lineCls = (i) => {
  const yin = props.hx?.lines?.[i] === 0
  const mov = (props.draw?.changing_lines || []).includes(i + 1)
  return { yin, mov }
}
const status = () => {
  const d = new Date(props.draw.created_at)
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `Đã gieo lúc ${hh}:${mm} — hẹn giờ Tý (0h) mai`
}
</script>

<template>
  <section class="pt-2">
    <div class="flex flex-wrap items-center gap-3">
      <p class="text-body text-muted">Quẻ hôm nay của bạn <span>· {{ fmtDateVn(draw.drawn_date) }}</span></p>
      <span v-if="streakLabel" data-testid="home-streak-chip" class="chip-status text-gold">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-cinnabar" aria-hidden="true"></span>{{ streakLabel }}
      </span>
    </div>
    <article data-testid="home-today-card" class="card p-5 mt-3">
      <p data-testid="home-today-status" class="text-small text-muted">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-bamboo mr-1" aria-hidden="true"></span>{{ status() }}
      </p>
      <div class="flex items-start gap-4 mt-3">
        <div
          v-if="hx"
          data-testid="home-hexagram-symbol"
          class="shrink-0 flex flex-col-reverse gap-[3px] w-16"
          role="img"
          :aria-label="`Quẻ ${hx.ten} ${hx.han}${draw.changing_lines?.length ? ', ' + changingLabel(draw.changing_lines) : ''}`"
        >
          <span
            v-for="i in 6"
            :key="i"
            class="ln"
            :class="{ yin: lineCls(i - 1).yin, mov: lineCls(i - 1).mov }"
          ></span>
        </div>
        <div v-else data-testid="home-hexagram-pending" class="han shrink-0 text-muted text-h1 leading-none" aria-label="đang tải quẻ">…</div>
        <div class="flex-1 min-w-0">
          <h2 v-if="hx" data-testid="home-hexagram-name" class="han font-semibold text-h2">
            <span class="text-small text-muted font-normal no-underline">Quẻ {{ hx.id }}</span> · {{ hx.ten }}
            <span class="han text-gold text-body">{{ hx.han }}</span>
          </h2>
          <p v-else-if="draw" class="text-small text-muted"><span data-testid="home-hexagram-pending">Chưa tải được tên quẻ — kiểm tra mạng rồi tải lại.</span></p>
          <p v-if="draw.changing_lines?.length" data-testid="home-changing-lines" class="text-cinnabar font-semibold text-small mt-1">
            {{ changingLabel(draw.changing_lines) }}
          </p>
          <div v-if="hx?.free_content?.congViec" data-testid="home-today-free-line" class="border-l-2 border-gold pl-3 mt-3 text-body">
            <p class="text-small text-muted">Công việc · luận ngắn</p>
            <p>{{ hx.free_content.congViec }}</p>
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-3 mt-4">
        <RouterLink data-testid="home-cta-detail" class="btn-cinnabar" :to="`/que/${draw.id}`">Xem đủ ba ngôi →</RouterLink>
        <RouterLink data-testid="home-share-btn" class="btn-line" :to="`/share-card?draw=${draw.id}`">Chia sẻ quẻ</RouterLink>
      </div>
    </article>
  </section>
</template>

<style scoped>
.ln {
  @apply block h-[7px] bg-ink/85 rounded-[1px];
}
.ln.yin {
  background: linear-gradient(90deg, rgba(30, 27, 24, 0.85) 0 42%, transparent 42% 58%, rgba(30, 27, 24, 0.85) 58% 100%);
}
.ln.mov {
  @apply bg-cinnabar;
}
.ln.mov.yin {
  background: linear-gradient(90deg, #b33a2b 0 42%, transparent 42% 58%, #b33a2b 58% 100%);
}
</style>
