<template>
  <v-card class="filters-card mb-6" rounded="xl" elevation="0">
    <v-card-text class="pa-4 pa-md-6">
      <v-row density="compact" align="center">
        <v-col cols="12" md="5">
          <v-text-field
            :model-value="search"
            label="Pesquisar objectos"
            placeholder="Ex.: mochila, telefone, carteira"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="comfortable"
            bg-color="white"
            clearable
            hide-details
            aria-label="Pesquisar objectos perdidos e encontrados"
            @update:model-value="updateSearch"
          />
        </v-col>

        <v-col cols="12" md="4" class="mt-4 mt-md-0">
          <div
            class="d-flex flex-wrap ga-2 justify-md-end"
            role="group"
            aria-label="Filtrar por tipo de objecto"
          >
            <v-btn
              v-for="filter in typeFilters"
              :key="filter.value"
              :variant="type === filter.value ? 'flat' : 'outlined'"
              color="primary"
              rounded="lg"
              :prepend-icon="filter.icon"
              :aria-pressed="type === filter.value"
              @click="emit('update:type', filter.value)"
            >
              {{ filter.label }}
            </v-btn>
          </div>
        </v-col>

        <v-col cols="12" md="3" class="mt-4 mt-md-0">
          <v-select
            :model-value="status"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            label="Estado"
            prepend-inner-icon="mdi-filter-outline"
            variant="outlined"
            density="comfortable"
            bg-color="white"
            hide-details
            aria-label="Filtrar por estado do objecto"
            @update:model-value="updateStatus"
          />
        </v-col>
      </v-row>

      <div
        v-if="hasActiveFilters"
        class="d-flex justify-end mt-3"
      >
        <v-btn
          variant="text"
          color="primary"
          rounded="lg"
          prepend-icon="mdi-filter-remove-outline"
          @click="emit('clear')"
        >
          Limpar filtros
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
defineProps({
  search: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'all',
  },
  status: {
    type: String,
    default: 'all',
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:search',
  'update:type',
  'update:status',
  'clear',
])

const typeFilters = [
  { value: 'all', label: 'Todos', icon: 'mdi-view-grid-outline' },
  { value: 'lost', label: 'Perdidos', icon: 'mdi-magnify' },
  { value: 'found', label: 'Encontrados', icon: 'mdi-check-circle-outline' },
]

const statusOptions = [
  { value: 'all', title: 'Todos os estados' },
  { value: 'open', title: 'Em aberto' },
  { value: 'pending', title: 'Em recuperação' },
  { value: 'returned', title: 'Devolvido' },
]

function updateSearch(value) {
  emit('update:search', value ?? '')
}

function updateStatus(value) {
  emit('update:status', value ?? 'all')
}
</script>

<style scoped>
.filters-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}
</style>
