import { createRouter, createWebHashHistory } from 'vue-router'
import AppLayout from '../components/layout/AppLayout.vue'
import MonitoringView from '../views/MonitoringView.vue'
import FamilyView from '../views/FamilyView.vue'

const DashboardView = () => import('../views/DashboardView.vue')
const AuditView = () => import('../views/AuditView.vue')
const AdminView = () => import('../views/AdminView.vue')
const LoginView = () => import('../views/LoginView.vue')
const NotFoundView = () => import('../views/NotFoundView.vue')

const routes = [
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', name: 'home', component: MonitoringView },
      { path: 'dashboard', name: 'dashboard', component: DashboardView },
      { path: 'family', name: 'family', component: FamilyView },
      { path: 'audit', name: 'audit', component: AuditView },
      { path: 'admin', name: 'admin', component: AdminView }
    ]
  },
  { path: '/login', name: 'login', component: LoginView },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView }
]

const router = createRouter({
  // Using WebHashHistory due to Apache missing rewrite rules
  history: createWebHashHistory(),
  routes,
})

export default router
