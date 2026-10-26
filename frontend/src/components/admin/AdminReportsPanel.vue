<template>
  <section aria-labelledby="admin-reports-title">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h2 id="admin-reports-title" class="text-h5 font-weight-bold mb-1">
          Denúncias
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Analisa denúncias de anúncios ou comportamentos e registra a decisão.
        </p>
      </div>

      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-refresh"
        :loading="loading"
        :disabled="loading"
        @click="load()"
      >
        Actualizar
      </v-btn>
    </div>

    <v-alert
      v-if="successMessage"
      type="success"
      variant="tonal"
      density="compact"
      closable
      class="mb-4"
      @click:close="successMessage = ''"
    >
      {{ successMessage }}
    </v-alert>

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
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filters.q"
              label="Pesquisar denunciante, anúncio ou utilizador"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @keyup.enter="applyFilters"
            />
          </v-col>

          <v-col cols="6" md="3">
            <v-select
              v-model="filters.status"
              :items="statusFilterOptions"
              item-title="title"
              item-value="value"
              label="Estado"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="6" md="2">
            <v-select
              v-model="filters.target_type"
              :items="targetOptions"
              item-title="title"
              item-value="value"
              label="Alvo"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3" class="d-flex ga-2">
            <v-btn
              block
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-magnify"
              @click="applyFilters"
            >
              Pesquisar
            </v-btn>
            <v-btn
              color="primary"
              variant="outlined"
              rounded="lg"
              @click="clearFilters"
            >
              Limpar
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div v-if="loading" aria-busy="true" aria-label="A carregar denúncias">
      <v-skeleton-loader type="table" rounded="xl" />
    </div>

    <EmptyState
      v-else-if="!reports.length"
      icon="mdi-flag-outline"
      title="Sem denúncias"
      description="Não existem denúncias que correspondam aos filtros selecionados."
    >
      <template #actions>
        <v-btn color="primary" variant="outlined" rounded="lg" @click="clearFilters">
          Limpar filtros
        </v-btn>
      </template>
    </EmptyState>

    <template v-else>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Alvo</th>
              <th scope="col">Denunciante</th>
              <th scope="col">Motivo</th>
              <th scope="col">Estado</th>
              <th scope="col">Recebida</th>
              <th scope="col"><span class="sr-only">Acções</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="report in reports" :key="report.id">
              <td>
                <div class="admin-table__primary">{{ getReportTargetLabel(report) }}</div>
                <div class="admin-table__secondary">
                  {{ report.target_type === 'user' ? 'Comportamento de utilizador' : 'Anúncio' }}
                </div>
              </td>
              <td>
                <div>{{ report.reporter?.name || 'Conta removida' }}</div>
                <div class="admin-table__secondary">{{ report.reporter?.email || 'Email indisponível' }}</div>
              </td>
              <td>{{ getReportReasonLabel(report.reason) }}</td>
              <td>
                <v-chip
                  :color="getReportStatusColor(report.status)"
                  variant="tonal"
                  size="small"
                >
                  {{ getReportStatusLabel(report.status) }}
                </v-chip>
              </td>
              <td class="text-no-wrap">{{ formatDateTime(report.created_at) }}</td>
              <td>
                <v-btn
                  size="small"
                  variant="text"
                  color="primary"
                  prepend-icon="mdi-clipboard-text-search-outline"
                  @click="openDetails(report)"
                >
                  Analisar
                </v-btn>
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

    <v-dialog v-model="detailDialog" max-width="760">
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          Análise da denúncia
        </v-card-title>

        <v-card-text class="px-6">
          <div v-if="detailLoading" class="py-6">
            <v-skeleton-loader type="article" />
          </div>

          <v-alert
            v-else-if="detailError"
            type="error"
            variant="tonal"
            density="compact"
          >
            {{ detailError }}
          </v-alert>

          <template v-else-if="detailReport">
            <div class="d-flex flex-wrap align-center ga-2 mb-4">
              <v-chip
                :color="getReportStatusColor(detailReport.status)"
                variant="tonal"
                size="small"
              >
                {{ getReportStatusLabel(detailReport.status) }}
              </v-chip>
              <v-chip variant="outlined" size="small">
                {{ getReportReasonLabel(detailReport.reason) }}
              </v-chip>
            </div>

            <v-list bg-color="transparent" density="comfortable">
              <v-list-item
                title="Alvo"
                :subtitle="getReportTargetLabel(detailReport)"
              />
              <v-list-item
                title="Denunciante"
                :subtitle="`${detailReport.reporter?.name || 'Conta removida'} · ${detailReport.reporter?.email || 'Email indisponível'}`"
              />
              <v-list-item
                v-if="detailReport.item?.user"
                title="Autor do anúncio"
                :subtitle="`${detailReport.item.user.name} · ${detailReport.item.user.email}`"
              />
              <v-list-item
                v-if="detailReport.reported_user"
                title="Utilizador denunciado"
                :subtitle="`${detailReport.reported_user.name} · ${detailReport.reported_user.email}`"
              />
              <v-list-item
                title="Recebida em"
                :subtitle="formatDateTime(detailReport.created_at)"
              />
              <v-list-item
                v-if="detailReport.handled_at"
                title="Última decisão em"
                :subtitle="formatDateTime(detailReport.handled_at)"
              />
              <v-list-item
                v-if="detailReport.handled_by"
                title="Decidida por"
                :subtitle="detailReport.handled_by.name"
              />
            </v-list>

            <v-divider class="my-4" />

            <h4 class="text-subtitle-1 font-weight-bold mb-2">
              Detalhes enviados
            </h4>
            <p class="text-body-2 text-medium-emphasis detail-description mb-0">
              {{ detailReport.details || 'Sem detalhes adicionais.' }}
            </p>

            <v-divider class="my-4" />

            <h4 class="text-subtitle-1 font-weight-bold mb-3">
              Acompanhamento e decisão
            </h4>

            <v-select
              v-model="decisionStatus"
              :items="reportStatusOptions"
              item-title="title"
              item-value="value"
              label="Estado da denúncia"
              variant="outlined"
              density="compact"
              class="mb-4"
              :disabled="decisionSubmitting"
            />

            <v-textarea
              v-model="resolutionNote"
              label="Nota de acompanhamento / decisão"
              :placeholder="requiresNote
                ? 'Obrigatória para resolver ou arquivar a denúncia.'
                : 'Podes registar uma nota sobre o acompanhamento.'"
              rows="3"
              maxlength="2000"
              counter="2000"
              auto-grow
              :disabled="decisionSubmitting"
            />

            <v-alert
              v-if="decisionError"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-4"
            >
              {{ decisionError }}
            </v-alert>
          </template>
        </v-card-text>

        <v-card-actions class="pa-6">
          <v-spacer />
          <v-btn
            variant="text"
            color="primary"
            rounded="lg"
            :disabled="decisionSubmitting"
            @click="detailDialog = false"
          >
            Fechar
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            :loading="decisionSubmitting"
            :disabled="decisionSubmitting || detailLoading"
            @click="submitDecision"
          >
            Guardar decisão
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import {
  fetchAdminReport,
  fetchAdminReports,
  updateAdminReport,
} from '@/services/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'
import {
  getReportReasonLabel,
  getReportStatusColor,
  getReportStatusLabel,
  getReportTargetLabel,
  reportStatusOptions,
} from '@/utils/report'

const statusFilterOptions = [
  { title: 'Todos os estados', value: '' },
  ...reportStatusOptions,
]

const targetOptions = [
  { title: 'Todos os alvos', value: '' },
  { title: 'Anúncios', value: 'item' },
  { title: 'Utilizadores', value: 'user' },
]

const filters = reactive({
  q: '',
  status: '',
  target_type: '',
})

const reports = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detailReport = ref(null)

const decisionStatus = ref('pending')
const resolutionNote = ref('')
const decisionSubmitting = ref(false)
const decisionError = ref('')

const requiresNote = computed(() => (
  ['resolved', 'dismissed'].includes(decisionStatus.value)
))

function queryParams() {
  return {
    q: (filters.q || '').trim() || undefined,
    status: filters.status || undefined,
    target_type: filters.target_type || undefined,
  }
}

async function load(targetPage = page.value) {
  loading.value = true
  errorMessage.value = ''

  try {
    const result = await fetchAdminReports({
      ...queryParams(),
      page: targetPage,
    })

    reports.value = result.items
    meta.value = result.meta
    page.value = result.meta?.current_page ?? targetPage
  } catch (requestError) {
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as denúncias.',
    )
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  load(1)
}

function clearFilters() {
  filters.q = ''
  filters.status = ''
  filters.target_type = ''
  page.value = 1
  load(1)
}

async function openDetails(report) {
  detailDialog.value = true
  detailLoading.value = true
  detailError.value = ''
  detailReport.value = null
  decisionError.value = ''

  try {
    detailReport.value = await fetchAdminReport(report.id)
    decisionStatus.value = detailReport.value.status
    resolutionNote.value = detailReport.value.resolution_note || ''
  } catch (requestError) {
    detailError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os detalhes da denúncia.',
    )
  } finally {
    detailLoading.value = false
  }
}

async function submitDecision() {
  if (decisionSubmitting.value || !detailReport.value) return

  decisionError.value = ''
  successMessage.value = ''

  if (requiresNote.value && resolutionNote.value.trim().length < 3) {
    decisionError.value = 'Indica uma nota de decisão antes de encerrar a denúncia.'
    return
  }

  decisionSubmitting.value = true

  try {
    await updateAdminReport(detailReport.value.id, {
      status: decisionStatus.value,
      resolution_note: resolutionNote.value.trim() || null,
    })

    successMessage.value = 'Denúncia actualizada com sucesso.'
    detailDialog.value = false
    await load()
  } catch (requestError) {
    decisionError.value = getApiErrorMessage(
      requestError,
      'Não foi possível actualizar a denúncia.',
    )
  } finally {
    decisionSubmitting.value = false
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.detail-description {
  color: #263445;
  line-height: 1.7;
  white-space: pre-line;
}
</style>
