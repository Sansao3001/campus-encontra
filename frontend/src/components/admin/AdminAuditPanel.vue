<template>
  <section aria-labelledby="admin-audit-title">
    <div class="admin-panel-header">
      <div>
        <h2 id="admin-audit-title" class="admin-panel-header__title">
          Auditoria
        </h2>
        <p class="admin-panel-header__description">
          Historial de acções administrativas, sem exposição de credenciais ou conteúdo privado.
        </p>
      </div>

      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-refresh"
        :loading="loading"
        :disabled="loading"
        @click="load(page)"
      >
        Actualizar
      </v-btn>
    </div>

    <v-alert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ errorMessage }}
    </v-alert>

    <v-card rounded="xl" elevation="0" class="admin-card mb-4">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="8" md="5">
            <v-select
              v-model="filters.action"
              :items="actionOptions"
              item-title="title"
              item-value="value"
              label="Tipo de acção"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="applyFilters"
            />
          </v-col>

          <v-col cols="12" sm="4" md="3">
            <v-btn
              block
              color="primary"
              variant="outlined"
              rounded="lg"
              prepend-icon="mdi-filter-off-outline"
              :disabled="!filters.action || loading"
              @click="clearFilter"
            >
              Limpar filtro
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div v-if="loading" aria-busy="true" aria-label="A carregar auditoria">
      <v-skeleton-loader type="table" rounded="xl" />
    </div>

    <EmptyState
      v-else-if="!logs.length"
      icon="mdi-history"
      title="Sem registos de auditoria"
      description="Não existem acções administrativas que correspondam ao filtro seleccionado."
    >
      <template #actions>
        <v-btn
          v-if="filters.action"
          color="primary"
          variant="outlined"
          rounded="lg"
          @click="clearFilter"
        >
          Limpar filtro
        </v-btn>
      </template>
    </EmptyState>

    <template v-else>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Data</th>
              <th scope="col">Administrador</th>
              <th scope="col">Acção</th>
              <th scope="col">Alvo</th>
              <th scope="col">Motivo</th>
              <th scope="col">Contexto</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in logs" :key="entry.id">
              <td class="text-no-wrap">{{ formatDateTime(entry.created_at) }}</td>
              <td>
                <div class="admin-table__primary">{{ entry.admin?.name || 'Sistema / conta removida' }}</div>
              </td>
              <td>
                <v-chip size="small" variant="tonal" color="primary">
                  {{ getAdminActionLabel(entry.action) }}
                </v-chip>
              </td>
              <td>
                <template v-if="entry.target_type">
                  <div class="admin-table__primary">{{ entry.target_type }}</div>
                  <div class="admin-table__secondary">
                    {{ entry.target_id ? `#${entry.target_id}` : 'Sem identificador' }}
                  </div>
                </template>
                <span v-else class="text-medium-emphasis">—</span>
              </td>
              <td class="admin-audit-reason">
                {{ entry.reason || '—' }}
              </td>
              <td class="admin-audit-context">
                {{ formatMetadata(entry.metadata) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <v-pagination
        v-if="meta && meta.last_page > 1"
        :model-value="page"
        :length="meta.last_page"
        :total-visible="5"
        rounded="circle"
        class="mt-5"
        @update:model-value="load"
      />
    </template>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import { fetchAdminAuditLogs } from '@/services/admin'
import { adminActionLabels, getAdminActionLabel } from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDateTime, truncate } from '@/utils/format'

const actionOptions = [
  { title: 'Todas as acções', value: '' },
  ...Object.entries(adminActionLabels).map(([value, title]) => ({ title, value })),
]

const filters = reactive({ action: '' })
const logs = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const errorMessage = ref('')

async function load(targetPage = page.value) {
  loading.value = true
  errorMessage.value = ''

  try {
    const result = await fetchAdminAuditLogs({
      action: filters.action || undefined,
      page: targetPage,
      per_page: 20,
    })

    logs.value = result.items
    meta.value = result.meta
    page.value = result.meta?.current_page ?? targetPage
  } catch (requestError) {
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar o registo de auditoria.',
    )
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  load(1)
}

function clearFilter() {
  filters.action = ''
  page.value = 1
  load(1)
}

function formatMetadata(metadata) {
  if (!metadata || typeof metadata !== 'object') return '—'

  const parts = Object.entries(metadata).map(([key, value]) => {
    if (Array.isArray(value)) {
      return `${key}: ${value.join(', ')}`
    }

    if (value && typeof value === 'object') {
      return `${key}: ${JSON.stringify(value)}`
    }

    return `${key}: ${value}`
  })

  return parts.length ? truncate(parts.join(' · '), 180) : '—'
}

onMounted(load)
</script>
