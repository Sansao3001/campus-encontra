import { createRouter, createWebHistory } from 'vue-router'

import EditItemPage from '@/pages/EditItemPage.vue'
import HomePage from '@/pages/HomePage.vue'
import ItemDetailPage from '@/pages/ItemDetailPage.vue'
import LoginPage from '@/pages/LoginPage.vue'
import MyItemsPage from '@/pages/MyItemsPage.vue'
import NotFoundPage from '@/pages/NotFoundPage.vue'
import PublishItemPage from '@/pages/PublishItemPage.vue'
import RegisterPage from '@/pages/RegisterPage.vue'

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
    meta: { title: 'Entrar' },
  },
  {
    path: '/registar',
    name: 'register',
    component: RegisterPage,
    meta: { title: 'Criar conta' },
  },
  {
    path: '/publicar',
    name: 'publish',
    component: PublishItemPage,
    meta: { title: 'Publicar objecto' },
  },
  {
    path: '/os-meus-objectos',
    name: 'my-items',
    component: MyItemsPage,
    meta: { title: 'Os meus objectos' },
  },
  {
    path: '/os-meus-objectos/:id/editar',
    name: 'edit-item',
    component: EditItemPage,
    props: true,
    meta: { title: 'Editar objecto' },
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

router.afterEach((to) => {
  const pageTitle = to.meta?.title
  document.title = pageTitle
    ? `${pageTitle} | CampusEncontra`
    : 'CampusEncontra'
})

export default router
