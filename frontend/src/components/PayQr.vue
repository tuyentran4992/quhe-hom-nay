<script setup>
// PayQr — 04-ui §2.S4: render qr_data thành PNG qua lib qrcode (dynamic import,
// chỉ tải khi màn S4 cần — code-splitting). show confirm_url stub nếu qr_data vắng.
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  qrData: { type: String, default: '' },
  confirmUrl: { type: String, default: '' },
  amountLabel: { type: String, default: '' },
})
const img = ref(null)
const err = ref(false)

async function render() {
  err.value = false
  if (!props.qrData || !img.value) return
  try {
    const QRCode = (await import('qrcode')).default
    const url = await QRCode.toDataURL(props.qrData, {
      margin: 1,
      width: 240,
      color: { dark: '#1E1B18', light: '#F7F2E7' },
    })
    img.value.src = url
  } catch {
    err.value = true
  }
}
onMounted(render)
watch(() => props.qrData, render)
</script>

<template>
  <div class="flex flex-col items-center gap-2" data-testid="pay-qr">
    <img
      v-show="qrData && !err"
      ref="img"
      alt="Mã QR thanh toán"
      class="rounded-card border border-gold/40 bg-paper"
      width="240"
      height="240"
    />
    <a
      v-if="!qrData || err"
      :data-testid="confirmUrl ? 'pay-confirm-link' : undefined"
      :href="confirmUrl || '#'"
      class="text-body underline text-bamboo"
      >Mở trang thanh toán</a
    >
    <p v-if="amountLabel" class="text-small text-muted">{{ amountLabel }}</p>
  </div>
</template>
