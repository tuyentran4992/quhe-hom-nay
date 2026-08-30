// Router — 04-ui §2: đủ 5 màn S1..S5, lazy views (code-splitting), catchall về home.
// base = BASE_URL ('/app/' khi build vào backend/public/app).
import { createRouter, createWebHistory } from 'vue-router'

export const routes = [
  { path: '/', name: 'home', component: () => import('../views/HomeView.vue') },
  { path: '/draw', name: 'draw', component: () => import('../views/DrawView.vue') },
  { path: '/que/:drawId', name: 'detail', component: () => import('../views/DetailView.vue') },
  { path: '/mo-khoa/:topic', name: 'paywall', component: () => import('../views/PaywallView.vue') },
  { path: '/cua-ban', name: 'library', component: () => import('../views/LibraryView.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export function makeRouter() {
  return createRouter({ history: createWebHistory(import.meta.env.BASE_URL), routes })
}

export default makeRouter()
