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
    <main class="flex-0 flex-1 pb-28">
      <RouterView />
    </main>
    <BottomTabs v-if="chrome" />
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
    <DisclaimerBar />
  </div>
</template>
