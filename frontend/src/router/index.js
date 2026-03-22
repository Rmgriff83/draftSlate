import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/pages/MarketingHome.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/LoginPage.vue'),
    meta: { layout: 'auth', guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/pages/RegisterPage.vue'),
    meta: { layout: 'auth', guest: true },
  },
  {
    path: '/app',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/DashboardPage.vue'),
      },
      {
        path: 'leagues',
        name: 'leagues',
        component: () => import('@/pages/LeaguesPage.vue'),
      },
      {
        path: 'leagues/browse',
        name: 'leagues-browse',
        component: () => import('@/pages/LeagueBrowsePage.vue'),
      },
      {
        path: 'leagues/create',
        name: 'leagues-create',
        component: () => import('@/pages/LeagueCreatePage.vue'),
      },
      {
        path: 'leagues/join/:code',
        name: 'leagues-invite',
        component: () => import('@/pages/LeagueInvitePage.vue'),
      },
      {
        path: 'leagues/:id',
        name: 'league-view',
        component: () => import('@/pages/LeagueViewPage.vue'),
      },
      {
        path: 'leagues/:id/draft',
        name: 'league-draft',
        component: () => import('@/pages/DraftPage.vue'),
      },
      {
        path: '',
        redirect: { name: 'dashboard' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/pages/NotFoundPage.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.checked) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
