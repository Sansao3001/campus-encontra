<template>
  <v-card class="item-card d-flex flex-column" rounded="xl" elevation="0" height="100%">
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

      <v-card-title class="px-0 pt-4 item-card__title">
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
        <span class="d-flex align-center">
          <v-icon size="18" color="primary" class="mr-2">mdi-map-marker-outline</v-icon>
          {{ item.location || 'Local não indicado' }}
        </span>

        <span class="d-flex align-center">
          <v-icon size="18" color="primary" class="mr-2">mdi-calendar-blank-outline</v-icon>
          {{ formatDate(item.occurrence_date) }}
        </span>

        <span v-if="item.user?.name" class="d-flex align-center">
          <v-icon size="18" color="primary" class="mr-2">mdi-account-outline</v-icon>
          {{ item.user.name }}
        </span>
      </div>
    </v-card-text>

    <v-divider />

    <v-card-actions class="pa-4">
      <v-btn
        :to="{ name: 'item-detail', params: { id: item.id } }"
        color="primary"
        variant="flat"
        rounded="lg"
        block
        append-icon="mdi-arrow-right"
      >
        Ver detalhes
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

const typeConfig = computed(() => getTypeConfig(props.item.type))
</script>

<style scoped>
.item-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
  border-top: 3px solid #164e80;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.item-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(11, 31, 58, 0.1) !important;
}

.item-card__title {
  color: #0b1f3a;
  line-height: 1.3;
  white-space: normal;
}
</style>
