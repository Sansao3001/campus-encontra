<template>
  <section aria-labelledby="admin-dashboard-title">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h2 id="admin-dashboard-title" class="text-h5 font-weight-bold mb-1">
          Visão geral
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Indicadores actualizados da plataforma.
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
      <v-row>
        <v-col v-for="index in 8" :key="index" cols="12" sm="6" lg="3">
          <v-skeleton-loader type="card" rounded="xl" />
        </v-col>
      </v-row>
    </div>

    <template v-else-if="data && !error">
      <v-row>
        <v-col
          v-for="stat in mainStats"
          :key="stat.label"
          cols="12"
          sm="6"
          lg="3"
        >
          <v-card rounded="xl" elevation="0" class="admin-card h-100">
            <v-card-text>
              <div class="d-flex align-center justify-space-between ga-3 mb-4">
                <v-avatar size="44" :color="stat.color" variant="tonal">
                  <v-icon size="24">{{ stat.icon }}</v-icon>
                </v-avatar>
                <v-chip size="small" variant="tonal" :color="stat.color">
                  {{ stat.badge }}
                </v-chip>
              </div>

              <div class="text-h4 font-weight-bold admin-stat-value">
                {{ stat.value }}
              </div>
              <div class="text-body-2 font-weight-medium mt-1">{{ stat.label }}</div>
              <div class="text-caption text-medium-emphasis mt-1">{{ stat.caption }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mt-2">
        <v-col
          v-for="group in stateGroups"
          :key="group.title"
          cols="12"
          md="6"
          lg="3"
        >
          <v-card rounded="xl" elevation="0" class="admin-card h-100">
            <v-card-title class="text-subtitle-1 font-weight-bold">
              {{ group.title }}
            </v-card-title>
            <v-card-text class="pt-0">
              <div
                v-for="row in group.rows"
                :key="row.label"
                class="d-flex align-center justify-space-between ga-3 py-2"
              >
                <span class="text-body-2 text-medium-emphasis">{{ row.label }}</span>
                <v-chip size="small" variant="tonal" :color="row.color">
                  {{ row.value }}
                </v-chip>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-card rounded="xl" elevation="0" class="admin-card mt-4">
        <v-card-title class="text-subtitle-1 font-weight-bold">
          Actividade administrativa recente
        </v-card-title>

        <v-card-text class="pt-0">
          <div v-if="!recentActions.length" class="text-body-2 text-medium-emphasis py-3">
            Ainda não existem acções administrativas registadas.
          </div>

          <v-list v-else bg-color="transparent" density="comfortable">
            <v-list-item
              v-for="action in recentActions"
              :key="action.id"
              :title="getAdminActionLabel(action.action)"
              :subtitle="actionSubtitle(action)"
              prepend-icon="mdi-history"
            >
              <template #append>
                <span class="text-caption text-medium-emphasis">
                  {{ formatDateTime(action.created_at) }}
                </span>
              </template>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { fetchAdminDashboard } from '@/services/admin'
import { getAdminActionLabel } from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'

const loading = ref(true)
const error = ref('')
const data = ref(null)

const mainStats = computed(() => {
  const users = data.value?.users ?? {}
  const items = data.value?.items ?? {}
  const claims = data.value?.claims ?? {}
  const reports = data.value?.reports ?? {}

  return [
    {
      icon: 'mdi-account-group-outline',
      color: 'primary',
      badge: 'Contas',
      value: users.total ?? 0,
      label: 'Utilizadores registados',
      caption: `${users.admins ?? 0} administradores, ${users.suspended ?? 0} suspensos`,
    },
    {
      icon: 'mdi-bag-personal-outline',
      color: 'info',
      badge: 'Anúncios',
      value: items.total ?? 0,
      label: 'Anúncios no total',
      caption: `${items.published ?? 0} publicados, ${items.moderated ?? 0} retirados`,
    },
    {
      icon: 'mdi-magnify',
      color: 'warning',
      badge: 'Perdidos',
      value: items.lost ?? 0,
      label: 'Objectos perdidos',
      caption: 'Anúncios classificados como perdidos',
    },
    {
      icon: 'mdi-check-decagram-outline',
      color: 'success',
      badge: 'Encontrados',
      value: items.found ?? 0,
      label: 'Objectos encontrados',
      caption: 'Anúncios classificados como encontrados',
    },
    {
      icon: 'mdi-handshake-outline',
      color: 'warning',
      badge: 'Pendentes',
      value: claims.pending ?? 0,
      label: 'Solicitações pendentes',
      caption: `${claims.total ?? 0} solicitações no total`,
    },
    {
      icon: 'mdi-check-circle-outline',
      color: 'success',
      badge: 'Devolvidos',
      value: items.returned ?? 0,
      label: 'Objectos devolvidos',
      caption: `${claims.completed ?? 0} solicitações concluídas`,
    },
    {
      icon: 'mdi-flag-outline',
      color: 'error',
      badge: 'Denúncias',
      value: reports.pending ?? 0,
      label: 'Denúncias pendentes',
      caption: `${reports.total ?? 0} denúncias no total`,
    },
    {
      icon: 'mdi-eye-off-outline',
      color: 'secondary',
      badge: 'Moderação',
      value: items.moderated ?? 0,
      label: 'Anúncios retirados',
      caption: 'Conteúdo fora da listagem pública',
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
        { label: 'Canceladas', value: claims.cancelled ?? 0, color: 'secondary' },
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

function actionSubtitle(action) {
  if (!action.target_type || !action.target_id) return 'Sem alvo associado.'

  return `Alvo: ${action.target_type} #${action.target_id}`
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    data.value = await fetchAdminDashboard()
  } catch (requestError) {
    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar o dashboard administrativo.',
    )
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
