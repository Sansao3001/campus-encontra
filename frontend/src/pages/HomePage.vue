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

    <ItemFilters
      v-model:search="search"
      v-model:type="activeFilter"
      v-model:status="activeStatus"
      :has-active-filters="hasActiveFilters"
      @clear="clearFilters"
    />

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
        :disabled="loading"
        @click="reloadItems"
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
        <v-btn color="error" variant="flat" rounded="lg" @click="reloadItems">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <template v-else>
      <v-row v-if="items.length > 0">
        <v-col
          v-for="item in items"
          :key="item.id"
          cols="12"
          sm="6"
          lg="4"
        >
          <ItemCard :item="item" />
        </v-col>
      </v-row>

      <EmptyState
        v-else-if="!hasActiveFilters"
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

      <v-pagination
        v-if="pagination.last_page > 1"
        :model-value="pagination.current_page"
        :length="pagination.last_page"
        :total-visible="5"
        rounded="circle"
        density="comfortable"
        class="mt-8"
        @update:model-value="changePage"
      />
    </template>
  </v-container>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import ItemCard from '@/components/ItemCard.vue'
import ItemFilters from '@/components/ItemFilters.vue'
import { getApiErrorMessage } from '@/utils/errors'
import { fetchItems } from '@/services/items'

const PER_PAGE = 12
const FILTER_DEBOUNCE_MS = 300

const items = ref([])
const search = ref('')
const activeFilter = ref('all')
const activeStatus = ref('all')
const page = ref(1)
const pagination = ref(emptyPagination())
const loading = ref(true)
const error = ref('')

let filterTimer = null
let requestId = 0

const hasActiveFilters = computed(() => (
  (search.value ?? '').trim() !== ''
  || activeFilter.value !== 'all'
  || activeStatus.value !== 'all'
))

const resultsLabel = computed(() => {
  if (loading.value) return 'A carregar objectos...'
  if (error.value) return 'Não foi possível carregar os objectos'

  const total = pagination.value.total

  if (total === 0) {
    return hasActiveFilters.value
      ? 'Sem resultados para estes filtros'
      : 'Nenhum objecto publicado'
  }

  const from = pagination.value.from ?? 1
  const to = pagination.value.to ?? total

  if (from === 1 && to === total) {
    return `${total} ${total === 1 ? 'objecto' : 'objectos'}`
  }

  return `${from}–${to} de ${total} objectos`
})

function emptyPagination() {
  return {
    current_page: 1,
    last_page: 1,
    per_page: PER_PAGE,
    total: 0,
    from: null,
    to: null,
  }
}

function buildParams() {
  const params = {
    page: page.value,
    per_page: PER_PAGE,
  }

  const term = (search.value ?? '').trim()

  if (term) params.q = term
  if (activeFilter.value !== 'all') params.type = activeFilter.value
  if (activeStatus.value !== 'all') params.status = activeStatus.value

  return params
}

async function loadItems() {
  const currentRequest = ++requestId

  loading.value = true
  error.value = ''

  try {
    const result = await fetchItems(buildParams())

    if (currentRequest !== requestId) return

    items.value = result.items
    pagination.value = result.meta ?? emptyPagination()

    if (result.meta?.current_page) {
      page.value = result.meta.current_page
    }
  } catch (requestError) {
    if (currentRequest !== requestId) return

    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os objectos. Tenta novamente.',
    )
    items.value = []
    pagination.value = emptyPagination()
  } finally {
    if (currentRequest === requestId) {
      loading.value = false
    }
  }
}

function scheduleFilterReload() {
  requestId += 1
  page.value = 1

  window.clearTimeout(filterTimer)
  filterTimer = window.setTimeout(loadItems, FILTER_DEBOUNCE_MS)
}

watch(
  [search, activeFilter, activeStatus],
  scheduleFilterReload,
  { flush: 'sync' },
)

function changePage(nextPage) {
  if (nextPage === page.value) return

  page.value = nextPage
  loadItems()
}

function clearFilters() {
  search.value = ''
  activeFilter.value = 'all'
  activeStatus.value = 'all'
  page.value = 1
}

function reloadItems() {
  window.clearTimeout(filterTimer)
  loadItems()
}

onMounted(loadItems)

onBeforeUnmount(() => {
  window.clearTimeout(filterTimer)
  requestId += 1
})
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
</style>
