// Entry — import font subsets TỰ HOST (04-ui §1: subset latin+vi, font-display swap
// có sẵn trong @fontsource). KHÔNG import chinese-traditional full 1.3M×3 weight vào
// main bundle → chỉ 400/600/700 latin + vietnamese cho body, han 400/700 cho Hán tự.
import { createApp } from 'vue'
import '@fontsource/be-vietnam-pro/latin-400.css'
import '@fontsource/be-vietnam-pro/latin-500.css'
import '@fontsource/be-vietnam-pro/latin-600.css'
import '@fontsource/be-vietnam-pro/latin-700.css'
import '@fontsource/be-vietnam-pro/vietnamese-400.css'
import '@fontsource/be-vietnam-pro/vietnamese-500.css'
import '@fontsource/be-vietnam-pro/vietnamese-600.css'
import '@fontsource/be-vietnam-pro/vietnamese-700.css'
import '@fontsource/noto-serif-tc/chinese-traditional-400.css'
import '@fontsource/noto-serif-tc/chinese-traditional-700.css'
import '@fontsource/noto-serif-tc/latin-400.css'
import './styles.css'
import App from './App.vue'
import router from './router/index.js'

createApp(App).use(router).mount('#app')
