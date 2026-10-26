import { createRouter, createWebHistory } from 'vue-router'
import { authState, restoreSession } from '@/stores/auth'

const AdminPage = () => import('@/pages/AdminPage.vue')
const ClaimsPage = () => import('@/pages/ClaimsPage.vue')
const EditItemPage = () => import('@/pages/EditItemPage.vue')
const HomePage = () => import('@/pages/HomePage.vue')
const ItemDetailPage = () => import('@/pages/ItemDetailPage.vue')
const LoginPage = () => import('@/pages/LoginPage.vue')
const MyItemsPage = () => import('@/pages/MyItemsPage.vue')
const NotFoundPage = () => import('@/pages/NotFoundPage.vue')
const PublishItemPage = () => import('@/pages/PublishItemPage.vue')
const RegisterPage = () => import('@/pages/RegisterPage.vue')

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomePage,
    meta: { title: 'Início' },
  },
  {
    path: '/objectos/:id',
    name: 'item-detail',
    component: ItemDetailPage,
    props: true,
    meta: { title: 'Detalhes do objecto' },
  },
  {
    path: '/entrar',
    name: 'login',
    component: LoginPage,
    meta: { title: 'Entrar', guestOnly: true },
  },
  {
    path: '/registar',
    name: 'register',
    component: RegisterPage,
    meta: { title: 'Criar conta', guestOnly: true },
  },
  {
    path: '/publicar',
    name: 'publish',
    component: PublishItemPage,
    meta: { title: 'Publicar objecto', requiresAuth: true },
  },
  {
    path: '/os-meus-objectos',
    name: 'my-items',
    component: MyItemsPage,
    meta: { title: 'Os meus objectos', requiresAuth: true },
  },
  {
    path: '/os-meus-objectos/:id/editar',
    name: 'edit-item',
    component: EditItemPage,
    props: true,
    meta: { title: 'Editar objecto', requiresAuth: true },
  },
  {
    path: '/reclamacoes',
    name: 'claims',
    component: ClaimsPage,
    meta: { title: 'Reclamações', requiresAuth: true },
  },
  {
    path: '/admin/:section?',
    name: 'admin',
    component: AdminPage,
    meta: { title: 'Administração', requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundPage,
    meta: { title: 'Página não encontrada' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  if (to.meta?.requiresAuth && !authState.token) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta?.requiresAdmin) {
    const user = authState.user || await restoreSession()

    if (!user) {
      return {
        name: 'login',
        query: { redirect: to.fullPath },
      }
    }

    if (user.role !== 'admin') {
      return { name: 'home' }
    }
  }

  if (to.meta?.guestOnly && authState.user) {
    return { name: 'my-items' }
  }

  return true
})

export default router
