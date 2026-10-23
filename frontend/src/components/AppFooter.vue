<template>
  <v-footer app class="app-footer py-4" color="transparent">
    <v-container
      class="d-flex flex-column flex-sm-row align-center justify-space-between ga-2 text-center text-sm-start"
    >
      <p class="text-caption mb-0">
        CampusEncontra — A comunidade ajuda a encontrar.
      </p>
      <p class="text-caption mb-0">
        {{ year }} · Objectos perdidos e encontrados no campus
      </p>
    </v-container>
  </v-footer>
</template>

<script setup>
const year = new Date().getFullYear()
</script>

<style scoped>
.app-footer {
  color: #5c6b7a;
  border-top: 1px solid rgba(11, 31, 58, 0.08);
}
</style>
