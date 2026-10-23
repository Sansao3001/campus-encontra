<template>
  <v-app>
    <a class="skip-link" href="#conteudo-principal">
      Saltar para o conteúdo
    </a>

    <AppHeader />

    <v-main id="conteudo-principal" class="page-background">
      <router-view />
    </v-main>

    <AppFooter />
  </v-app>
</template>

<script setup>
import AppFooter from './components/AppFooter.vue'
import AppHeader from './components/AppHeader.vue'
</script>
