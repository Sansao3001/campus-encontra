<template>
  <v-container max-width="900" class="py-10 py-md-16">
    <v-card rounded="xl" elevation="0" class="coming-soon">
      <v-card-text class="pa-6 pa-md-10 text-center">
        <v-avatar size="88" color="campus-light" class="mb-5">
          <v-icon size="46" color="primary">{{ icon }}</v-icon>
        </v-avatar>

        <div class="d-flex flex-wrap justify-center align-center ga-2 mb-4">
          <v-chip color="primary" variant="tonal" size="small">
            {{ phase }}
          </v-chip>

          <v-chip
            v-if="user"
            color="success"
            variant="tonal"
            size="small"
            prepend-icon="mdi-account-check-outline"
          >
            Sessão iniciada: {{ user.name }}
          </v-chip>
        </div>

        <h1 class="text-h4 text-md-h3 font-weight-bold mb-4 coming-soon__title">
          {{ title }}
        </h1>

        <p class="text-body-1 text-medium-emphasis mx-auto coming-soon__description">
          {{ description }}
        </p>

        <v-alert
          v-if="sessionError"
          type="warning"
          variant="tonal"
          class="text-left mt-6"
          icon="mdi-wifi-alert"
        >
          <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
            <span>{{ sessionError }}</span>
            <v-btn
              color="warning"
              variant="flat"
              size="small"
              rounded="lg"
              @click="$emit('retry-session')"
            >
              Tentar novamente
            </v-btn>
          </div>
        </v-alert>

        <v-alert
          type="info"
          variant="tonal"
          class="text-left mt-6 mb-6"
          icon="mdi-information-outline"
        >
          {{ notice }}
        </v-alert>

        <v-list
          v-if="items.length"
          class="text-left mx-auto coming-soon__list"
          bg-color="transparent"
        >
          <v-list-item
            v-for="item in items"
            :key="item"
            prepend-icon="mdi-check-circle-outline"
          >
            <v-list-item-title class="text-body-2">{{ item }}</v-list-item-title>
          </v-list-item>
        </v-list>

        <div class="d-flex flex-wrap justify-center ga-3 mt-8">
          <slot name="actions" />
        </div>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    default: 'mdi-progress-wrench',
  },
  phase: {
    type: String,
    default: 'Em preparação',
  },
  items: {
    type: Array,
    default: () => [],
  },
  user: {
    type: Object,
    default: null,
  },
  sessionError: {
    type: String,
    default: '',
  },
  notice: {
    type: String,
    default: 'Esta página ainda não efectua nenhuma operação real.',
  },
})

defineEmits(['retry-session'])
</script>

<style scoped>
.coming-soon {
  border: 1px solid rgba(11, 31, 58, 0.08);
}

.coming-soon__title {
  color: #0b1f3a;
  letter-spacing: -0.02em;
}

.coming-soon__description {
  max-width: 620px;
}

.coming-soon__list {
  max-width: 560px;
}
</style>
