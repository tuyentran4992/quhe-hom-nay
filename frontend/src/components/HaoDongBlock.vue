<script setup>
// HaoDongBlock — 04-ui §3: 1 khối TỪ HÀO của 1 hào động (props: phần tử hao_texts #2b/#3).
// Layout bám mockup: nhãn `hao` + `han` font han + `quoc_am` + `nghia`. Không tự thêm
// gì ngoài 4 field contract (quẻ biến cấm ra UI — gate t_04394e77).
defineProps({
  text: { type: Object, required: true }, // {vi, hao, han, quoc_am, nghia}
})
</script>

<template>
  <div class="card p-3" data-testid="hao-dong-block" :data-vi="text.vi">
    <p data-testid="hao-dong-label" class="text-small font-semibold text-cinnabar">{{ text.hao }}</p>
    <p data-testid="hao-dong-han" class="han text-h2 text-ink mt-1">{{ text.han }}</p>
    <p data-testid="hao-dong-quocam" class="text-small text-muted mt-1">{{ text.quoc_am }}</p>
    <p data-testid="hao-dong-nghia" class="text-body mt-1">{{ text.nghia }}</p>
  </div>
</template>
