<script setup>
// App shell — DisclaimerBar fix mọi màn (04-ui §3) + toast toàn cục (DRAW_LIMIT_REACHED...)
// + HOME-FE-V3/NAV-SPEC §1: NavBar desktop + BottomTabs mobile mount cùng chỗ, LOẠI TRỪ
// /draw (nghi thức — DrawView tự có draw-back) và /share-card (overlay fullscreen).
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import DisclaimerBar from './components/DisclaimerBar.vue'
import NavBar from './components/NavBar.vue'
import BottomTabs from './components/BottomTabs.vue'
import { useToasts } from './composables/useToasts.js'
const toasts = useToasts()
const route = useRoute()
// §1c loại trừ: đường nav shell không được lạc người dùng giữa nghi thức/overlay
const chrome = computed(() => route.name !== 'draw' && route.name !== 'share-card')
</script>

<template>
  <div class="min-h-dvh flex flex-col">
    <NavBar v-if="chrome" />
    <!-- pb-32 (128px) ≥ chiều cao stack chrome mobile lớn nhất (tabs ~58 + disclaimer
    wrap 2 dòng ~55 = ~113 ở 375) — đáy card cuối trang không bị mép stack cắt.
    Desktop: stack chỉ còn disclaimer → pb lớn hơn chỉ là vùng trống cuối trang, không đổi visual chrome. -->
    <main class="flex-0 flex-1 pb-32">
      <RouterView />
    </main>
    <!-- [BUG02 FIX t_b548bbd6 / QA-5E25-02] mobile 375: tab fixed bottom-9 + disclaimer
    fixed bottom-0 định vị ĐỘC LẬP → disclaimer wrap 2 dòng (55px) bị đè 19px. Gộp vào
    MỘT stack flex-col fixed bottom-0: chiều cao stack = tổng 2 phần tự co giãn,
    overlap = 0 THEO CẤU TRÚC (disclaimer wrap bao nhiêu dòng cũng không thể đè tab).
    Desktop ≥768: BottomTabs md:hidden (display:none, không chiếm chỗ) → stack còn đúng
    disclaimer y như cũ. z tab 45 > disc 40 giữ nguyên thứ tự lớp cho QA e2e. -->
    <div class="fixed bottom-0 inset-x-0 z-40 flex flex-col">
      <BottomTabs v-if="chrome" />
      <DisclaimerBar />
    </div>
    <div aria-live="polite" data-testid="toast-stack" class="fixed bottom-14 inset-x-0 z-50 flex flex-col items-center gap-2 px-gutter pointer-events-none">
      <p
        v-for="t in toasts.list.value"
        :key="t.id"
        :data-testid="'toast-' + t.id"
        class="card bg-ink text-paper text-body px-4 py-2 shadow-lift"
      >
        {{ t.text }}
      </p>
    </div>
  </div>
</template>
