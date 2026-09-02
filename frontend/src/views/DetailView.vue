<script>
export default { name: 'DetailView' }
</script>
<script setup>
// S3 Detail — 04-ui §2.S3: bảng hào trên cùng, 3 tab ngôi (free_content),
// lưới Vận niên·Đại ý·Từ khóa, accordion Bản gốc, vùng luận sâu (TopicGate).
// FE-1 (shape thật 03-api): #1/#3 KHÔNG embed hexagram trong draw →
//  - draw hôm nay: lines_rolled/changing_lines từ draw, nội dung quẻ từ cache #2
//    (prime từ #3 khi đi từ S2 → zero-fetch; refresh thì ensure #2).
//  - deep-link quẻ KHÁC ngày: contract không có GET /draws/{id} → resolve draw
//    qua #4 history (limit 50), rồi #2 theo hexagram_id. Không thấy → detail-error.
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { useHexagrams } from '../composables/useHexagrams.js'
import { useHaoTexts } from '../composables/useHaoTexts.js'
import LineChart from '../components/LineChart.vue'
import LuanHomNay from '../components/LuanHomNay.vue'
import TopicGate from '../components/TopicGate.vue'
import { changingLabel } from '../utils/format.js'

const route = useRoute()
const router = useRouter()
const d = useDevice()
const hxlib = useHexagrams()
const haolib = useHaoTexts()
const draw = ref(null)
const hx = ref(null)
const haoDong = ref([]) // FE-3XU: mảng từ hào của các hào động (sơ→thượng)
const loadErr = ref(null)
const tab = ref('congViec')
const TABS = [
  ['congViec', 'cong-viec', 'Công việc'],
  ['tinhDuyen', 'tinh-duyen', 'Tình duyên'],
  ['taiLoc', 'tai-loc', 'Tài lộc'],
]
const original = ref(false)
// [HOME-V4-A L1] deep-link từ chip home: ?topic= mở ĐÚNG tab đầu tiên
// (congViec|tinhDuyen|taiLoc; values khác/khuyết → mặc định hiện hành congViec).
const TOPIC_QUERY_TABS = ['congViec', 'tinhDuyen', 'taiLoc']
if (TOPIC_QUERY_TABS.includes(route.query.topic)) tab.value = route.query.topic

async function resolveDraw(id) {
  // 1) draw hôm nay từ #1 (kể cả đã prime bởi S2)
  if (!d.me.value) await d.load(true)
  if (d.todayDraw.value?.id === id) return d.todayDraw.value
  // 2) quẻ quá khứ: tìm trong #4 (mVP không phân trang, limit tối đa 50)
  const h = await api.history(50)
  return (h.data || []).find((dr) => dr.id === id) || null
}

async function load() {
  loadErr.value = null
  const id = Number(route.params.drawId)
  if (!Number.isInteger(id) || id <= 0) {
    loadErr.value = new Error('bad id')
    return
  }
  try {
    const dr = await resolveDraw(id)
    if (!dr) {
      loadErr.value = new Error('not found')
      return
    }
    draw.value = dr
    // FE-3XU: song song #2 + #2b (≥1 hào động mới xin từ hào; 0 = hợp lệ, chỉ Đại ý)
    const changing = dr.changing_lines || []
    const [hxGot, haoGot] = await Promise.all([
      hxlib.get(dr.hexagram_id) || hxlib.ensure(dr.hexagram_id),
      changing.length
        ? (async () => {
            const cached = haolib.get(dr.hexagram_id, changing)
            return cached.length ? cached : (await haolib.ensure(dr.hexagram_id, changing)) || []
          })()
        : [],
    ])
    hx.value = hxGot
    haoDong.value = haoGot
  } catch (e) {
    loadErr.value = e
  }
}
onMounted(load)

const free = computed(() => hx.value?.free_content || {})
const bg = computed(() => hx.value?.ban_goc || null)
const linesForChart = computed(() => draw.value?.lines_rolled || hx.value?.lines || [])
// tab ngôi (04-ui) map topic luận sâu C-02: công việc→xuất hành (khởi sự, đi lại),
// tình duyên→duyen, tài lộc→tai_loc. Đây là ánh xạ hiển thị S3; QA đối chiếu 04-ui §2.S3.
const topicForTab = computed(() => ({ congViec: 'xuat_hanh', tinhDuyen: 'duyen', taiLoc: 'tai_loc' })[tab.value])

// ===== [F8-FE C3] t_03424b76 — CTA "Lễ tùy tâm ủng hộ" (chip paper2, chỉ khi free_deep) =====
// Nút render = luận đã tải xong (hx có trong template v-else) VÀ d.freeDeep (key C1, không suy từ entitlements).
// [HOME-V4-B] t_3647e25e — Luật 2: đích đến là route RIÊNG /tam-tu (query mode cũ trên
// paywall chết hẳn). Event tracking donate_cta_* giữ nguyên name + props.
const donateCtaVisible = computed(() => !!hx.value && !!draw.value && d.freeDeep.value)
function openDonateCta() {
  api.track({ name: 'donate_cta_click', props: { topic: topicForTab.value } }).catch(() => {}) // fire-and-forget (C2)
  router.push({ name: 'donate' })
}
// donate_cta_shown: ĐÚNG 1 lần khi nút THỰC SỰ render (C3) — flag OFF → không bắn; reload data
// trong cùng mount không bắn lại (bắn theo nút, không theo tab).
let donateShownFired = false
watch(donateCtaVisible, (v) => {
  if (v && !donateShownFired) {
    donateShownFired = true
    api.track({ name: 'donate_cta_shown', props: { topic: topicForTab.value } }).catch(() => {})
  }
}, { immediate: true })
</script>

<template>
  <div class="wrap mx-auto max-w-3xl px-gutter pt-4">
    <p v-if="loadErr" data-testid="detail-error" class="text-muted mt-8">
      Không mở được quẻ này. <RouterLink to="/" class="underline text-bamboo">Về trang chính</RouterLink>
    </p>
    <p v-else-if="!hx" data-testid="detail-loading" class="text-muted mt-8">Đang mở bảng giải…</p>

    <template v-else>
      <!-- [UI-POLISH t_fc6387df] Nhịp đọc: tên quẻ DISPLAY serif (token han/text-h1)
           tách khỏi hàng chip trạng thái — mọi chip cùng 1 kiểu thẻ `chip-status`. -->
      <header class="mt-2">
        <h1 data-testid="detail-hexagram-name" class="han text-h1 font-semibold text-ink leading-tight">
          {{ hx.symbol }} {{ hx.ten }} <span class="text-gold">{{ hx.han }}</span>
        </h1>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <span data-testid="detail-chip-index" class="chip-status text-muted">Chỉ mục {{ hx.id }}</span>
          <span v-if="draw && changingLabel(draw.changing_lines)" data-testid="detail-changing-lines" class="chip-status text-cinnabar">
            {{ changingLabel(draw.changing_lines) }}
          </span>
          <span v-if="d.freeDeep.value" data-testid="detail-chip-free" class="chip-status text-bamboo">Luận sâu miễn phí hôm nay</span>
        </div>
      </header>

      <!-- bảng hào trên cùng -->
      <div data-testid="detail-linechart" class="card p-5 mt-4 flex justify-center">
        <LineChart :lines="linesForChart" :changing="draw?.changing_lines || []" size="lg" />
      </div>

      <!-- 3 tab ngôi -->
      <nav role="tablist" data-testid="detail-tabs" class="flex gap-2 mt-6">
        <button
          v-for="[key, tid, label] in TABS"
          :key="key"
          type="button"
          role="tab"
          :aria-selected="tab === key"
          :data-testid="`detail-tab-${tid}`"
          class="px-3 py-1.5 rounded-card font-medium"
          :class="tab === key ? 'bg-cinnabar text-paper' : 'bg-paper2 text-muted'"
          @click="tab = key"
        >{{ label }}</button>
      </nav>
      <div data-testid="detail-free-slot" class="mt-3 border-l-2 border-gold pl-3 text-body">
        {{ free[tab] }}
      </div>

      <!-- lưới Vận niên · Đại ý · Từ khóa -->
      <dl class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-6">
        <div class="card p-3"><dt class="text-small text-muted">Vận niên</dt><dd data-testid="detail-vv-nien" class="text-body">{{ hx.vv_nien }}</dd></div>
        <div class="card p-3"><dt class="text-small text-muted">Đại ý</dt><dd data-testid="detail-dai-ci" class="text-body">{{ hx.dai_ci }}</dd></div>
        <div class="card p-3"><dt class="text-small text-muted">Từ khóa</dt><dd data-testid="detail-keywords" class="text-body">{{ (hx.keywords || []).join(' · ') }}</dd></div>
      </dl>

      <!-- LUẬN HÔM NAY — FE-3XU (04-ui §S3) + [UI-POLISH t_fc6387df] tách component
           (>250 dòng chống god-file): nhịp tiểu dẫn — đại ý — kicker "Từ hào" — kết. -->
      <LuanHomNay :dai-ci="hx.dai_ci" :hao-dong="haoDong" />

      <!-- accordion Bản gốc -->
      <section v-if="bg" class="mt-6">
        <button
          type="button"
          data-testid="detail-original-toggle"
          class="w-full text-left font-semibold text-bamboo"
          :aria-expanded="original"
          @click="original = !original"
        >Bản gốc {{ original ? '▴' : '▾' }}</button>
        <div v-show="original" data-testid="detail-original-body" class="mt-2 space-y-3">
          <div v-if="bg.quaTu" class="card p-3">
            <p class="han text-h2 text-ink">{{ bg.quaTu.han }}</p>
            <p class="text-small text-muted">{{ bg.quaTu.am }}</p>
            <p class="text-body mt-1">{{ bg.quaTu.nghia }}</p>
          </div>
          <div v-if="bg.haoTu?.length" class="card p-3 space-y-2">
            <p class="text-small text-muted font-semibold">Hào Từ</p>
            <div v-for="l in bg.haoTu" :key="l.vi">
              <p class="han text-ink">{{ l.hao }}: {{ l.han }}</p>
              <p class="text-small text-muted">{{ l.am }} — {{ l.nghia }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- [UI-POLISH t_fc6387df] Hàng hành động phân cấp 2 bậc (SOUL finish-gate):
           BẬC 1 — "Lễ tùy tâm ủng hộ": btn-cinnabar (ấn ngo + hover/focus-visible/disabled
           qua class nền styles.css) = phần tử nổi bật nhất row, testid donate-cta-open giữ.
           BẬC 2 — "Chia sẻ thẻ quẻ" outline + TopicGate "Xin luận sâu" (giáng cấp qua
           scoped CSS khi row có has-donate-cta — CTA trả phí KHÔNG bị yếu khi donate ẩn).
           F7 (SPEC-THE §1): nút share vẫn trong template v-else, click → /share-card?draw=. -->
      <div
        data-testid="detail-actions"
        class="mt-8 flex flex-wrap items-center gap-3"
        :class="{ 'has-donate-cta': donateCtaVisible }"
      >
        <button
          type="button"
          data-testid="share-card-open"
          class="btn-outline inline-flex items-center gap-2 px-4 py-2 rounded-card font-medium"
          @click="router.push({ name: 'share-card', query: { draw: String(draw.id) } })"
        >Chia sẻ thẻ quẻ</button>
        <TopicGate
          v-if="draw"
          :draw-id="draw.id"
          :topic="topicForTab"
        />
        <!-- [F8-FE C3] t_03424b76 + polish: CTA donate BẬC 1 — btn-cinnabar + hover/
             focus-visible/disabled; click → donate_cta_click + màn /tam-tu (flow C2 giữ). -->
        <button
          v-if="donateCtaVisible"
          type="button"
          data-testid="donate-cta-open"
          class="btn-cinnabar inline-flex items-center gap-2 px-5 py-2.5 font-semibold shadow-lift hover:brightness-110 hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold disabled:opacity-50 disabled:cursor-not-allowed"
          @click="openDonateCta"
        >Lễ tùy tâm ủng hộ</button>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* Nhịp đọc đồng bộ (t_fc6387df): hàng nút chỉ BẰNG NHAU khi donate thật sự hiện
   (freeDeep) — đây là hàng hành động DONATE, CTA donate phải nhất quán nổi bật.
   Khi donate ẩn, CTA 29k của TopicGate giữ NGUYÊN trọng lượng (không giáng cấp
   paywall logic — ngoài phạm vi card). */
.has-donate-cta :deep([data-testid="gate-ask"]),
.has-donate-cta :deep([data-testid="gate-cta-paywall"]) {
  @apply inline-flex items-center gap-2 px-4 py-2 rounded-card font-medium bg-transparent text-ink border border-cinnabar/50 shadow-none;
}
.has-donate-cta :deep([data-testid="gate-ask"]:hover),
.has-donate-cta :deep([data-testid="gate-cta-paywall"]:hover) {
  @apply bg-cinnabar/5;
}
</style>
