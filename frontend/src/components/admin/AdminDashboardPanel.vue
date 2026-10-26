<template>
  <section aria-labelledby="admin-dashboard-title">
    <div class="admin-panel-header">
      <div>
        <h2 id="admin-dashboard-title" class="admin-panel-header__title">
          Visão geral
        </h2>
        <p class="admin-panel-header__description">
          Indicadores reais da plataforma, com séries temporais e filtros de período.
        </p>
      </div>

      <div class="d-flex flex-wrap align-center ga-2">
        <v-select
          v-model="days"
          :items="periodOptions"
          item-title="title"
          item-value="value"
          label="Período"
          variant="outlined"
          density="compact"
          hide-details
          class="admin-period-select"
          :disabled="loading"
        />

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
    </div>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      prominent
      class="mb-6"
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>{{ error }}</span>
        <v-btn color="error" variant="flat" rounded="lg" @click="load">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <div v-if="loading" aria-busy="true" aria-label="A carregar dashboard">
      <div class="admin-kpi-grid">
        <v-skeleton-loader
          v-for="index in 4"
          :key="index"
          type="card"
          rounded="xl"
        />
      </div>
      <v-skeleton-loader type="image" rounded="xl" class="mt-4" />
    </div>

    <template v-else-if="data && !error">
      <div class="admin-kpi-grid">
        <v-card
          v-for="stat in mainStats"
          :key="stat.label"
          rounded="xl"
          elevation="0"
          class="admin-card admin-kpi-card h-100"
        >
          <v-card-text>
            <div class="admin-kpi-card__top">
              <span class="admin-kpi-card__icon" :class="`admin-kpi-card__icon--${stat.tone}`">
                <v-icon size="22">{{ stat.icon }}</v-icon>
              </span>
              <v-chip size="small" variant="tonal" :color="stat.color">
                {{ stat.badge }}
              </v-chip>
            </div>

            <div class="admin-kpi-card__value">{{ stat.value }}</div>
            <div class="admin-kpi-card__label">{{ stat.label }}</div>
            <div class="admin-kpi-card__caption">{{ stat.caption }}</div>
          </v-card-text>
        </v-card>
      </div>

      <div class="admin-period-strip mt-4">
        <div
          v-for="metric in periodMetrics"
          :key="metric.label"
          class="admin-period-strip__item"
        >
          <span class="admin-period-strip__value">{{ metric.value }}</span>
          <span class="admin-period-strip__label">{{ metric.label }}</span>
        </div>
      </div>

      <v-row class="mt-1">
        <v-col cols="12" lg="8">
          <v-card rounded="xl" elevation="0" class="admin-card h-100">
            <v-card-title class="admin-card__title">
              Evolução no período
            </v-card-title>
            <v-card-subtitle class="admin-card__subtitle">
              {{ periodLabel }} · dados agregados no servidor
            </v-card-subtitle>
            <v-card-text>
              <AdminTrendChart
                :labels="data.series?.labels ?? []"
                :series="chartSeries"
                :aria-label="`Evolução de anúncios, solicitações, devoluções e denúncias nos ${periodLabel.toLowerCase()}`"
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="4">
          <v-card rounded="xl" elevation="0" class="admin-card h-100">
            <v-card-title class="admin-card__title">
              Categorias mais publicadas
            </v-card-title>
            <v-card-subtitle class="admin-card__subtitle">
              {{ periodLabel }}
            </v-card-subtitle>
            <v-card-text>
              <AdminBarList
                :items="data.categories ?? []"
                empty-text="Ainda não existem anúncios neste período."
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <div class="admin-section-grid mt-4">
        <v-card
          v-for="group in stateGroups"
          :key="group.title"
          rounded="xl"
          elevation="0"
          class="admin-card h-100"
        >
          <v-card-title class="admin-card__title">
            {{ group.title }}
          </v-card-title>
          <v-card-text class="pt-1">
            <div
              v-for="row in group.rows"
              :key="row.label"
              class="admin-status-row"
            >
              <div class="d-flex align-center ga-2">
                <span class="admin-status-row__dot" :style="{ backgroundColor: colorFor(row.color) }" />
                <span class="admin-status-row__label">{{ row.label }}</span>
              </div>
              <strong class="admin-status-row__value">{{ row.value }}</strong>
            </div>
          </v-card-text>
        </v-card>
      </div>

      <v-card rounded="xl" elevation="0" class="admin-card mt-4">
        <v-card-title class="admin-card__title">
          Actividade administrativa recente
        </v-card-title>
        <v-card-subtitle class="admin-card__subtitle">
          Últimas acções registadas na auditoria.
        </v-card-subtitle>

        <v-card-text class="pt-1">
          <div
            v-if="!recentActions.length"
            class="admin-empty-copy py-3"
          >
            Ainda não existem acções administrativas registadas.
          </div>

          <div v-else class="admin-activity-list">
            <div
              v-for="action in recentActions"
              :key="action.id"
              class="admin-activity-item"
            >
              <span class="admin-activity-item__icon">
                <v-icon size="19">mdi-history</v-icon>
              </span>
              <div class="min-width-0">
                <p class="admin-activity-item__title">
                  {{ getAdminActionLabel(action.action) }}
                </p>
                <p class="admin-activity-item__meta">
                  {{ actionSubtitle(action) }}
                </p>
              </div>
              <span class="admin-activity-item__time">
                {{ formatDateTime(action.created_at) }}
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AdminBarList from '@/components/admin/AdminBarList.vue'
import AdminTrendChart from '@/components/admin/AdminTrendChart.vue'
import { fetchAdminDashboard } from '@/services/admin'
import { getAdminActionLabel } from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'

const periodOptions = [
  { title: 'Últimos 7 dias', value: 7 },
  { title: 'Últimos 30 dias', value: 30 },
  { title: 'Últimos 90 dias', value: 90 },
  { title: 'Últimos 12 meses', value: 365 },
]

const loading = ref(true)
const error = ref('')
const data = ref(null)
const days = ref(30)

const palette = {
  primary: '#164E80',
  info: '#0EA5E9',
  warning: '#EF6C00',
  success: '#2E7D32',
  error: '#C62828',
  secondary: '#60758A',
}

const periodLabel = computed(() => (
  periodOptions.find((item) => item.value === days.value)?.title ?? 'Período seleccionado'
))

const mainStats = computed(() => {
  const users = data.value?.users ?? {}
  const items = data.value?.items ?? {}
  const claims = data.value?.claims ?? {}
  const reports = data.value?.reports ?? {}
  const metrics = data.value?.metrics ?? {}

  return [
    {
      icon: 'mdi-account-group-outline',
      tone: 'primary',
      color: 'primary',
      badge: 'Contas',
      value: users.total ?? 0,
      label: 'Utilizadores registados',
      caption: `${users.students ?? 0} estudantes · ${users.suspended ?? 0} suspensos`,
    },
    {
      icon: 'mdi-bag-personal-outline',
      tone: 'info',
      color: 'info',
      badge: 'Anúncios',
      value: items.total ?? 0,
      label: 'Anúncios no total',
      caption: `${items.published ?? 0} publicados · ${items.moderated ?? 0} retirados`,
    },
    {
      icon: 'mdi-clipboard-alert-outline',
      tone: 'warning',
      color: 'warning',
      badge: 'Trabalho',
      value: metrics.pending_workload ?? 0,
      label: 'Itens a acompanhar',
      caption: `${claims.pending ?? 0} solicitações · ${(reports.pending ?? 0) + (reports.reviewing ?? 0)} denúncias`,
    },
    {
      icon: 'mdi-check-decagram-outline',
      tone: 'success',
      color: 'success',
      badge: 'Recuperação',
      value: `${metrics.recovery_rate ?? 0}%`,
      label: 'Taxa de recuperação',
      caption: `${items.returned ?? 0} objectos devolvidos`,
    },
  ]
})

const periodMetrics = computed(() => {
  const totals = data.value?.period_totals ?? {}

  return [
    { label: 'Anúncios criados', value: totals.items_created ?? 0 },
    { label: 'Solicitações criadas', value: totals.claims_created ?? 0 },
    { label: 'Devoluções concluídas', value: totals.returns_completed ?? 0 },
    { label: 'Denúncias recebidas', value: totals.reports_created ?? 0 },
  ]
})

const chartSeries = computed(() => {
  const series = data.value?.series ?? {}

  return [
    {
      key: 'items',
      label: 'Anúncios',
      color: palette.primary,
      values: series.items ?? [],
    },
    {
      key: 'claims',
      label: 'Solicitações',
      color: palette.warning,
      values: series.claims ?? [],
    },
    {
      key: 'returns',
      label: 'Devoluções',
      color: palette.success,
      values: series.returns ?? [],
    },
    {
      key: 'reports',
      label: 'Denúncias',
      color: palette.error,
      values: series.reports ?? [],
    },
  ]
})

const stateGroups = computed(() => {
  const users = data.value?.users ?? {}
  const items = data.value?.items ?? {}
  const claims = data.value?.claims ?? {}
  const reports = data.value?.reports ?? {}

  return [
    {
      title: 'Utilizadores',
      rows: [
        { label: 'Administradores', value: users.admins ?? 0, color: 'primary' },
        { label: 'Estudantes', value: users.students ?? 0, color: 'secondary' },
        { label: 'Suspensos', value: users.suspended ?? 0, color: 'error' },
      ],
    },
    {
      title: 'Anúncios por estado',
      rows: [
        { label: 'Em aberto', value: items.open ?? 0, color: 'success' },
        { label: 'Em recuperação', value: items.pending ?? 0, color: 'warning' },
        { label: 'Devolvidos', value: items.returned ?? 0, color: 'info' },
      ],
    },
    {
      title: 'Solicitações por estado',
      rows: [
        { label: 'Pendentes', value: claims.pending ?? 0, color: 'warning' },
        { label: 'Aceites', value: claims.accepted ?? 0, color: 'info' },
        { label: 'Recusadas', value: claims.rejected ?? 0, color: 'error' },
        { label: 'Concluídas', value: claims.completed ?? 0, color: 'success' },
      ],
    },
    {
      title: 'Denúncias por estado',
      rows: [
        { label: 'Pendentes', value: reports.pending ?? 0, color: 'warning' },
        { label: 'Em análise', value: reports.reviewing ?? 0, color: 'info' },
        { label: 'Resolvidas', value: reports.resolved ?? 0, color: 'success' },
        { label: 'Arquivadas', value: reports.dismissed ?? 0, color: 'secondary' },
      ],
    },
  ]
})

const recentActions = computed(() => data.value?.recent_actions ?? [])

function colorFor(tone) {
  return palette[tone] ?? palette.primary
}

function actionSubtitle(action) {
  if (!action.target_type || !action.target_id) {
    return action.reason ? `Motivo: ${action.reason}` : 'Sem alvo associado.'
  }

  return `${action.target_type} #${action.target_id}${action.reason ? ` · ${action.reason}` : ''}`
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    data.value = await fetchAdminDashboard({ days: days.value })
  } catch (requestError) {
    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar o dashboard administrativo.',
    )
  } finally {
    loading.value = false
  }
}

watch(days, load)

onMounted(load)
</script>
