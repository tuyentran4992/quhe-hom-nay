<script setup>
// App shell — DisclaimerBar fix mọi màn (04-ui §3) + toast toàn cục (DRAW_LIMIT_REACHED...).
import { provide } from 'vue'
import DisclaimerBar from './components/DisclaimerBar.vue'
import { useToasts } from './composables/useToasts.js'
const toasts = useToasts()
provide('toasts', toasts)
</script>

<template>
  <div class="min-h-dvh flex flex-col">
    <main class="flex-0 flex-1 pb-16">
      <RouterView />
    </main>
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
