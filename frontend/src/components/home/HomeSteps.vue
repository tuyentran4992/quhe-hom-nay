<script setup>
// HomeSteps — hướng dẫn 3 bước (一/二/三) State A, copy từ HOME_COPY.steps.
import { HOME_COPY } from '../../constants.js'
</script>

<template>
  <section data-testid="home-steps" class="mt-10">
    <h2 class="han text-h2 font-semibold">Chỉ 5 giây, ra một quẻ</h2>
    <p class="text-small text-muted mt-1">Ba bước tới luận giải</p>
    <ol class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4 list-none p-0">
      <li v-for="(s, i) in HOME_COPY.steps" :key="s.t" :data-testid="`home-step-${i + 1}`" class="card p-4">
        <span class="han text-gold text-h2" aria-hidden="true">{{ s.no }}</span>
        <h3 class="font-semibold mt-1">{{ s.t }}</h3>
        <p class="text-small text-muted mt-1">{{ s.d }}</p>
      </li>
    </ol>
  </section>
</template>
