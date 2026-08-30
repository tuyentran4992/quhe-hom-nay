import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// 01-overview: outDir ../backend/public/app · 05-testplan: dev 5173 proxy /api→8000
// base '/app/' vì Blade/Laravel route '/' sẽ serve nguyên nội dung
// backend/public/app/index.html — asset URL phải absolute /app/assets/*.
export default defineConfig({
  base: '/app/',
  plugins: [vue()],
  build: {
    outDir: '../backend/public/app',
    emptyOutDir: true,
    sourcemap: false,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
  test: {
    environment: 'jsdom',
    globals: false,
    include: ['tests/**/*.test.js'],
  },
})
