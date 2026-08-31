<script>
export default { name: 'ShareCardView' }
</script>
<script setup>
// F7-FE overlay /share-card — SPEC-THE §1: fullscreen, thẻ THẬT (canvas render),
// 2 toggle khung (9:16 story / 1:1 feed), 3 hành động (Tải ảnh / Copy link / Chia sẻ).
//Tracking V1–V4 fire-and-forget (utils/track). E1 render fail → fallback thẻ HTML,
// copy vẫn chạy. Link fail → URL dự phòng /que/{id}, KHÔNG bắn error (không chặn UX).
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { useHexagrams } from '../composables/useHexagrams.js'
import { useHaoTexts } from '../composables/useHaoTexts.js'
import { buildCardModel, renderCaption } from '../utils/shareCard.js'
import { renderFrame, FRAME_9X16, FRAME_1X1 } from '../utils/shareCardCanvas.js'
import { trackShareCard } from '../utils/track.js'
import { CAPTION_NATIVE, CAPTION_1X1 } from '../constants.js'

const route = useRoute()
const router = useRouter()
const d = useDevice()
const hxlib = useHexagrams()
const haolib = useHaoTexts()

const model = ref(null)
const frame = ref(FRAME_9X16)
const shot = ref(null) // {canvas, blob, dataUrl, ms} của khung hiện tại
const fallback = ref(false)
let lastFrame = null

/** resolve draw theo id: hôm nay (#1) → history (#4). Like DetailView (không có GET /draws/{id}). */
async function resolveDraw(id) {
  if (!d.me.value) await d.load(true)
  if (d.todayDraw.value?.id === id) return d.todayDraw.value
  const h = await api.history(50)
  return (h.data || []).find((dr) => dr.id === id) || null
}

async function boot() {
  const id = Number(route.query.draw)
  if (!Number.isInteger(id) || id <= 0) return goBack()
  try {
    const draw = await resolveDraw(id)
    if (!draw) return goBack()
    const changing = draw.changing_lines || []
    const [hxGot, haoGot] = await Promise.all([
      hxlib.get(draw.hexagram_id) || hxlib.ensure(draw.hexagram_id),
      changing.length
        ? (async () => {
            const cached = haolib.get(draw.hexagram_id, changing)
            return cached.length ? cached : (await haolib.ensure(draw.hexagram_id, changing)) || []
          })()
        : [],
    ])
    // link chia sẻ (F7-CONTRACT §2) — fail thì URL dự phòng trong app, KHÔNG phải E1
    let link = null
    try {
      link = await api.shareLinks(draw.id)
    } catch {
      link = null
    }
    const url = link?.url || `${location.origin}/que/${draw.id}`
    const m = buildCardModel({ draw, hexagram: hxGot, haoDong: haoGot, url, token: link?.token ?? null })
    m.caption_1x1 = renderCaption(CAPTION_1X1, m.ten) // dòng phụ khung 1:1 (CAP-THE §4)
    model.value = m
    trackShareCard.open({ draw_id: m.draw_id, hexagram_id: m.hexagram_id, has_dynamic_line: m.has_dynamic_line }) // V1
    await paint(m, FRAME_9X16)
  } catch {
    goBack()
  }
}

/** Vẽ 1 khung → shot; E1: throw → fallback HTML + V3 share_card_error. */
async function paint(m, f) {
  try {
    const r = await renderFrame(m, f, { qrText: m.qr_text })
    shot.value = r
    fallback.value = false
    if (lastFrame !== f.key) {
      lastFrame = f.key
      trackShareCard.created({ draw_id: m.draw_id, frame: f.key, render_ms: r.ms }) // V2
    }
  } catch (e) {
    shot.value = null
    fallback.value = true
    trackShareCard.error({ draw_id: m.draw_id, reason: (e && e.message) || 'render_failed' }) // V3
  }
}

function goBack() {
  router.replace({ name: 'detail', params: { drawId: String(route.query.draw || '') } })
}

async function pickFrame(f) {
  if (!model.value || frame.value === f) return
  frame.value = f
  await paint(model.value, f)
}

const fileName = computed(() => `que-${model.value?.token || `draw-${model.value?.draw_id}`}.png`)

async function getCanvasBlob() {
  if (shot.value?.blob) return shot.value.blob
  const canvas = shot.value?.canvas
  if (!canvas) return null
  return new Promise((res) => canvas.toBlob((b) => res(b), 'image/png'))
}

/** Copy link — 9:16 = NGUYÊN URL; 1:1 = CAPTION_1X1 + "\n" + URL (CAP-THE §4). */
async function copyLink() {
  const m = model.value
  if (!m) return
  const text = frame.value.key === '1x1' ? `${m.caption_1x1}\n${m.url}` : m.url
  try {
    await navigator.clipboard.writeText(text)
    trackShareCard.done({ draw_id: m.draw_id, method: 'copy', token: m.token }) // V4
  } catch {
    /* clipboard từ chối (permission/http) — im lặng, không alert */
  }
}

/** Tải ảnh PNG — anchor download, tên que-{token}.png. */
async function download() {
  const m = model.value
  if (!m) return
  const blob = await getCanvasBlob()
  if (!blob) return
  const a = document.createElement('a')
  const objUrl = URL.createObjectURL(blob)
  a.href = objUrl
  a.download = fileName.value
  a.click()
  URL.revokeObjectURL(objUrl)
  trackShareCard.done({ draw_id: m.draw_id, method: 'download', token: m.token }) // V4
}

/** Web Share API (E2: cancel/unsupported → IM LẶNG, không bắn done). */
async function shareNative() {
  const m = model.value
  if (!m || !navigator.share) return
  try {
    const blob = await getCanvasBlob()
    const data = { text: renderCaption(CAPTION_NATIVE, m.ten), url: m.url }
    if (blob) {
      data.files = [new File([blob], fileName.value, { type: 'image/png' })]
      delete data.url // có files thì không cần url (một số UA cấm cả hai)
    }
    await navigator.share(data)
    trackShareCard.done({ draw_id: m.draw_id, method: 'native', token: m.token }) // V4
  } catch {
    /* AbortError / not supported — E2: im lặng tuyệt đối */
  }
}

onMounted(boot)
</script>

<template>
  <div data-testid="share-card-open" class="fixed inset-0 z-50 bg-ink/90 flex flex-col items-center justify-center p-4 gap-4">
    <template v-if="model">
      <!-- ảnh thẻ THẬT (canvas → PNG), fallback E1 = thẻ HTML tối giản -->
      <img
        v-if="shot && !fallback"
        data-testid="share-card-image"
        :src="shot.dataUrl"
        :alt="`Thẻ quẻ ${model.ten}`"
        class="max-h-[62vh] w-auto rounded-card shadow-lg"
      />
      <div v-else-if="fallback" data-testid="share-card-fallback" class="bg-paper text-ink rounded-card p-6 max-w-sm w-full text-center">
        <p class="han text-h2">{{ model.symbol }}</p>
        <p class="font-semibold text-h2 mt-1">{{ model.ten }}</p>
        <p class="text-body mt-2">{{ model.hook_text }}</p>
        <p class="text-small text-muted mt-2">{{ model.url }}</p>
        <p class="text-small text-muted mt-3">{{ model.disclaimer }}</p>
      </div>
      <p v-else class="text-paper/80">Đang dựng thẻ…</p>

      <!-- toggle 2 khung (SPEC-THE §1) -->
      <div role="group" aria-label="Khổ thẻ" class="flex gap-2">
        <button
          type="button"
          data-testid="share-card-frame-9x16"
          :aria-pressed="frame.key === '9x16'"
          class="px-4 py-2 rounded-card font-medium"
          :class="frame.key === '9x16' ? 'bg-cinnabar text-paper' : 'bg-paper2 text-muted'"
          @click="pickFrame(FRAME_9X16)"
        >Story 9:16</button>
        <button
          type="button"
          data-testid="share-card-frame-1x1"
          :aria-pressed="frame.key === '1x1'"
          class="px-4 py-2 rounded-card font-medium"
          :class="frame.key === '1x1' ? 'bg-cinnabar text-paper' : 'bg-paper2 text-muted'"
          @click="pickFrame(FRAME_1X1)"
        >Feed 1:1</button>
      </div>

      <!-- 3 hành động -->
      <div class="flex flex-wrap gap-2 justify-center">
        <button
          type="button"
          data-testid="share-card-download"
          class="px-4 py-2 rounded-card bg-paper text-ink font-medium"
          :disabled="!shot"
          @click="download"
        >Tải ảnh</button>
        <button
          type="button"
          data-testid="share-card-copy-link"
          class="px-4 py-2 rounded-card bg-paper2 text-ink font-medium"
          @click="copyLink"
        >Copy link</button>
        <button
          type="button"
          data-testid="share-card-native"
          class="px-4 py-2 rounded-card bg-bamboo text-paper font-medium"
          @click="shareNative"
        >Chia sẻ</button>
      </div>
    </template>
    <button type="button" data-testid="share-card-close" class="text-paper/80 underline text-small mt-2" @click="goBack">Đóng</button>
  </div>
</template>
