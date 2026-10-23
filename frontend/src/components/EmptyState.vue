<template>
  <v-card rounded="xl" elevation="0" class="empty-state">
    <v-card-text class="text-center py-12 px-6">
      <v-avatar size="80" color="campus-light" class="mb-5">
        <v-icon size="42" color="primary">{{ icon }}</v-icon>
      </v-avatar>

      <h3 class="text-h6 font-weight-bold mb-2">{{ title }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-6">{{ description }}</p>

      <div class="d-flex flex-wrap justify-center ga-3">
        <slot name="actions" />
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
defineProps({
  icon: {
    type: String,
    default: 'mdi-magnify',
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
})
</script>

<style scoped>
.empty-state {
  border: 1px solid rgba(11, 31, 58, 0.08);
}
</style>
