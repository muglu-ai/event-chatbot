import { createRouter, createWebHistory } from 'vue-router'
import DemoView from '@/views/DemoView.vue'
import AdminView from '@/views/AdminView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'demo', component: DemoView },
    { path: '/admin', name: 'admin', component: AdminView },
  ],
})

export default router
