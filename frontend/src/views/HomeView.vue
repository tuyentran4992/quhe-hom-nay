<script>
export default { name: 'HomeView' } // test router resolve-by-name
</script>
<script setup>
// S1 Home — 04-ui §2.S1 + TESTIDS.md. Nhánh: loading / lỗi / chưa-gieo / đã-gieo.
// Ngày MŨI TÊN từ server_date_vn (#1) — không dùng đồng hồ máy.
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useDevice } from '../composables/useDeviceApi.js'
import { useToasts } from '../composables/useToasts.js'
import DisclaimerBar from '../components/DisclaimerBar.vue'
import { fmtDateVn, changingLabel } from '../utils/format.js'
import { TOPICS, TOPIC_LABELS, PRICE_LABEL } from '../constants.js'

const route = useRoute()
const d = useDevice()
const toasts = useToasts()
const today = computed(() => d.todayDraw.value)
const hx = computed(() => today.value?.hexagram || null)

onMounted(async () => {
  if (route.query.toast === 'draw_limit') {
    toasts.push('Hôm nay đã gieo rồi, hẹn 0h.')
  }
  try {
    await d.load(true) // S1 luôn muốn ngày/quẻ mới nhất
  } catch {
    /* home-error render từ d.error */
  }
})
</script>

<template>
  <div class="wrap mx-auto max-w-[1180px] px-gutter pt-4">
    <header class="flex items-center gap-3">
      <span data-testid="home-seal" class="han seal" aria-hidden="true">今日</span>
      <h1 data-testid="home-logo" class="han font-bold text-h1 tracking-[0.14em]">Quẻ Hôm Nay</h1>
    </header>

    <!-- loading: không trắng màn -->
    <p v-if="d.loading.value && !d.me.value" data-testid="home-loading" class="text-muted mt-8">Đang mở bàn cờ…</p>

    <p v-else-if="d.error.value" data-testid="home-error" class="text-muted mt-8">
      Không tải được dữ liệu. Kiểm tra mạng rồi thử lại.
    </p>

    <template v-else-if="d.me.value">
      <p class="text-small text-muted mt-1">
        <span data-testid="home-server-date">{{ fmtDateVn(d.serverDateVn.value) }}</span>
      </p>

      <!-- nhánh ĐÃ GIEO -->
      <article v-if="today" data-testid="home-today-card" class="card p-5 mt-4">
        <div class="flex items-start justify-between gap-4">
          <div class="text-center">
            <div data-testid="home-hexagram-symbol" class="han text-ink" style="font-size: 44px; line-height: 1.2">
              {{ hx.symbol }}
            </div>
          </div>
          <div class="flex-1">
            <h2 data-testid="home-hexagram-name" class="han font-semibold text-h2">
              {{ hx.ten }} <span class="text-muted text-body">{{ hx.han }}</span>
            </h2>
            <p v-if="today.changing_lines?.length" data-testid="home-changing-lines" class="text-cinnabar font-semibold text-small mt-1">
              {{ changingLabel(today.changing_lines) }}
            </p>
          </div>
        </div>
        <div data-testid="home-slot-congViec" class="mt-3 border-l-2 border-gold pl-3 text-body">
          {{ hx.free_content?.congViec }}
        </div>
        <RouterLink
          data-testid="home-link-detail"
          class="btn-cinnabar mt-4"
          :to="{ name: 'detail', params: { drawId: today.id } }"
        >Xem đủ ba ngôi + bản gốc →</RouterLink>
      </article>

      <!-- nhánh CHƯA GIEO -->
      <article v-else data-testid="home-cta-card" class="card p-8 text-center mt-4">
        <h2 class="han text-h2 font-semibold">Hôm nay chưa gieo quẻ</h2>
        <p class="text-muted mt-2">Tâm tĩnh rồi hãy gieo — một quẻ một ngày.</p>
        <RouterLink data-testid="home-cta-draw" class="btn-cinnabar mt-4" :to="{ name: 'draw' }"
          >Gieo quẻ hôm nay</RouterLink
        >
      </article>

      <!-- 3 chip chủ đề -->
      <h3 data-testid="home-topics-title" class="text-h2 font-semibold mt-8 mb-2">Chủ đề luận sâu</h3>
      <ul class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <li v-for="t in TOPICS" :key="t">
          <RouterLink
            :data-testid="`home-chip-${t.replace('_', '-')}`"
            class="card flex items-center justify-between px-4 py-3 no-underline"
            :to="{ name: 'paywall', params: { topic: t } }"
          >
            <span class="text-ink font-medium">{{ TOPIC_LABELS[t] }}</span>
            <span class="flex items-center gap-2">
              <template v-if="d.entitlements.value.includes(t)">
                <span
                  :data-testid="`home-chip-${t.replace('_', '-')}-icon`"
                  class="text-gold font-bold"
                  aria-hidden="true"
                >✓</span>
                <span
                  :data-testid="`home-chip-${t.replace('_', '-')}-state`"
                  class="text-small font-semibold text-gold"
                >đã mở</span>
              </template>
              <template v-else>
                <span
                  :data-testid="`home-chip-${t.replace('_', '-')}-icon`"
                  aria-hidden="true"
                >🔒</span>
                <span
                  :data-testid="`home-chip-${t.replace('_', '-')}-price`"
                  class="text-small text-muted"
                >{{ PRICE_LABEL }}</span>
              </template>
            </span>
          </RouterLink>
        </li>
      </ul>
    </template>
  </div>
</template>
