<script>
export default { name: 'HomeView' } // test router resolve-by-name
</script>
<script setup>
// S1 Home — HOME-FE-V3 (t_a7026e13) dựng lại theo mockup UX-HOME-V2 đã duyệt:
// 3 trạng thái A (khách mới chưa gieo) / B (đã gieo hôm nay) / C (quay lại, có lịch
// sử, hôm nay chưa gieo). Nguồn SỐNG là API: quẻ hôm nay từ #1 today_draw (draw thuần
// §3.2 — symbol/ten qua cache #2 useHexagrams.ensure), lịch sử từ #4, ngày neo
// server_date_vn (#1 — CẤM đồng hồ máy), streak TỰ SUY từ drawn_date (API chưa có
// streak field — đã báo dev-lead, xem PROGRESS), không gọi #3 bao giờ (idempotent).
// Nav in-page cũ (home-nav-draw/library) dời lên shell App.vue (NAV-SPEC).
import { onMounted, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { useHexagrams } from '../composables/useHexagrams.js'
import { useToasts } from '../composables/useToasts.js'
import { fmtDateVn } from '../utils/format.js'
import { streakFromDates } from '../utils/streak.js'
import { HOME_COPY, DONATE_LABEL, DONATE_HREF } from '../constants.js'
import HomeHero from '../components/home/HomeHero.vue'
import HomeCtaGieo from '../components/home/HomeCtaGieo.vue'
import HomeSteps from '../components/home/HomeSteps.vue'
import HomeStreakChip from '../components/home/HomeStreakChip.vue'
import HomeTodayCard from '../components/home/HomeTodayCard.vue'
import HomeTopicChips from '../components/home/HomeTopicChips.vue'
import HomeLibraryStrip from '../components/home/HomeLibraryStrip.vue'

const route = useRoute()
const d = useDevice()
const hxlib = useHexagrams()
const toasts = useToasts()

const me = d.me
const today = computed(() => d.todayDraw.value)
const hx = ref(null) // #2 tra cho quẻ hôm nay (B)
const history = ref([]) // #4 data (draw thuần §3.2)

// ── 3 trạng thái ─────────────────────────────────────────────────────────────
const state = computed(() => {
  if (!me.value) return 'loading'
  if (today.value) return 'b'
  return history.value.length || me.value.is_new_device === false ? 'c' : 'a'
})

// streak suy từ #4 neo server_date_vn — B đếm gồm hôm nay, C lùi từ hôm qua (a4)
const streak = computed(() =>
  streakFromDates(history.value.map((x) => x.drawn_date), d.serverDateVn.value),
)
const streakLabel = computed(() => {
  const n = streak.value.count
  if (!n) return ''
  return state.value === 'b' ? HOME_COPY.streakB(n) : HOME_COPY.streakC(n)
})
// note hero C: có chuỗi → liệt ngày thật; không suy được → dòng KHÔNG con số
// (API chưa có streak field — không bịa số). A → noteA định vị miễn phí.
const heroNote = computed(() => {
  if (state.value === 'a') return HOME_COPY.noteA
  if (streak.value.count >= 2) {
    return HOME_COPY.noteC(streak.value.dates.slice(-2).map((s) => s.slice(5).split('-').reverse().join('/')).join(' · '), streak.value.count)
  }
  return HOME_COPY.noteNoStreak
})

// dải Sổ quẻ State B: trám quẻ hôm nay (nó đã là thẻ to ở trên) — link /que/:id
const stripDraws = computed(() => history.value.filter((x) => x.id !== today.value?.id).slice(0, 3))

// #4 chạy song song #1 — lỗi history không được trắng màn (giữ home không streak)
async function loadHistory() {
  try {
    const r = await api.history(20)
    history.value = r.data || []
  } catch {
    history.value = []
  }
}
// #2 theo hexagram_id (FE-1 shape thật); lỗi → card vẫn hiện, pending thay glyph
async function syncHex() {
  hx.value = null
  const id = today.value?.hexagram_id
  if (!id) return
  try {
    hx.value = (await hxlib.ensure(id)) || null
  } catch {
    hx.value = null
  }
}

onMounted(async () => {
  if (route.query.toast === 'draw_limit') {
    toasts.push('Hôm nay đã gieo rồi, hẹn 0h.')
  }
  loadHistory()
  try {
    await d.load(true) // S1 luôn muốn ngày/quẻ mới nhất
  } catch {
    /* home-error render từ d.error */
  }
  syncHex()
})
</script>

<template>
  <div class="wrap mx-auto max-w-[1180px] px-gutter pt-4 pb-6">
    <!-- loading: không trắng màn -->
    <p v-if="d.loading.value && !me" data-testid="home-loading" class="text-muted mt-8">Đang mở bàn cờ…</p>

    <p v-else-if="d.error.value" data-testid="home-error" class="text-muted mt-8">
      Không tải được dữ liệu. Kiểm tra mạng rồi thử lại.
    </p>

    <template v-else-if="me">
      <!-- B: đã gieo hôm nay — thẻ quẻ là trọng lượng thị giác số 1 của màn -->
      <HomeTodayCard v-if="state === 'b'" :draw="today" :hx="hx" :streak-label="streakLabel" />

      <!-- A/C: chưa gieo — hero + streak chip (C) + CTA gieo là ngôi đầu màn -->
      <template v-else>
        <div v-if="streakLabel" class="mb-2"><HomeStreakChip :label="streakLabel" /></div>
        <HomeHero :state="state" :server-date="fmtDateVn(d.serverDateVn.value)" :note="heroNote" />
        <HomeCtaGieo />
      </template>

      <HomeLibraryStrip v-if="stripDraws.length" :draws="stripDraws" />
      <HomeSteps v-if="state === 'a'" />
      <HomeTopicChips :entitlements="d.entitlements.value" :free-deep="d.freeDeep.value" />

      <!-- Donate: chỉ ngôn ngữ free-deep (nhánh giá cũ KHÔNG thấy "Lễ tùy tâm" ở home —
           CTA donate chính nằm nav shell, gate giống nav-donate) -->
      <p v-if="d.freeDeep.value" class="text-center text-small text-muted mt-10">
        <RouterLink data-testid="home-donate-link" :to="DONATE_HREF" class="text-gold font-semibold no-underline hover:underline">{{ DONATE_LABEL }} →</RouterLink>
      </p>
    </template>
  </div>
</template>
