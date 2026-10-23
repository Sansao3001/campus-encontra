<template>
  <v-app-bar color="secondary" elevation="2" height="72">
    <v-container fluid class="d-flex align-center px-4 px-md-8">
      <v-btn
        :to="{ name: 'home' }"
        variant="text"
        color="white"
        class="logo-button px-0"
        aria-label="CampusEncontra — Ir para o início"
      >
        <v-icon size="30" class="mr-1">mdi-school</v-icon>
        <span class="text-h6 font-weight-bold">CampusEncontra</span>
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
        <span class="text-h6 font-weight-bold drawer-brand">CampusEncontra</span>
      </div>
      <p class="text-caption text-medium-emphasis mt-2 mb-0">
        Encontra o que procuras.
      </p>
    </div>

    <v-divider />

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
    </div>
  </v-navigation-drawer>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const drawer = ref(false)

const primaryLinks = [
  { name: 'home', label: 'Início', icon: 'mdi-home-outline' },
  { name: 'publish', label: 'Publicar', icon: 'mdi-plus-circle-outline' },
  { name: 'my-items', label: 'Os meus objectos', icon: 'mdi-bag-personal-outline' },
]

const authLinks = [
  { name: 'login', label: 'Entrar', icon: 'mdi-login' },
  { name: 'register', label: 'Registar', icon: 'mdi-account-plus-outline' },
]

const allLinks = computed(() => [...primaryLinks, ...authLinks])

function isActive(name) {
  if (name === 'my-items') {
    return ['my-items', 'edit-item'].includes(route.name)
  }

  return route.name === name
}

watch(
  () => route.fullPath,
  () => {
    drawer.value = false
  },
)
</script>

<style scoped>
.logo-button {
  letter-spacing: 0;
  text-transform: none;
}

.nav-active {
  background-color: rgba(255, 255, 255, 0.12);
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
