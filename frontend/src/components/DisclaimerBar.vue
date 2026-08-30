<script setup>
// DisclaimerBar — footer fix MỌI màn (04-ui §2.S1 + §5). Wording BẮT BUỘC từng chữ.
import { DISCLAIMER_TEXT } from '../constants.js'
defineProps({ testid: { type: String, default: 'disclaimer-bar' } })
</script>

<template>
  <footer class="fixed bottom-0 inset-x-0 z-40 bg-paper/95 backdrop-blur-sm border-t border-paper2">
    <p :data-testid="testid" class="text-small text-muted text-center px-gutter py-2">
      {{ DISCLAIMER_TEXT }}
    </p>
  </footer>
</template>
