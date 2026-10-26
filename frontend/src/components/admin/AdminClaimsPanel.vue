<template>
  <section aria-labelledby="admin-claims-title">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h2 id="admin-claims-title" class="text-h5 font-weight-bold mb-1">
          Solicitações
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Acompanha o estado das solicitações de recuperação.
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
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4"
      prepend-icon="mdi-lock-outline"
    >
      Esta área mostra o estado das solicitações, mas não expõe as mensagens privadas trocadas entre as partes.
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
          <v-col cols="12" md="6">
            <v-text-field
              v-model="filters.q"
              label="Pesquisar anúncio, reclamante ou responsável"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @keyup.enter="applyFilters"
            />
          </v-col>

          <v-col cols="12" md="3">
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

    <div v-if="loading" aria-busy="true" aria-label="A carregar solicitações">
      <v-skeleton-loader type="table" rounded="xl" />
    </div>

    <EmptyState
      v-else-if="!claims.length"
      icon="mdi-handshake-outline"
      title="Sem solicitações"
      description="Não existem solicitações que correspondam aos filtros selecionados."
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
              <th scope="col">Anúncio</th>
              <th scope="col">Reclamante</th>
              <th scope="col">Responsável</th>
              <th scope="col">Estado</th>
              <th scope="col">Criada</th>
              <th scope="col"><span class="sr-only">Acções</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="claim in claims" :key="claim.id">
              <td>
                <div class="admin-table__primary">
                  {{ claim.item?.title || 'Anúncio já removido' }}
                </div>
                <div class="admin-table__secondary">
                  {{ claim.item ? getTypeConfig(claim.item.type).label : 'Sem anúncio associado' }}
                </div>
              </td>
              <td>
                <div>{{ claim.claimant?.name || 'Conta removida' }}</div>
                <div class="admin-table__secondary">{{ claim.claimant?.email || 'Email indisponível' }}</div>
              </td>
              <td>
                <div>{{ claim.item?.user?.name || 'Conta removida' }}</div>
                <div class="admin-table__secondary">{{ claim.item?.user?.email || 'Email indisponível' }}</div>
              </td>
              <td>
                <v-chip
                  :color="getClaimStatusColor(claim.status)"
                  variant="tonal"
                  size="small"
                >
                  {{ getClaimStatusLabel(claim.status) }}
                </v-chip>
              </td>
              <td class="text-no-wrap">{{ formatDateTime(claim.created_at) }}</td>
              <td>
                <v-btn
                  size="small"
                  variant="text"
                  color="primary"
                  prepend-icon="mdi-eye-outline"
                  @click="openDetails(claim)"
                >
                  Detalhes
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

    <v-dialog v-model="detailDialog" max-width="680">
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          Detalhes da solicitação
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

          <template v-else-if="detailClaim">
            <div class="d-flex align-center ga-2 mb-4">
              <v-chip
                :color="getClaimStatusColor(detailClaim.status)"
                variant="tonal"
                size="small"
              >
                {{ getClaimStatusLabel(detailClaim.status) }}
              </v-chip>
              <v-chip
                v-if="detailClaim.item?.is_moderated"
                color="error"
                variant="tonal"
                size="small"
              >
                Anúncio retirado
              </v-chip>
            </div>

            <h3 class="text-h6 font-weight-bold mb-3">
              {{ detailClaim.item?.title || 'Anúncio já removido' }}
            </h3>

            <v-list bg-color="transparent" density="comfortable">
              <v-list-item
                title="Reclamante"
                :subtitle="`${detailClaim.claimant?.name || 'Conta removida'} · ${detailClaim.claimant?.email || 'Email indisponível'}`"
              />
              <v-list-item
                title="Responsável pelo anúncio"
                :subtitle="`${detailClaim.item?.user?.name || 'Conta removida'} · ${detailClaim.item?.user?.email || 'Email indisponível'}`"
              />
              <v-list-item
                title="Criada em"
                :subtitle="formatDateTime(detailClaim.created_at)"
              />
              <v-list-item
                v-if="detailClaim.decided_at"
                title="Decidida em"
                :subtitle="formatDateTime(detailClaim.decided_at)"
              />
              <v-list-item
                v-if="detailClaim.completed_at"
                title="Concluída em"
                :subtitle="formatDateTime(detailClaim.completed_at)"
              />
            </v-list>

            <v-divider class="my-4" />

            <h4 class="text-subtitle-1 font-weight-bold mb-2">
              Mensagem inicial
            </h4>
            <p class="text-body-2 text-medium-emphasis detail-description mb-0">
              {{ detailClaim.message || 'Sem mensagem inicial.' }}
            </p>

            <v-alert
              type="info"
              variant="tonal"
              density="compact"
              class="mt-5"
              prepend-icon="mdi-lock-outline"
            >
              As mensagens privadas da conversa não são carregadas nesta área administrativa.
            </v-alert>
          </template>
        </v-card-text>

        <v-card-actions class="pa-6">
          <v-spacer />
          <v-btn variant="text" color="primary" rounded="lg" @click="detailDialog = false">
            Fechar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import { fetchAdminClaim, fetchAdminClaims } from '@/services/admin'
import {
  claimStatusOptions,
  getClaimStatusColor,
  getClaimStatusLabel,
} from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'
import { getTypeConfig } from '@/utils/item'

const statusFilterOptions = [
  { title: 'Todos os estados', value: '' },
  ...claimStatusOptions,
]

const filters = reactive({
  q: '',
  status: '',
})

const claims = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const errorMessage = ref('')

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detailClaim = ref(null)

function queryParams() {
  return {
    q: (filters.q || '').trim() || undefined,
    status: filters.status || undefined,
  }
}

async function load(targetPage = page.value) {
  loading.value = true
  errorMessage.value = ''

  try {
    const result = await fetchAdminClaims({
      ...queryParams(),
      page: targetPage,
    })

    claims.value = result.items
    meta.value = result.meta
    page.value = result.meta?.current_page ?? targetPage
  } catch (requestError) {
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as solicitações.',
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
  page.value = 1
  load(1)
}

async function openDetails(claim) {
  detailDialog.value = true
  detailLoading.value = true
  detailError.value = ''
  detailClaim.value = null

  try {
    detailClaim.value = await fetchAdminClaim(claim.id)
  } catch (requestError) {
    detailError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os detalhes da solicitação.',
    )
  } finally {
    detailLoading.value = false
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
