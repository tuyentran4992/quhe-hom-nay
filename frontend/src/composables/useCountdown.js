// useCountdown — 04-ui §4: đếm ngược retry_after_seconds cho nút "Xin luận sâu".
import { ref, computed, onScopeDispose } from 'vue'

export function useCountdown() {
  const remaining = ref(0)
  const running = computed(() => remaining.value > 0)
  let timer = null

  function stop() {
    if (timer) clearInterval(timer)
    timer = null
  }
  function start(seconds) {
    stop()
    remaining.value = Math.max(0, Math.ceil(seconds))
    if (!remaining.value) return
    timer = setInterval(() => {
      remaining.value -= 1
      if (remaining.value <= 0 && timer) {
        clearInterval(timer)
        timer = null
      }
    }, 1000)
  }
  const formatted = computed(() => {
    const m = Math.floor(remaining.value / 60)
    const s = remaining.value % 60
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  })

  onScopeDispose(stop, true)
  return { remaining, running, formatted, start, stop }
}
