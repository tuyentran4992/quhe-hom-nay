<script setup>
// LuanArticle — [RL-FE t_47c88de0] toàn văn 1 bài luận đã lưu, render bằng engine
// CHUNG duy nhất parseLuan (src/utils/luanRender.js). Mượn khuôn markup của
// TopicGate phase done/saved (luan-heading/luan-body/luan-fade — class sống trong
// styles.css toàn cục của dự án) nhưng KHÔNG import TopicGate — TopicGate bất
// biến theo lệnh CEO (cấm sửa lần 4/3 ngày).
// Nợ kỹ thuật (body card mục 7 — KHÔNG làm bây giờ): đời sau TopicGate sửa →
// nó ăn LuanArticle này, xóa 2 bản copy markup (~dòng 360/~470 của TopicGate).
import { computed } from 'vue'
import { parseLuan } from '../utils/luanRender'
import { LUAN_LIST } from '../constants.js'

const props = defineProps({
  text: { type: String, default: '' }, // raw `result` từ #13
  question: { type: String, default: null },
  label: { type: String, default: '' },
})
const luanBlocks = computed(() => parseLuan(props.text))
// question null/whitespace → không dòng «Bạn hỏi:» (không bịa, cùng luật displayedQuestion TopicGate)
const shownQuestion = computed(() => (props.question || '').trim() || null)
</script>

<template>
  <article data-testid="luans-article" class="luan-fade">
    <span v-if="label" data-testid="luans-article-label" class="chip-status text-muted mb-2 inline-flex">{{ label }}</span>
    <p v-if="shownQuestion" data-testid="luans-article-question" class="text-small text-muted mb-2">{{ LUAN_LIST.questionPrefix }} {{ shownQuestion }}</p>
    <div data-testid="luan-rendered">
      <template v-for="(b, i) in luanBlocks" :key="i">
        <h4 v-if="b.heading" data-testid="luan-heading" class="han text-h2 font-semibold text-ink mt-4 mb-1">{{ b.heading }}</h4>
        <p v-if="b.text" data-testid="luan-body" class="prose-quhe text-body text-ink whitespace-pre-wrap">{{ b.text }}</p>
      </template>
    </div>
  </article>
</template>
