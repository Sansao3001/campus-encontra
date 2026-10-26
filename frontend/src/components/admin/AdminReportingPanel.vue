<template>
  <section aria-labelledby="admin-reporting-title">
    <div class="admin-panel-header">
      <div>
        <h2 id="admin-reporting-title" class="admin-panel-header__title">
          Relatórios
        </h2>
        <p class="admin-panel-header__description">
          Agrega indicadores no servidor, filtra por período e exporta CSV.
        </p>
      </div>

      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-refresh"
        :loading="loading"
        :disabled="loading"
        @click="load"
      >
        Actualizar
      </v-btn>
    </div>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ error }}
    </v-alert>

    <v-alert
      v-if="exportMessage"
      type="success"
      variant="tonal"
      density="compact"
      closable
      class="mb-4"
      @click:close="exportMessage = ''"
    >
      {{ exportMessage }}
    </v-alert>

    <v-card rounded="xl" elevation="0" class="admin-card mb-4">
      <v-card-title class="admin-card__title">
        Filtros do relatório
      </v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6" lg="3">
            <v-text-field
              v-model="filters.from"
              label="Desde"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
          <v-col cols="12" sm="6" lg="3">
            <v-text-field
              v-model="filters.to"
              label="Até"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
          <v-col cols="6" lg="2">
            <v-select
              v-model="filters.type"
              :items="typeOptions"
              item-title="title"
              item-value="value"
              label="Tipo"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
          <v-col cols="6" lg="2">
            <v-select
              v-model="filters.category"
              :items="categoryOptions"
              item-title="title"
              item-value="value"
              label="Categoria"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
          <v-col cols="12" sm="6" lg="2" class="d-flex ga-2">
            <v-btn
              block
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-filter-outline"
              :disabled="loading"
              @click="load"
            >
              Aplicar
            </v-btn>
            <v-btn
              color="primary"
              variant="outlined"
              rounded="lg"
              :disabled="loading"
              @click="clearFilters"
            >
              Limpar
            </v-btn>
          </v-col>

          <v-col cols="6" sm="4" lg="3">
            <v-select
              v-model="filters.status"
              :items="itemStatusOptions"
              item-title="title"
              item-value="value"
              label="Estado do anúncio"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
          <v-col cols="6" sm="4" lg="3">
            <v-select
              v-model="filters.claim_status"
              :items="claimStatusOptions"
              item-title="title"
              item-value="value"
              label="Estado da solicitação"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
          <v-col cols="12" sm="4" lg="3">
            <v-select
              v-model="filters.report_status"
              :items="reportStatusOptions"
              item-title="title"
              item-value="value"
              label="Estado da denúncia"
              variant="outlined"
              density="compact"
              hide-details
              :disabled="loading"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div v-if="loading" aria-busy="true" aria-label="A carregar relatórios">
      <div class="admin-kpi-grid">
        <v-skeleton-loader
          v-for="index in 6"
          :key="index"
          type="card"
          rounded="xl"
        />
      </div>
      <v-skeleton-loader type="article" rounded="xl" class="mt-4" />
    </div>

    <template v-else-if="data">
      <div class="admin-report-meta mb-4">
        <v-chip variant="tonal" color="primary" prepend-icon="mdi-calendar-range">
          {{ data.period?.from }} a {{ data.period?.to }}
        </v-chip>
        <v-chip variant="outlined" prepend-icon="mdi-clock-outline">
          {{ data.period?.days }} dias · {{ data.period?.timezone }}
        </v-chip>
        <span class="text-caption text-medium-emphasis">
          Gerado em {{ formatDateTime(data.generated_at) }}
        </span>
      </div>

      <div class="admin-kpi-grid">
        <v-card
          v-for="card in summaryCards"
          :key="card.label"
          rounded="xl"
          elevation="0"
          class="admin-card admin-kpi-card h-100"
        >
          <v-card-text>
            <div class="admin-kpi-card__top">
              <span class="admin-kpi-card__icon" :class="`admin-kpi-card__icon--${card.tone}`">
                <v-icon size="21">{{ card.icon }}</v-icon>
              </span>
            </div>
            <div class="admin-kpi-card__value">{{ card.value }}</div>
            <div class="admin-kpi-card__label">{{ card.label }}</div>
            <div class="admin-kpi-card__caption">{{ card.caption }}</div>
          </v-card-text>
        </v-card>
      </div>

      <div class="admin-section-grid mt-4">
        <v-card rounded="xl" elevation="0" class="admin-card">
          <v-card-title class="admin-card__title">
            Anúncios por categoria
          </v-card-title>
          <v-card-text>
            <AdminBarList
              :items="labeled(data.items_by_category, itemCategoryLabel)"
              empty-text="Sem anúncios para os filtros seleccionados."
            />
          </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="0" class="admin-card">
          <v-card-title class="admin-card__title">
            Anúncios por tipo
          </v-card-title>
          <v-card-text>
            <AdminBarList
              :items="labeled(data.items_by_type, itemTypeLabel)"
              color="#0EA5E9"
              empty-text="Sem anúncios para os filtros seleccionados."
            />
          </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="0" class="admin-card">
          <v-card-title class="admin-card__title">
            Anúncios por estado
          </v-card-title>
          <v-card-text>
            <AdminBarList
              :items="labeled(data.items_by_status, itemStatusLabel)"
              color="#2E7D32"
              empty-text="Sem anúncios para os filtros seleccionados."
            />
          </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="0" class="admin-card">
          <v-card-title class="admin-card__title">
            Solicitações por estado
          </v-card-title>
          <v-card-text>
            <AdminBarList
              :items="labeled(data.claims_by_status, claimStatusLabel)"
              color="#EF6C00"
              empty-text="Sem solicitações para os filtros seleccionados."
            />
          </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="0" class="admin-card">
          <v-card-title class="admin-card__title">
            Denúncias por estado
          </v-card-title>
          <v-card-text>
            <AdminBarList
              :items="labeled(data.reports_by_status, reportStatusLabel)"
              color="#C62828"
              empty-text="Sem denúncias para os filtros seleccionados."
            />
          </v-card-text>
        </v-card>
      </div>

      <v-card rounded="xl" elevation="0" class="admin-card mt-4">
        <v-card-title class="admin-card__title">
          Exportar dados
        </v-card-title>
        <v-card-subtitle class="admin-card__subtitle">
          CSV calculado no servidor, sem emails, mensagens privadas ou notas internas. Limite de 5 000 linhas por exportação.
        </v-card-subtitle>
        <v-card-text>
          <div class="d-flex flex-wrap ga-2">
            <v-btn
              v-for="dataset in datasets"
              :key="dataset.value"
              color="primary"
              variant="outlined"
              rounded="lg"
              :prepend-icon="dataset.icon"
              :loading="exporting === dataset.value"
              :disabled="Boolean(exporting) || loading"
              @click="exportDataset(dataset.value)"
            >
              {{ dataset.label }}
            </v-btn>
          </div>
          <p class="text-caption text-medium-emphasis mt-3 mb-0">
            Cada exportação fica registada na auditoria e está sujeita ao limite de 10 pedidos por minuto.
          </p>
        </v-card-text>
      </v-card>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminBarList from '@/components/admin/AdminBarList.vue'
import { downloadAdminExport, fetchAdminReporting } from '@/services/admin'
import { getClaimStatusLabel } from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'
import { getStatusConfig, getTypeConfig } from '@/utils/item'
import { getReportStatusLabel } from '@/utils/report'

const now = new Date()
const start = new Date(now)
start.setDate(start.getDate() - 29)

function toIsoDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const filters = reactive({
  from: toIsoDate(start),
  to: toIsoDate(now),
  type: '',
  status: '',
  claim_status: '',
  report_status: '',
  category: '',
})

const typeOptions = [
  { title: 'Todos os tipos', value: '' },
  { title: 'Perdido', value: 'lost' },
  { title: 'Encontrado', value: 'found' },
]

const itemStatusOptions = [
  { title: 'Todos os estados', value: '' },
  { title: 'Em aberto', value: 'open' },
  { title: 'Em recuperação', value: 'pending' },
  { title: 'Devolvido', value: 'returned' },
]

const claimStatusOptions = [
  { title: 'Todas as solicitações', value: '' },
  { title: 'Pendente', value: 'pending' },
  { title: 'Aceite', value: 'accepted' },
  { title: 'Recusada', value: 'rejected' },
  { title: 'Cancelada', value: 'cancelled' },
  { title: 'Concluída', value: 'completed' },
]

const reportStatusOptions = [
  { title: 'Todas as denúncias', value: '' },
  { title: 'Pendente', value: 'pending' },
  { title: 'Em análise', value: 'reviewing' },
  { title: 'Resolvida', value: 'resolved' },
  { title: 'Arquivada', value: 'dismissed' },
]

const datasets = [
  { value: 'items', label: 'Exportar anúncios', icon: 'mdi-bag-personal-outline' },
  { value: 'claims', label: 'Exportar solicitações', icon: 'mdi-handshake-outline' },
  { value: 'reports', label: 'Exportar denúncias', icon: 'mdi-flag-outline' },
]

const data = ref(null)
const loading = ref(true)
const error = ref('')
const exporting = ref('')
const exportMessage = ref('')
const categories = ref([])

const categoryOptions = computed(() => [
  { title: 'Todas as categorias', value: '' },
  ...categories.value.map((category) => ({ title: category, value: category })),
])

const summaryCards = computed(() => {
  const summary = data.value?.summary ?? {}

  return [
    {
      label: 'Novas contas',
      value: summary.new_users ?? 0,
      caption: 'Registos no período',
      icon: 'mdi-account-plus-outline',
      tone: 'primary',
    },
    {
      label: 'Anúncios criados',
      value: summary.items_created ?? 0,
      caption: `${summary.items_returned ?? 0} com estado devolvido`,
      icon: 'mdi-bag-personal-outline',
      tone: 'info',
    },
    {
      label: 'Solicitações criadas',
      value: summary.claims_created ?? 0,
      caption: `${summary.current_pending_claims ?? 0} pendentes agora`,
      icon: 'mdi-handshake-outline',
      tone: 'warning',
    },
    {
      label: 'Devoluções concluídas',
      value: summary.returns_completed ?? 0,
      caption: 'Concluídas no período',
      icon: 'mdi-check-decagram-outline',
      tone: 'success',
    },
    {
      label: 'Denúncias recebidas',
      value: summary.reports_created ?? 0,
      caption: `${summary.current_open_reports ?? 0} em aberto agora`,
      icon: 'mdi-flag-outline',
      tone: 'error',
    },
  ]
})

function queryParams() {
  return {
    from: filters.from || undefined,
    to: filters.to || undefined,
    type: filters.type || undefined,
    status: filters.status || undefined,
    claim_status: filters.claim_status || undefined,
    report_status: filters.report_status || undefined,
    category: filters.category || undefined,
  }
}

function labeled(items = [], formatter = (value) => value) {
  return (items ?? []).map((item) => ({
    label: formatter(item.label),
    total: item.total,
  }))
}

function itemCategoryLabel(value) {
  return value || 'Sem categoria'
}

function itemTypeLabel(value) {
  return getTypeConfig(value).label
}

function itemStatusLabel(value) {
  return getStatusConfig(value).label
}

function claimStatusLabel(value) {
  return getClaimStatusLabel(value)
}

function reportStatusLabel(value) {
  return getReportStatusLabel(value)
}

async function load() {
  loading.value = true
  error.value = ''
  exportMessage.value = ''

  try {
    data.value = await fetchAdminReporting(queryParams())
    categories.value = data.value?.filter_options?.categories ?? []
  } catch (requestError) {
    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível gerar os relatórios.',
    )
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.from = toIsoDate(start)
  filters.to = toIsoDate(now)
  filters.type = ''
  filters.status = ''
  filters.claim_status = ''
  filters.report_status = ''
  filters.category = ''
  load()
}

async function exportDataset(dataset) {
  if (exporting.value) return

  exporting.value = dataset
  exportMessage.value = ''
  error.value = ''

  try {
    const filename = await downloadAdminExport(dataset, queryParams())
    exportMessage.value = `Exportação concluída: ${filename}`
  } catch (requestError) {
    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível exportar os dados.',
    )
  } finally {
    exporting.value = ''
  }
}

onMounted(load)
</script>
