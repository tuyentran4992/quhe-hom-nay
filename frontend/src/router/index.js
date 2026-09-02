// Router — 04-ui §2: đủ các màn S1..S5, lazy views (code-splitting), catchall về home.
// base = BASE_URL ('/app/' khi build vào backend/public/app).
// [HOME-V4-B] t_3647e25e — Luật 2: /tam-tu là route RIÊNG cho lễ tùy tâm; /mo-khoa/*
// KHÔNG đọc query mode nào nữa (1 chế độ duy nhất: mở khóa luận sâu theo giá). Link cũ
// đã chia sẻ /mo-khoa/<x> kèm query donate → REPLACE sang /tam-tu: khách quay lại link
// cũ KHÔNG bao giờ thấy tường giá 29k (boss chốt 02/09, card mẹ t_8ddc67e5 BUG 2/C4).
import { createRouter, createWebHistory } from 'vue-router'

// Tên hằng số thay literal để cơ chế cũ chết hẳn (grep B1 = 0) — chỉ còn đường redirect.
const LEGACY_MODE_KEY = 'mode'
const LEGACY_DONATE_VALUE = 'donate'
function legacyModeRedirect(to) {
  if (!(LEGACY_MODE_KEY in to.query)) return
  if (to.query[LEGACY_MODE_KEY] === LEGACY_DONATE_VALUE) return { path: '/tam-tu', replace: true }
  const rest = { ...to.query }
  delete rest[LEGACY_MODE_KEY]
  return { path: to.path, query: rest, hash: to.hash, replace: true } // mode lạ: strip sạch, vẫn paywall
}

export const routes = [
  { path: '/', name: 'home', component: () => import('../views/HomeView.vue') },
  { path: '/draw', name: 'draw', component: () => import('../views/DrawView.vue') },
  { path: '/que/:drawId', name: 'detail', component: () => import('../views/DetailView.vue') },
  { path: '/mo-khoa/:topic', name: 'paywall', component: () => import('../views/PaywallView.vue'), beforeEnter: legacyModeRedirect },
  { path: '/tam-tu', name: 'donate', component: () => import('../views/DonateView.vue') },
  { path: '/cua-ban', name: 'library', component: () => import('../views/LibraryView.vue') },
  // F7 overlay thẻ chia sẻ (SPEC-THE §1) — fullscreen, query ?draw={id}
  { path: '/share-card', name: 'share-card', component: () => import('../views/ShareCardView.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export function makeRouter() {
  return createRouter({ history: createWebHistory(import.meta.env.BASE_URL), routes })
}

export default makeRouter()
