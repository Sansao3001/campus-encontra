<template>
  <v-card class="my-item-card d-flex flex-column" rounded="xl" elevation="0" height="100%">
    <v-card-item class="pb-2">
      <div class="d-flex align-start justify-space-between ga-3">
        <v-avatar size="48" color="campus-light">
          <v-icon color="primary" size="26">{{ typeConfig.icon }}</v-icon>
        </v-avatar>

        <div class="d-flex flex-wrap justify-end ga-2">
          <StatusChip kind="type" :value="item.type" />
          <StatusChip kind="status" :value="item.status" />
        </div>
      </div>

      <v-card-title class="px-0 pt-4 my-item-card__title">
        {{ item.title }}
      </v-card-title>

      <v-card-subtitle class="px-0">
        {{ item.category }}
      </v-card-subtitle>
    </v-card-item>

    <v-card-text class="pt-2 flex-grow-1">
      <p class="text-body-2 text-medium-emphasis text-clamp-3 mb-4">
        {{ item.description || 'Sem descrição disponível.' }}
      </p>

      <div class="d-flex flex-column ga-2 text-body-2 text-medium-emphasis">
        <span class="d-flex align-center ga-2">
          <v-icon size="16">mdi-map-marker-outline</v-icon>
          {{ item.location || 'Local não indicado' }}
        </span>

        <span class="d-flex align-center ga-2">
          <v-icon size="16">mdi-calendar-blank-outline</v-icon>
          {{ formatDate(item.occurrence_date) }}
        </span>
      </div>
    </v-card-text>

    <v-divider />

    <v-card-actions class="pa-4 flex-wrap ga-2">
      <v-btn
        :to="{ name: 'item-detail', params: { id: item.id } }"
        variant="text"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-eye-outline"
      >
        Ver
      </v-btn>

      <v-spacer />

      <v-btn
        :to="{ name: 'edit-item', params: { id: item.id } }"
        color="primary"
        variant="outlined"
        rounded="lg"
        prepend-icon="mdi-pencil-outline"
      >
        Editar
      </v-btn>

      <v-btn
        color="error"
        variant="text"
        rounded="lg"
        prepend-icon="mdi-delete-outline"
        @click="$emit('delete', item)"
      >
        Eliminar
      </v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import StatusChip from './StatusChip.vue'
import { formatDate } from '@/utils/format'
import { getTypeConfig } from '@/utils/item'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
})

defineEmits(['delete'])

const typeConfig = computed(() => getTypeConfig(props.item.type))
</script>

<style scoped>
.my-item-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}

.my-item-card__title {
  white-space: normal;
  line-height: 1.35;
}
</style>
