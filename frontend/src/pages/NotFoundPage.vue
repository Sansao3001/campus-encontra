<template>
  <v-container max-width="720" class="py-12 text-center">
    <v-avatar size="96" color="campus-light" class="mb-6">
      <v-icon size="52" color="primary">mdi-compass-outline</v-icon>
    </v-avatar>

    <p class="text-overline text-primary mb-2">Erro 404</p>

    <h1 class="text-h4 text-md-h3 font-weight-bold not-found-title mb-4">
      Página não encontrada
    </h1>

    <p class="text-body-1 text-medium-emphasis mb-8">
      O endereço que tentaste abrir não existe ou foi movido.
    </p>

    <div class="d-flex flex-wrap justify-center ga-3">
      <v-btn
        color="primary"
        rounded="lg"
        :to="{ name: 'home' }"
        prepend-icon="mdi-home-outline"
      >
        Voltar ao início
      </v-btn>
      <v-btn
        color="primary"
        variant="outlined"
        rounded="lg"
        :to="{ name: 'home' }"
        prepend-icon="mdi-view-grid-outline"
      >
        Ver objectos
      </v-btn>
    </div>
  </v-container>
</template>

<script setup>
</script>

<style scoped>
.not-found-title {
  color: #0b1f3a;
  letter-spacing: -0.02em;
}
</style>
