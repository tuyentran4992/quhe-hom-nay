<script setup>
// HomeTopicChips — 3 chip chủ đề S1. NGÔN NGỮ THEO freeDeep (boss chốt 02/09):
// true → pill "Luận sâu MIỄN PHÍ" trên CẢ 3 chip, CẤM in giá; false → giữ hành vi cũ
// (Đã mở ✓ cho topic trong entitlements, PRICE_LABEL cho chip chưa mở).
// [HOME-V4-A L1] freeDeep=true → chip nhảy THẲNG trang quẻ /que/<draw_id>?topic=<tab>
// (KHÔNG bao giờ qua /mo-khoa); chưa có draw hôm nay → /draw. false → y nguyên hành vi cũ.
import { HOME_TOPIC_CHIPS, TOPIC_LABELS, PRICE_LABEL, FREE_DEEP_LABEL } from '../../constants.js'
const props = defineProps({
  entitlements: { type: Array, default: () => [] },
  freeDeep: { type: Boolean, default: false },
  todayDrawId: { type: [Number, String], default: null }, // #1 today_draw.id (HomeView truyền xuống)
})
const slug = (t) => t.replace('_', '-')
// ánh xạ chip home → tab DetailView (ngược với topicForTab DetailView:85 —
// congViec→xuat_hanh, tinhDuyen→duyen, taiLoc→tai_loc)
const TAB_FOR_TOPIC = { xuat_hanh: 'congViec', duyen: 'tinhDuyen', tai_loc: 'taiLoc' }
const chipTo = (t) => {
  if (!props.freeDeep) return `/mo-khoa/${t}`
  if (props.todayDrawId) return { path: `/que/${props.todayDrawId}`, query: { topic: TAB_FOR_TOPIC[t] } }
  return '/draw'
}
</script>

<template>
  <section data-testid="home-topics" class="mt-10">
    <h2 data-testid="home-topics-title" class="text-h2 font-semibold mb-3">Luận sâu theo chủ đề</h2>
    <ul class="grid grid-cols-1 md:grid-cols-3 gap-3 list-none p-0">
      <li v-for="c in HOME_TOPIC_CHIPS" :key="c.topic">
        <RouterLink
          :data-testid="`home-chip-${slug(c.topic)}`"
          class="card block p-4 no-underline"
          :to="chipTo(c.topic)"
        >
          <span class="font-semibold text-ink">{{ c.name }}</span>
          <span class="sr-only"> ({{ TOPIC_LABELS[c.topic] }})</span>
          <p class="text-small text-muted mt-1">{{ c.desc }}</p>
          <span v-if="freeDeep" :data-testid="`home-chip-${slug(c.topic)}-free`" class="chip-status mt-2 text-bamboo font-semibold">
            {{ FREE_DEEP_LABEL }}
          </span>
          <span v-else class="flex items-center gap-2 mt-2">
            <template v-if="props.entitlements.includes(c.topic)">
              <span :data-testid="`home-chip-${slug(c.topic)}-state`" class="text-small font-semibold text-bamboo">Đã mở ✓</span>
            </template>
            <template v-else>
              <span :data-testid="`home-chip-${slug(c.topic)}-price`" class="chip-status text-small text-muted">{{ PRICE_LABEL }}</span>
            </template>
          </span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
