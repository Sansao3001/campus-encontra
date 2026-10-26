<template>
  <v-container fluid class="admin-shell px-3 px-sm-4 px-md-6 py-5 py-md-8">
    <div class="admin-layout">
      <aside class="admin-sidebar d-none d-lg-flex" aria-label="Navegação administrativa">
        <div class="admin-sidebar__brand">
          <v-avatar color="primary" variant="tonal" rounded="lg" size="46">
            <v-icon size="25">mdi-shield-account-outline</v-icon>
          </v-avatar>
          <div class="min-width-0">
            <p class="admin-sidebar__eyebrow">CampusEncontra</p>
            <p class="admin-sidebar__title">Administração</p>
          </div>
        </div>

        <nav class="admin-nav" aria-label="Secções administrativas">
          <router-link
            v-for="item in sections"
            :key="item.value"
            :to="{ name: 'admin', params: { section: item.value } }"
            class="admin-nav__link"
            :class="{ 'admin-nav__link--active': activeSection === item.value }"
            :aria-current="activeSection === item.value ? 'page' : undefined"
          >
            <v-icon size="20">{{ item.icon }}</v-icon>
            <span>{{ item.label }}</span>
          </router-link>
        </nav>

        <div class="admin-sidebar__footer">
          <div class="admin-profile">
            <v-avatar color="secondary" size="38">{{ initials }}</v-avatar>
            <div class="min-width-0">
              <p class="admin-profile__name">{{ displayName }}</p>
              <p class="admin-profile__role">{{ authState.user?.email }}</p>
            </div>
          </div>

          <v-btn
            variant="text"
            color="error"
            block
            prepend-icon="mdi-logout"
            class="justify-start"
            :loading="loggingOut"
            :disabled="loggingOut"
            @click="handleLogout"
          >
            Terminar sessão
          </v-btn>
        </div>
      </aside>

      <div class="admin-main">
        <header class="admin-topbar">
          <div class="d-flex align-center ga-3 min-width-0">
            <v-btn
              class="d-lg-none"
              icon="mdi-menu"
              variant="text"
              color="primary"
              aria-label="Abrir menu administrativo"
              @click="mobileNav = true"
            />

            <div class="min-width-0">
              <p class="admin-topbar__eyebrow">Painel administrativo</p>
              <h1 class="admin-topbar__title">
                {{ activeMeta.label }}
              </h1>
              <p class="admin-topbar__description d-none d-sm-block">
                {{ activeMeta.description }}
              </p>
            </div>
          </div>

          <div class="d-flex align-center ga-2 flex-shrink-0">
            <v-chip
              class="d-none d-md-inline-flex"
              color="success"
              variant="tonal"
              size="small"
              prepend-icon="mdi-shield-check-outline"
            >
              Sessão de administrador
            </v-chip>

            <v-btn
              icon="mdi-refresh"
              variant="text"
              color="primary"
              aria-label="Actualizar secção actual"
              :loading="refreshing"
              @click="refreshPanel"
            />
          </div>
        </header>

        <main
          id="admin-conteudo"
          ref="adminContent"
          tabindex="-1"
          class="admin-content"
        >
          <component
            :is="activeComponent"
            :key="`${activeSection}-${refreshKey}`"
          />
        </main>
      </div>
    </div>

    <v-navigation-drawer
      v-model="mobileNav"
      temporary
      location="start"
      width="300"
      class="admin-mobile-drawer"
    >
      <div class="admin-mobile-drawer__inner">
        <div class="admin-sidebar__brand px-4 pt-5">
          <v-avatar color="primary" variant="tonal" rounded="lg" size="44">
            <v-icon size="24">mdi-shield-account-outline</v-icon>
          </v-avatar>
          <div class="min-width-0">
            <p class="admin-sidebar__eyebrow">CampusEncontra</p>
            <p class="admin-sidebar__title">Administração</p>
          </div>
        </div>

        <div class="admin-profile mx-4 my-4">
          <v-avatar color="secondary" size="38">{{ initials }}</v-avatar>
          <div class="min-width-0">
            <p class="admin-profile__name">{{ displayName }}</p>
            <p class="admin-profile__role">{{ authState.user?.email }}</p>
          </div>
        </div>

        <v-divider />

        <nav class="admin-nav px-3 py-4" aria-label="Secções administrativas móveis">
          <router-link
            v-for="item in sections"
            :key="item.value"
            :to="{ name: 'admin', params: { section: item.value } }"
            class="admin-nav__link"
            :class="{ 'admin-nav__link--active': activeSection === item.value }"
            :aria-current="activeSection === item.value ? 'page' : undefined"
            @click="mobileNav = false"
          >
            <v-icon size="20">{{ item.icon }}</v-icon>
            <span>{{ item.label }}</span>
          </router-link>
        </nav>

        <v-divider />

        <div class="pa-4">
          <v-btn
            variant="outlined"
            color="error"
            block
            prepend-icon="mdi-logout"
            :loading="loggingOut"
            :disabled="loggingOut"
            @click="handleLogout"
          >
            Terminar sessão
          </v-btn>
        </div>
      </div>
    </v-navigation-drawer>
  </v-container>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminAuditPanel from '@/components/admin/AdminAuditPanel.vue'
import AdminClaimsPanel from '@/components/admin/AdminClaimsPanel.vue'
import AdminDashboardPanel from '@/components/admin/AdminDashboardPanel.vue'
import AdminItemsPanel from '@/components/admin/AdminItemsPanel.vue'
import AdminReportingPanel from '@/components/admin/AdminReportingPanel.vue'
import AdminReportsPanel from '@/components/admin/AdminReportsPanel.vue'
import AdminSettingsPanel from '@/components/admin/AdminSettingsPanel.vue'
import AdminUsersPanel from '@/components/admin/AdminUsersPanel.vue'
import { authState, logout } from '@/stores/auth'

const sections = [
  {
    value: 'dashboard',
    label: 'Dashboard',
    icon: 'mdi-view-dashboard-outline',
    description: 'Indicadores, séries temporais e actividade recente.',
    component: AdminDashboardPanel,
  },
  {
    value: 'utilizadores',
    label: 'Utilizadores',
    icon: 'mdi-account-group-outline',
    description: 'Contas, papéis, suspensões e historial de acesso.',
    component: AdminUsersPanel,
  },
  {
    value: 'anuncios',
    label: 'Anúncios',
    icon: 'mdi-bag-personal-outline',
    description: 'Publicações, moderação e reposição de conteúdo.',
    component: AdminItemsPanel,
  },
  {
    value: 'solicitacoes',
    label: 'Solicitações',
    icon: 'mdi-handshake-outline',
    description: 'Estado das solicitações de recuperação.',
    component: AdminClaimsPanel,
  },
  {
    value: 'denuncias',
    label: 'Denúncias',
    icon: 'mdi-flag-outline',
    description: 'Análise e decisão de denúncias da comunidade.',
    component: AdminReportsPanel,
  },
  {
    value: 'relatorios',
    label: 'Relatórios',
    icon: 'mdi-chart-box-outline',
    description: 'Agregações por período e exportações CSV.',
    component: AdminReportingPanel,
  },
  {
    value: 'auditoria',
    label: 'Auditoria',
    icon: 'mdi-history',
    description: 'Registo das acções administrativas.',
    component: AdminAuditPanel,
  },
  {
    value: 'configuracoes',
    label: 'Configurações',
    icon: 'mdi-cog-outline',
    description: 'Identidade, operação e estado das protecções.',
    component: AdminSettingsPanel,
  },
]

const route = useRoute()
const router = useRouter()
const mobileNav = ref(false)
const refreshKey = ref(0)
const refreshing = ref(false)
const loggingOut = ref(false)
const adminContent = ref(null)

function validSection(value) {
  return sections.some((item) => item.value === value)
}

const activeSection = computed(() => (
  validSection(route.params.section) ? route.params.section : 'dashboard'
))

const activeMeta = computed(() => (
  sections.find((item) => item.value === activeSection.value) ?? sections[0]
))

const activeComponent = computed(() => activeMeta.value.component)

const displayName = computed(() => authState.user?.name || 'Administrador')

const initials = computed(() => {
  const parts = displayName.value
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)

  return parts.map((part) => part[0]?.toUpperCase()).join('') || 'AD'
})

let refreshTimer = null

async function refreshPanel() {
  if (refreshing.value) return

  refreshing.value = true
  refreshKey.value += 1

  window.clearTimeout(refreshTimer)
  refreshTimer = window.setTimeout(() => {
    refreshing.value = false
  }, 500)
}

async function handleLogout() {
  if (loggingOut.value) return

  loggingOut.value = true
  const { revoked } = await logout()
  loggingOut.value = false
  mobileNav.value = false

  await router.push({
    name: 'login',
    query: revoked ? { loggedOut: '1' } : { logoutError: '1' },
  })
}

watch(
  () => route.params.section,
  (value) => {
    if (value && !validSection(value)) {
      router.replace({
        name: 'admin',
        params: { section: 'dashboard' },
      })
      return
    }

    nextTick(() => {
      adminContent.value?.focus({ preventScroll: true })
    })
  },
  { immediate: true },
)

watch(
  () => route.fullPath,
  () => {
    mobileNav.value = false
  },
)

onBeforeUnmount(() => {
  window.clearTimeout(refreshTimer)
})
</script>
