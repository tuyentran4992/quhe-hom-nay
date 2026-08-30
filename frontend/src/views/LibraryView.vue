<script>
export default { name: 'LibraryView' }
</script>
<script setup>
// S5 Library — 04-ui §2.S5: timeline ngược #4 (symbol nhỏ + ten + drawn_date).
// #4 chỉ trả draw §3.2 → tra #2 theo hexagram_id (cache module ≤64 quẻ, 1 request/quẻ).
// Rỗng → "Chưa có quẻ nào." Không có xóa. #10 refetch ngày mới khi quay lại màn.
import { ref, computed, onMounted } from 'vue'
import { api } from '../api/client.js'
import { useDevice } from '../composables/useDeviceApi.js'
import { fmtDateVn, changingLabel } from '../utils/format.js'

const hexCache = new Map() // hexagram_id → {symbol, ten}

const d = useDevice()
const rows = ref(null) // null=loading
const err = ref(null)
const limit = 20

async function ensureHex(id) {
  if (!hexCache.has(id)) {
    try {
      const r = await api.hexagram(id)
      hexCache.set(id, { symbol: r.data.symbol, ten: r.data.ten })
    } catch {
      hexCache.set(id, { symbol: '䷠', ten: `Quẻ #${id}` })
    }
  }
  return hexCache.get(id)
}

async function load() {
  err.value = null
  try {
    const r = await api.history(limit)
    rows.value = r.data
    await Promise.all(r.data.map((dr) => ensureHex(dr.hexagram_id)))
    d.refresh().catch(() => {}) // #10 giữ ngày/quẻ hôm nay đồng bộ
  } catch (e) {
    err.value = e
    rows.value = []
  }
}
onMounted(load)
</script>

<template>
  <div class="wrap mx-auto max-w-2xl px-gutter pt-4">
    <h1 class="han text-h1 font-semibold">Sổ quẻ của bạn</h1>

    <p v-if="rows === null && !err" data-testid="lib-loading" class="text-muted mt-6">Đang mở sổ…</p>
    <p v-else-if="err" data-testid="lib-error" class="text-muted mt-6">Không tải được sổ quẻ. <button type="button" class="underline text-bamboo" data-testid="lib-retry" @click="load">Thử lại</button></p>
    <p v-else-if="!rows.length" data-testid="lib-empty" class="text-muted mt-6">Chưa có quẻ nào.</p>

    <ol v-else data-testid="lib-timeline" class="mt-6 space-y-3">
      <li v-for="dr in rows" :key="dr.id" class="card p-4 flex items-center gap-4">
        <span data-testid="lib-item-symbol" class="han text-ink" style="font-size: 30px; line-height: 1.1">
          {{ d.todayDraw.value?.id === dr.id ? d.todayDraw.value.hexagram?.symbol : '䷠' }}
        </span>
        <span class="flex-1">
          <span data-testid="lib-item-name" class="font-medium">
            {{ d.todayDraw.value?.id === dr.id ? d.todayDraw.value.hexagram?.ten : `Quẻ #${dr.hexagram_id}` }}
          </span>
          <span data-testid="lib-item-date" class="block text-small text-muted">{{ fmtDateVn(dr.drawn_date) }}</span>
          <span v-if="dr.changing_lines?.length" class="block text-small text-cinnabar mt-0.5">{{ changingLabel(dr.changing_lines) }}</span>
        </span>
        <RouterLink
          v-if="d.todayDraw.value?.id === dr.id"
          data-testid="lib-item-link"
          class="btn-cinnabar"
          :to="{ name: 'detail', params: { drawId: dr.id } }"
        >Xem</RouterLink>
      </li>
    </ol>
    <p class="text-small text-muted mt-4">Quẻ lưu theo thiết bị — không ai khác đọc được, và không có nút xóa.</p>
  </div>
</template>
