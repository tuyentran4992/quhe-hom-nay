<script>
export default { name: 'DetailView' }
</script>
<script setup>
// S3 Detail — 04-ui §2.S3: bảng hào trên cùng, 3 tab ngôi (free_content),
// lưới Vận niên·Đại ý·Từ khóa, accordion Bản gốc, vùng luận sâu (TopicGate).
// Nguồn: cache #3 (state.device.todayDraw khi đi từ S2) hoặc #2 khi deep-link.
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import LineChart from '../components/LineChart.vue'
import TopicGate from '../components/TopicGate.vue'
import { changingLabel } from '../utils/format.js'

const route = useRoute()
const d = useDevice()
const draw = ref(null)
const hx = ref(null)
const loadErr = ref(null)
const tab = ref('congViec')
const TABS = [
  ['congViec', 'Công việc'],
  ['tinhDuyen', 'Tình duyên'],
  ['taiLoc', 'Tài lộc'],
]
const original = ref(false)

async function load() {
  loadErr.value = null
  const id = Number(route.params.drawId)
  // cache từ #1/#3 trước (đi máy bay nội tuyến), deep-link thì tra #2
  const t = d.todayDraw.value
  if (t && t.id === id) {
    draw.value = t
    hx.value = t.hexagram || (await api.hexagram(t.hexagram_id)).data
    return
  }
  try {
    if (!d.me.value) await d.load(true)
    if (d.todayDraw.value?.id === id) {
      draw.value = d.todayDraw.value
      hx.value = d.todayDraw.value.hexagram || (await api.hexagram(d.todayDraw.value.hexagram_id)).data
    } else {
      // draw khác ngày: MVP chỉ có quẻ hôm nay → lấy hexagram theo route param nếu là hexagram_id
      const r = await api.hexagram(id)
      hx.value = r.data
      draw.value = { id, hexagram_id: r.data.id, lines_rolled: null, changing_lines: [] }
    }
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
</script>

<template>
  <div class="wrap mx-auto max-w-3xl px-gutter pt-4">
    <p v-if="loadErr" data-testid="detail-error" class="text-muted mt-8">
      Không mở được quẻ này. <RouterLink to="/" class="underline text-bamboo">Về trang chính</RouterLink>
    </p>
    <p v-else-if="!hx" data-testid="detail-loading" class="text-muted mt-8">Đang mở bảng giải…</p>

    <template v-else>
      <header class="flex items-baseline justify-between">
        <h1 data-testid="detail-hexagram-name" class="han font-semibold text-h2">
          {{ hx.symbol }} {{ hx.ten }} <span class="text-muted text-body">{{ hx.han }}</span>
        </h1>
        <span v-if="draw && changingLabel(draw.changing_lines)" data-testid="detail-changing-lines" class="text-cinnabar font-semibold text-small">
          {{ changingLabel(draw.changing_lines) }}
        </span>
      </header>

      <!-- bảng hào trên cùng -->
      <div data-testid="detail-linechart" class="card p-5 mt-4 flex justify-center">
        <LineChart :lines="linesForChart" :changing="draw?.changing_lines || []" size="lg" />
      </div>

      <!-- 3 tab ngôi -->
      <nav role="tablist" data-testid="detail-tabs" class="flex gap-2 mt-6">
        <button
          v-for="[key, label] in TABS"
          :key="key"
          type="button"
          role="tab"
          :aria-selected="tab === key"
          :data-testid="`detail-tab-${key}`"
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
        <div class="card p-3"><dt class="text-small text-muted">Vận niên</dt><dd data-testid="detail-vv-nien" class="text-body">{{ hx.vv_nien || hx.vvanNien }}</dd></div>
        <div class="card p-3"><dt class="text-small text-muted">Đại ý</dt><dd data-testid="detail-dai-ci" class="text-body">{{ hx.dai_ci || hx.daiCI }}</dd></div>
        <div class="card p-3"><dt class="text-small text-muted">Từ khóa</dt><dd data-testid="detail-keywords" class="text-body">{{ (hx.keywords || []).join(' · ') }}</dd></div>
      </dl>

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

      <!-- vùng luận sâu (3 nhánh §2.S3) -->
      <TopicGate
        v-if="draw"
        :draw-id="draw.id"
        :topic="topicForTab"
      />
    </template>
  </div>
</template>
