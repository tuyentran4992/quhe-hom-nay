// Toast stack dùng chung (04-ui §4: DRAW_LIMIT_REACHED về S1 + toast).
import { reactive } from 'vue'

const state = reactive({ list: [] })
let seq = 0

export function useToasts() {
  function push(text, ms = 4000) {
    const id = ++seq
    state.list.push({ id, text })
    setTimeout(() => {
      const i = state.list.findIndex((t) => t.id === id)
      if (i >= 0) state.list.splice(i, 1)
    }, ms)
    return id
  }
  return { list: { get value() { return state.list } }, push }
}
