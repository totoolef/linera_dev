import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', component: () => import('@/pages/Dashboard.vue') },
];

export const router = createRouter({
  history: createWebHistory(),
  routes,
});
