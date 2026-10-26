<template>
  <v-app-bar color="secondary" elevation="2" height="72">
    <v-container fluid class="d-flex align-center px-4 px-md-8">
      <v-btn
        :to="{ name: 'home' }"
        variant="text"
        color="white"
        class="logo-button px-0"
        :aria-label="`${siteName} — Ir para o início`"
      >
        <v-icon size="30" class="mr-1">mdi-school</v-icon>
        <span class="text-h6 font-weight-bold logo-button__label">{{ siteName }}</span>
      </v-btn>

      <v-spacer />

      <nav class="d-none d-lg-flex align-center ga-1" aria-label="Navegação principal">
        <v-btn
          v-for="item in primaryLinks"
          :key="item.name"
          :to="{ name: item.name }"
          variant="text"
          color="white"
          :class="{ 'nav-active': isActive(item.name) }"
          :aria-current="isActive(item.name) ? 'page' : undefined"
        >
          {{ item.label }}
        </v-btn>

        <v-divider
          vertical
          class="mx-2"
          style="border-color: rgba(255, 255, 255, 0.25)"
        />

        <template v-if="hasSession">
          <v-chip
            color="white"
            variant="outlined"
            prepend-icon="mdi-account-circle-outline"
            class="user-chip"
          >
            {{ displayName }}
          </v-chip>

          <v-btn
            color="white"
            variant="text"
            prepend-icon="mdi-logout"
            :loading="loggingOut"
            :disabled="loggingOut"
            @click="handleLogout"
          >
            Sair
          </v-btn>
        </template>

        <template v-else>
          <v-btn
            :to="{ name: 'login' }"
            variant="text"
            color="white"
            :class="{ 'nav-active': isActive('login') }"
            :aria-current="isActive('login') ? 'page' : undefined"
          >
            Entrar
          </v-btn>

          <v-btn
            :to="{ name: 'register' }"
            color="primary"
            variant="flat"
            rounded="lg"
            class="ml-2"
          >
            Registar
          </v-btn>
        </template>
      </nav>

      <v-app-bar-nav-icon
        class="d-lg-none"
        color="white"
        aria-label="Abrir menu de navegação"
        @click="drawer = !drawer"
      />
    </v-container>
  </v-app-bar>

  <v-navigation-drawer
    v-model="drawer"
    temporary
    location="right"
    width="300"
    color="surface"
  >
    <div class="pa-5">
      <div class="d-flex align-center">
        <v-icon color="primary" size="28" class="mr-2">mdi-school</v-icon>
        <span class="text-h6 font-weight-bold drawer-brand">{{ siteName }}</span>
      </div>
      <p class="text-caption text-medium-emphasis mt-2 mb-0">
        {{ siteTagline }}
      </p>
    </div>

    <v-divider />

    <div v-if="hasSession" class="pa-5 pb-0">
      <v-chip
        color="primary"
        variant="tonal"
        prepend-icon="mdi-account-circle-outline"
        class="mb-2"
      >
        {{ displayName }}
      </v-chip>
      <p v-if="authState.user?.email" class="text-caption text-medium-emphasis mb-0">
        {{ authState.user.email }}
      </p>
      <p v-else class="text-caption text-medium-emphasis mb-0">
        A confirmar a sessão com o servidor.
      </p>
    </div>

    <v-list nav density="comfortable" aria-label="Navegação móvel">
      <v-list-item
        v-for="item in allLinks"
        :key="item.name"
        :to="{ name: item.name }"
        :prepend-icon="item.icon"
        :title="item.label"
        @click="drawer = false"
      />
    </v-list>

    <v-divider class="my-3" />

    <div class="pa-4 d-flex flex-column ga-2">
      <template v-if="hasSession">
        <v-btn
          variant="outlined"
          color="primary"
          block
          prepend-icon="mdi-logout"
          :loading="loggingOut"
          :disabled="loggingOut"
          @click="handleLogout"
        >
          Terminar sessão
        </v-btn>
      </template>

      <template v-else>
        <v-btn
          :to="{ name: 'login' }"
          variant="outlined"
          color="primary"
          block
          @click="drawer = false"
        >
          Entrar
        </v-btn>
        <v-btn
          :to="{ name: 'register' }"
          color="primary"
          block
          @click="drawer = false"
        >
          Criar conta
        </v-btn>
      </template>
    </div>
  </v-navigation-drawer>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authState, hasSession, isAdmin, logout } from '@/stores/auth'
import { loadPublicSettings, publicSettings } from '@/stores/settings'

const route = useRoute()
const router = useRouter()
const drawer = ref(false)
const loggingOut = ref(false)

const siteName = computed(
  () => publicSettings.settings?.site_name || 'CampusEncontra',
)

const siteTagline = computed(
  () => publicSettings.settings?.site_description || 'Encontra o que procuras.',
)

const primaryLinks = computed(() => {
  const links = [
    { name: 'home', label: 'Início', icon: 'mdi-home-outline' },
  ]

  if (hasSession.value) {
    links.push(
      { name: 'publish', label: 'Publicar', icon: 'mdi-plus-circle-outline' },
      { name: 'my-items', label: 'Os meus objectos', icon: 'mdi-bag-personal-outline' },
      { name: 'claims', label: 'Reclamações', icon: 'mdi-handshake-outline' },
    )
  }

  if (isAdmin.value) {
    links.push({
      name: 'admin',
      label: 'Administração',
      icon: 'mdi-shield-account-outline',
    })
  }

  return links
})

const allLinks = primaryLinks

const displayName = computed(
  () => authState.user?.name || 'Sessão por confirmar',
)

function isActive(name) {
  if (name === 'my-items') {
    return ['my-items', 'edit-item'].includes(route.name)
  }

  return route.name === name
}

async function handleLogout() {
  if (loggingOut.value) return

  loggingOut.value = true
  const { revoked } = await logout()
  loggingOut.value = false
  drawer.value = false

  await router.push({
    name: 'login',
    query: revoked ? { loggedOut: '1' } : { logoutError: '1' },
  })
}

watch(
  () => route.fullPath,
  () => {
    drawer.value = false
  },
)

watch(
  [siteName, () => route.meta?.title],
  ([name, pageTitle]) => {
    document.title = pageTitle ? `${pageTitle} | ${name}` : name
  },
  { immediate: true },
)

onMounted(() => {
  void loadPublicSettings()
})
</script>

<style scoped>
.logo-button {
  letter-spacing: 0;
  text-transform: none;
}

.logo-button__label {
  max-width: min(36vw, 260px);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.drawer-brand {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nav-active {
  background-color: rgba(255, 255, 255, 0.12);
}

.user-chip {
  max-width: 220px;
}

.user-chip :deep(.v-chip__content) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.drawer-brand {
  color: #0b1f3a;
}

@media (max-width: 599px) {
  .logo-button :deep(.v-btn__content) {
    font-size: 1rem;
  }
}
</style>
