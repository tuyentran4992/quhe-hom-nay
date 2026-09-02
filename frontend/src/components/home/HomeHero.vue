<script setup>
// HomeHero — khối đầu S1 3 trạng thái A/C (UX-HOME-V2 mockup a/c). State B không dùng
// hero (b-hero nằm trong HomeTodayCard). Copy lấy từ HOME_COPY — CẤM hardcode trong .vue.
defineProps({
  state: { type: String, required: true }, // 'a' | 'c'
  serverDate: { type: String, default: '' },
  note: { type: String, default: '' }, // C: streak note / fallback không con số — View tính
})
</script>

<template>
  <section class="pt-2" data-testid="home-hero">
    <div class="md:grid md:grid-cols-[1.4fr_1fr] md:gap-8 items-start">
      <div>
        <span data-testid="home-hero-seal" class="han inline-block border border-cinnabar/60 text-cinnabar rounded-sm px-2 py-0.5 text-small tracking-[0.2em]" aria-hidden="true">今日</span>
        <h1 data-testid="home-hero-title" class="han font-bold text-h1 leading-[1.25] mt-3">
          <template v-if="state === 'c'">Quẻ hôm nay<br />vẫn đang <em class="not-italic text-cinnabar">chờ bạn</em></template>
          <template v-else>Gieo ba đồng xu,<br />xin một quẻ <em class="not-italic text-cinnabar">hôm nay</em></template>
        </h1>
        <p v-if="state === 'a'" data-testid="home-hero-tagline" class="text-body text-muted mt-3 max-w-prose">
          Quẻ Hôm Nay luận giải việc làm, tình cảm, tiền tài bằng tiếng Việt — một quẻ
          Kinh Dịch cho một ngày của bạn.
        </p>
        <p v-if="serverDate" class="text-small text-muted mt-2">
          Hôm nay <span data-testid="home-server-date">{{ serverDate }}</span>
        </p>
        <p data-testid="home-hero-note" class="text-small text-muted mt-2">{{ note }}</p>
      </div>
      <div
        v-if="state === 'a'"
        data-testid="home-ritual"
        class="card p-5 mt-5 md:mt-0 text-center"
        aria-label="Nghi thức gieo ba đồng xu"
      >
        <span class="han text-gold text-h1 leading-none" aria-hidden="true">筮</span>
        <p class="text-small text-muted mt-2">Tĩnh tâm một nhịp — gieo ba đồng xu, đọc quẻ cho ngày của bạn.</p>
      </div>
    </div>
  </section>
</template>
