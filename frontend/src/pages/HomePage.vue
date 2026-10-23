<template>
  <v-container class="py-8 py-md-12">
    <section class="hero mb-8 mb-md-10" aria-labelledby="hero-title">
      <v-row align="center">
        <v-col cols="12" md="8" lg="7">
          <v-chip
            color="primary"
            variant="tonal"
            prepend-icon="mdi-school"
            class="mb-4"
          >
            Comunidade universitária
          </v-chip>

          <h1 id="hero-title" class="text-h4 text-md-h2 font-weight-bold hero__title">
            Encontra o que procuras.
          </h1>

          <p class="text-body-1 text-md-h6 hero__subtitle mt-4">
            Consulta, pesquisa e publica objectos perdidos e encontrados no campus.
          </p>

          <div class="d-flex flex-wrap ga-3 mt-6">
            <v-btn
              color="primary"
              size="large"
              rounded="lg"
              prepend-icon="mdi-plus"
              :to="{ name: 'publish' }"
            >
              Publicar objecto
            </v-btn>

            <v-btn
              color="primary"
              variant="outlined"
              size="large"
              rounded="lg"
              prepend-icon="mdi-login"
              :to="{ name: 'login' }"
            >
              Entrar
            </v-btn>
          </div>
        </v-col>

        <v-col cols="4" class="d-none d-md-flex justify-end">
          <div class="hero__art" aria-hidden="true">
            <v-icon size="150" color="primary">mdi-bag-personal-outline</v-icon>
          </div>
        </v-col>
      </v-row>
    </section>

    <v-card class="filters-card mb-6" rounded="xl" elevation="0">
      <v-card-text class="pa-4 pa-md-6">
        <v-row density="compact" align="center">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              label="Pesquisar objectos"
              placeholder="Ex.: mochila, telefone, carteira"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              bg-color="white"
              clearable
              hide-details
              aria-label="Pesquisar objectos perdidos e encontrados"
            />
          </v-col>

          <v-col cols="12" md="6">
            <div
              class="d-flex flex-wrap ga-2 justify-md-end mt-4 mt-md-0"
              role="group"
              aria-label="Filtrar por tipo de objecto"
            >
              <v-btn
                v-for="filter in filters"
                :key="filter.value"
                :variant="activeFilter === filter.value ? 'flat' : 'outlined'"
                color="primary"
                rounded="lg"
                :prepend-icon="filter.icon"
                :aria-pressed="activeFilter === filter.value"
                @click="activeFilter = filter.value"
              >
                {{ filter.label }}
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
      <div>
        <h2 class="text-h6 text-md-h5 font-weight-bold hero__title">
          Objectos registados
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0" aria-live="polite">
          {{ resultsLabel }}
        </p>
      </div>

      <v-btn
        variant="text"
        color="primary"
        prepend-icon="mdi-refresh"
        :loading="loading"
        @click="loadItems"
      >
        Actualizar
      </v-btn>
    </div>

    <div v-if="loading" aria-busy="true" aria-label="A carregar objectos">
      <v-row>
        <v-col v-for="n in 6" :key="n" cols="12" sm="6" lg="4">
          <v-skeleton-loader type="card" rounded="xl" />
        </v-col>
      </v-row>
    </div>

    <v-alert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
      prominent
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>{{ error }}</span>
        <v-btn color="error" variant="flat" rounded="lg" @click="loadItems">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <template v-else>
      <v-row v-if="filteredItems.length > 0">
        <v-col
          v-for="item in filteredItems"
          :key="item.id"
          cols="12"
          sm="6"
          lg="4"
        >
          <ItemCard :item="item" />
        </v-col>
      </v-row>

      <EmptyState
        v-else-if="items.length === 0"
        icon="mdi-bag-personal-outline"
        title="Ainda não há objectos publicados"
        description="Sê a primeira pessoa a publicar um objecto perdido ou encontrado no campus."
      >
        <template #actions>
          <v-btn color="primary" rounded="lg" :to="{ name: 'publish' }">
            Publicar objecto
          </v-btn>
        </template>
      </EmptyState>

      <EmptyState
        v-else
        icon="mdi-magnify"
        title="Sem resultados"
        description="Não encontrámos objectos com essa pesquisa ou filtro. Tenta outros termos."
      >
        <template #actions>
          <v-btn
            color="primary"
            variant="outlined"
            rounded="lg"
            @click="clearFilters"
          >
            Limpar filtros
          </v-btn>
        </template>
      </EmptyState>
    </template>
  </v-container>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import ItemCard from '@/components/ItemCard.vue'
import { getApiErrorMessage } from '@/utils/errors'
import { fetchItems } from '@/services/items'

const filters = [
  { value: 'all', label: 'Todos', icon: 'mdi-view-grid-outline' },
  { value: 'lost', label: 'Perdidos', icon: 'mdi-magnify' },
  { value: 'found', label: 'Encontrados', icon: 'mdi-check-circle-outline' },
]

const items = ref([])
const search = ref('')
const activeFilter = ref('all')
const loading = ref(true)
const error = ref('')

const filteredItems = computed(() => {
  const term = search.value.trim().toLowerCase()

  return items.value.filter((item) => {
    const matchesType = activeFilter.value === 'all' || item.type === activeFilter.value

    if (!matchesType) return false
    if (!term) return true

    const searchable = [
      item.title,
      item.description,
      item.category,
      item.location,
      item.user?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return searchable.includes(term)
  })
})

const resultsLabel = computed(() => {
  if (loading.value) return 'A carregar objectos...'

  const visible = filteredItems.value.length
  const total = items.value.length

  if (total === 0) return 'Nenhum objecto publicado'
  if (visible === total) {
    return `${visible} ${visible === 1 ? 'objecto' : 'objectos'}`
  }

  return `${visible} de ${total} objectos`
})

async function loadItems() {
  loading.value = true
  error.value = ''

  try {
    items.value = await fetchItems()
  } catch (requestError) {
    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os objectos. Tenta novamente.',
    )
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  search.value = ''
  activeFilter.value = 'all'
}

onMounted(loadItems)
</script>

<style scoped>
.hero {
  position: relative;
  overflow: hidden;
  padding: clamp(1.5rem, 4vw, 3.5rem);
  border: 1px solid rgba(22, 78, 128, 0.08);
  border-radius: 28px;
  background: linear-gradient(135deg, #ffffff 0%, #dce8f5 100%);
}

.hero__title {
  color: #0b1f3a;
  letter-spacing: -0.02em;
}

.hero__subtitle {
  max-width: 620px;
  color: #526477;
}

.hero__art {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 190px;
  height: 190px;
  border: 1px solid rgba(22, 78, 128, 0.1);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.65);
}

.filters-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}
</style>
