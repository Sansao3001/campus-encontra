<template>
  <section aria-labelledby="admin-items-title">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h2 id="admin-items-title" class="text-h5 font-weight-bold mb-1">
          Anúncios
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Consulta publicações e retira conteúdo inadequado sem eliminar o histórico.
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
              label="Pesquisar anúncios ou autores"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @keyup.enter="applyFilters"
            />
          </v-col>

          <v-col cols="6" md="2">
            <v-select
              v-model="filters.type"
              :items="typeOptions"
              item-title="title"
              item-value="value"
              label="Tipo"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="6" md="2">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
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
              v-model="filters.moderation"
              :items="moderationOptions"
              item-title="title"
              item-value="value"
              label="Publicação"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="2" class="d-flex ga-2">
            <v-btn
              block
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-magnify"
              @click="applyFilters"
            >
              Filtrar
            </v-btn>
            <v-btn
              color="primary"
              variant="outlined"
              rounded="lg"
              aria-label="Limpar filtros"
              @click="clearFilters"
            >
              <v-icon>mdi-filter-off-outline</v-icon>
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div v-if="loading" aria-busy="true" aria-label="A carregar anúncios">
      <v-skeleton-loader type="table" rounded="xl" />
    </div>

    <EmptyState
      v-else-if="!items.length"
      icon="mdi-bag-personal-outline"
      title="Sem anúncios"
      description="Não existem anúncios que correspondam aos filtros selecionados."
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
              <th scope="col">Autor</th>
              <th scope="col">Classificação</th>
              <th scope="col">Publicação</th>
              <th scope="col">Denúncias</th>
              <th scope="col">Criado</th>
              <th scope="col"><span class="sr-only">Acções</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <div class="admin-table__primary">{{ item.title }}</div>
                <div class="admin-table__secondary">
                  {{ item.category }} · {{ item.location || 'Local não indicado' }}
                </div>
              </td>
              <td>
                <div>{{ item.user?.name || 'Autor removido' }}</div>
                <div class="admin-table__secondary">{{ item.user?.email || 'Email indisponível' }}</div>
              </td>
              <td>
                <div class="d-flex flex-wrap ga-1">
                  <StatusChip kind="type" :value="item.type" />
                  <StatusChip kind="status" :value="item.status" />
                </div>
              </td>
              <td>
                <v-chip
                  :color="getModerationColor(item)"
                  variant="tonal"
                  size="small"
                >
                  {{ getModerationLabel(item) }}
                </v-chip>
                <div v-if="item.is_moderated" class="admin-table__secondary mt-1">
                  {{ formatDateTime(item.moderated_at) }}
                </div>
              </td>
              <td>{{ item.reports_count ?? 0 }}</td>
              <td class="text-no-wrap">{{ formatDate(item.created_at) }}</td>
              <td>
                <div class="admin-actions">
                  <v-btn
                    size="small"
                    variant="text"
                    color="primary"
                    prepend-icon="mdi-eye-outline"
                    @click="openDetails(item)"
                  >
                    Detalhes
                  </v-btn>

                  <v-btn
                    v-if="!item.is_moderated"
                    size="small"
                    variant="text"
                    color="error"
                    prepend-icon="mdi-eye-off-outline"
                    @click="openModerate(item)"
                  >
                    Retirar
                  </v-btn>

                  <v-btn
                    v-else
                    size="small"
                    variant="text"
                    color="success"
                    prepend-icon="mdi-eye-outline"
                    @click="openRestore(item)"
                  >
                    Repor
                  </v-btn>
                </div>
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
          Detalhes do anúncio
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

          <template v-else-if="detailItem">
            <div class="d-flex flex-wrap ga-2 mb-4">
              <StatusChip kind="type" :value="detailItem.type" />
              <StatusChip kind="status" :value="detailItem.status" />
              <v-chip
                :color="getModerationColor(detailItem)"
                variant="tonal"
                size="small"
              >
                {{ getModerationLabel(detailItem) }}
              </v-chip>
            </div>

            <h3 class="text-h6 font-weight-bold mb-2">{{ detailItem.title }}</h3>

            <v-img
              v-if="detailItem.photo_url"
              :src="detailItem.photo_url"
              :alt="`Fotografia de ${detailItem.title}`"
              max-height="320"
              contain
              class="detail-photo mb-4"
            />

            <p class="text-body-1 detail-description mb-4">
              {{ detailItem.description || 'Sem descrição disponível.' }}
            </p>

            <v-list bg-color="transparent" density="comfortable">
              <v-list-item title="Categoria" :subtitle="detailItem.category" />
              <v-list-item title="Local" :subtitle="detailItem.location || 'Não indicado'" />
              <v-list-item
                title="Data da ocorrência"
                :subtitle="formatDate(detailItem.occurrence_date)"
              />
              <v-list-item
                title="Autor"
                :subtitle="`${detailItem.user?.name || 'Autor removido'} · ${detailItem.user?.email || 'Email indisponível'}`"
              />
              <v-list-item
                title="Reclamações"
                :subtitle="String(detailItem.claims_count ?? 0)"
              />
              <v-list-item
                title="Denúncias"
                :subtitle="String(detailItem.reports_count ?? 0)"
              />
              <v-list-item
                v-if="detailItem.is_moderated"
                title="Retirado em"
                :subtitle="formatDateTime(detailItem.moderated_at)"
              />
              <v-list-item
                v-if="detailItem.is_moderated"
                title="Razão da moderação"
                :subtitle="detailItem.moderation_reason || 'Não indicada'"
              />
              <v-list-item
                v-if="detailItem.moderator"
                title="Moderado por"
                :subtitle="detailItem.moderator.name"
              />
            </v-list>
          </template>
        </v-card-text>

        <v-card-actions class="pa-6">
          <v-spacer />
          <v-btn variant="text" color="primary" rounded="lg" @click="detailDialog = false">
            Fechar
          </v-btn>
          <v-btn
            v-if="detailItem && !detailItem.is_moderated"
            color="error"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-eye-off-outline"
            @click="openModerate(detailItem)"
          >
            Retirar anúncio
          </v-btn>
          <v-btn
            v-else-if="detailItem"
            color="success"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-eye-outline"
            @click="openRestore(detailItem)"
          >
            Repor anúncio
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="actionDialog" max-width="560" persistent>
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          {{ actionType === 'moderate' ? 'Retirar anúncio' : 'Repor anúncio' }}
        </v-card-title>

        <v-card-text class="px-6">
          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ actionDescription }}
          </p>

          <v-textarea
            v-model="actionReason"
            :label="actionType === 'moderate' ? 'Razão da moderação' : 'Nota de reposição (opcional)'"
            :placeholder="actionType === 'moderate'
              ? 'Indica o motivo, para ficar registado na auditoria.'
              : 'Podes registar uma nota sobre a revisão.'"
            rows="3"
            maxlength="1000"
            counter="1000"
            auto-grow
            :disabled="actionSubmitting"
          />

          <v-alert
            v-if="actionError"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            {{ actionError }}
          </v-alert>
        </v-card-text>

        <v-card-actions class="pa-6">
          <v-spacer />
          <v-btn
            variant="text"
            color="primary"
            rounded="lg"
            :disabled="actionSubmitting"
            @click="closeActionDialog"
          >
            Cancelar
          </v-btn>
          <v-btn
            :color="actionType === 'moderate' ? 'error' : 'success'"
            variant="flat"
            rounded="lg"
            :loading="actionSubmitting"
            :disabled="actionSubmitting"
            @click="submitAction"
          >
            {{ actionType === 'moderate' ? 'Confirmar retirada' : 'Confirmar reposição' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import StatusChip from '@/components/StatusChip.vue'
import {
  fetchAdminItem,
  fetchAdminItems,
  moderateAdminItem,
  restoreAdminItem,
} from '@/services/admin'
import {
  getModerationColor,
  getModerationLabel,
} from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDate, formatDateTime } from '@/utils/format'

const typeOptions = [
  { title: 'Todos os tipos', value: '' },
  { title: 'Perdido', value: 'lost' },
  { title: 'Encontrado', value: 'found' },
]

const statusOptions = [
  { title: 'Todos os estados', value: '' },
  { title: 'Em aberto', value: 'open' },
  { title: 'Em recuperação', value: 'pending' },
  { title: 'Devolvido', value: 'returned' },
]

const moderationOptions = [
  { title: 'Todas as publicações', value: '' },
  { title: 'Publicados', value: 'visible' },
  { title: 'Retirados', value: 'moderated' },
]

const filters = reactive({
  q: '',
  type: '',
  status: '',
  moderation: '',
})

const items = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detailItem = ref(null)

const actionDialog = ref(false)
const actionType = ref('moderate')
const actionItem = ref(null)
const actionReason = ref('')
const actionSubmitting = ref(false)
const actionError = ref('')

const actionDescription = computed(() => {
  if (!actionItem.value) return ''

  if (actionType.value === 'moderate') {
    return `O anúncio "${actionItem.value.title}" deixará de aparecer na listagem pública. O registo e o histórico serão preservados.`
  }

  return `O anúncio "${actionItem.value.title}" voltará a aparecer na listagem pública.`
})

function queryParams() {
  return {
    q: (filters.q || '').trim() || undefined,
    type: filters.type || undefined,
    status: filters.status || undefined,
    moderation: filters.moderation || undefined,
  }
}

async function load(targetPage = page.value) {
  loading.value = true
  errorMessage.value = ''

  try {
    const result = await fetchAdminItems({
      ...queryParams(),
      page: targetPage,
    })

    items.value = result.items
    meta.value = result.meta
    page.value = result.meta?.current_page ?? targetPage
  } catch (requestError) {
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os anúncios.',
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
  filters.type = ''
  filters.status = ''
  filters.moderation = ''
  page.value = 1
  load(1)
}

async function openDetails(item) {
  detailDialog.value = true
  detailLoading.value = true
  detailError.value = ''
  detailItem.value = null

  try {
    detailItem.value = await fetchAdminItem(item.id)
  } catch (requestError) {
    detailError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os detalhes do anúncio.',
    )
  } finally {
    detailLoading.value = false
  }
}

function openModerate(item) {
  actionType.value = 'moderate'
  actionItem.value = item
  actionReason.value = ''
  actionError.value = ''
  actionDialog.value = true
}

function openRestore(item) {
  actionType.value = 'restore'
  actionItem.value = item
  actionReason.value = ''
  actionError.value = ''
  actionDialog.value = true
}

function closeActionDialog() {
  if (actionSubmitting.value) return

  actionDialog.value = false
  actionError.value = ''
}

async function submitAction() {
  if (actionSubmitting.value || !actionItem.value) return

  actionSubmitting.value = true
  actionError.value = ''
  successMessage.value = ''

  try {
    if (actionType.value === 'moderate') {
      if (actionReason.value.trim().length < 3) {
        actionError.value = 'Indica uma razão com pelo menos 3 caracteres.'
        return
      }

      await moderateAdminItem(actionItem.value.id, actionReason.value.trim())
      successMessage.value = 'Anúncio retirado de publicação.'
    } else {
      await restoreAdminItem(actionItem.value.id, actionReason.value.trim())
      successMessage.value = 'Anúncio reposto em publicação.'
    }

    actionDialog.value = false
    detailDialog.value = false
    await load()
  } catch (requestError) {
    actionError.value = getApiErrorMessage(
      requestError,
      'Não foi possível concluir a operação.',
    )
  } finally {
    actionSubmitting.value = false
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.detail-photo {
  border-radius: 14px;
  background: #f4f7fb;
}

.detail-description {
  color: #263445;
  line-height: 1.7;
  white-space: pre-line;
}
</style>
