<script setup>
// DisclaimerBar — footer fix MỌI màn (04-ui §2.S1 + §5). Wording BẮT BUỘC từng chữ.
import { DISCLAIMER_TEXT } from '../constants.js'
defineProps({ testid: { type: String, default: 'disclaimer-bar' } })
</script>

<template>
  <!-- [BUG02 FIX t_b548bbd6] footer không còn tự fixed bottom-0 — App.vue đặt nó cùng
  stack flex-col với BottomTabs (fixed bottom-0, inset-x-0) → hết class định vị độc lập
  là nguồn chồng lấn ở 375. Width 100% do stack lo. -->
  <footer class="relative z-40 bg-paper/95 backdrop-blur-sm border-t border-paper2">
    <p :data-testid="testid" class="text-small text-muted text-center px-gutter py-2">
      {{ DISCLAIMER_TEXT }}
    </p>
  </footer>
</template>
