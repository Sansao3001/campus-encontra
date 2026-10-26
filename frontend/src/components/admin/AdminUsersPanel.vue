<template>
  <section aria-labelledby="admin-users-title">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h2 id="admin-users-title" class="text-h5 font-weight-bold mb-1">
          Utilizadores
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Consulta contas, suspende acessos e gere papéis com auditoria.
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
          <v-col cols="12" md="5">
            <v-text-field
              v-model="filters.q"
              label="Pesquisar por nome ou email"
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
              v-model="filters.role"
              :items="roleFilterOptions"
              item-title="title"
              item-value="value"
              label="Papel"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="6" md="2">
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

    <div v-if="loading" aria-busy="true" aria-label="A carregar utilizadores">
      <v-skeleton-loader type="table" rounded="xl" />
    </div>

    <EmptyState
      v-else-if="!users.length"
      icon="mdi-account-search-outline"
      title="Sem utilizadores"
      description="Não existem utilizadores que correspondam aos filtros selecionados."
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
              <th scope="col">Utilizador</th>
              <th scope="col">Papel</th>
              <th scope="col">Estado</th>
              <th scope="col">Anúncios</th>
              <th scope="col">Reclamações</th>
              <th scope="col">Registo</th>
              <th scope="col"><span class="sr-only">Acções</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>
                <div class="admin-table__primary">
                  {{ user.name }}
                  <v-chip
                    v-if="isCurrentUser(user)"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    class="ms-1"
                  >
                    A tua conta
                  </v-chip>
                </div>
                <div class="admin-table__secondary">{{ user.email }}</div>
              </td>
              <td>
                <v-chip
                  :color="getRoleColor(user.role)"
                  variant="tonal"
                  size="small"
                >
                  {{ getRoleLabel(user.role) }}
                </v-chip>
              </td>
              <td>
                <v-chip
                  :color="getAccountStatusColor(user)"
                  variant="tonal"
                  size="small"
                >
                  {{ getAccountStatusLabel(user) }}
                </v-chip>
                <div
                  v-if="user.suspended_at"
                  class="admin-table__secondary mt-1"
                >
                  Desde {{ formatDateTime(user.suspended_at) }}
                </div>
              </td>
              <td>{{ user.items_count ?? 0 }}</td>
              <td>{{ user.claims_count ?? 0 }}</td>
              <td class="text-no-wrap">{{ formatDate(user.created_at) }}</td>
              <td>
                <div class="admin-actions">
                  <v-btn
                    size="small"
                    variant="text"
                    color="primary"
                    prepend-icon="mdi-eye-outline"
                    @click="openDetails(user)"
                  >
                    Detalhes
                  </v-btn>

                  <v-btn
                    v-if="!isCurrentUser(user)"
                    size="small"
                    variant="text"
                    :color="user.suspended_at ? 'success' : 'error'"
                    :prepend-icon="user.suspended_at ? 'mdi-account-check-outline' : 'mdi-account-cancel-outline'"
                    @click="openStatusAction(user)"
                  >
                    {{ user.suspended_at ? 'Reactivar' : 'Suspender' }}
                  </v-btn>

                  <v-btn
                    v-if="!isCurrentUser(user)"
                    size="small"
                    variant="text"
                    color="secondary"
                    :prepend-icon="user.role === 'admin' ? 'mdi-account-arrow-down-outline' : 'mdi-account-arrow-up-outline'"
                    @click="openRoleAction(user)"
                  >
                    {{ user.role === 'admin' ? 'Remover admin' : 'Promover' }}
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

    <v-dialog v-model="detailDialog" max-width="640">
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          Detalhes do utilizador
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

          <template v-else-if="detailUser">
            <v-list bg-color="transparent" density="comfortable">
              <v-list-item title="Nome" :subtitle="detailUser.name" />
              <v-list-item title="Email" :subtitle="detailUser.email" />
              <v-list-item
                title="Papel"
                :subtitle="getRoleLabel(detailUser.role)"
              />
              <v-list-item
                title="Estado da conta"
                :subtitle="getAccountStatusLabel(detailUser)"
              />
              <v-list-item
                v-if="detailUser.suspended_at"
                title="Suspensa desde"
                :subtitle="formatDateTime(detailUser.suspended_at)"
              />
              <v-list-item
                v-if="detailUser.suspension_reason"
                title="Razão da suspensão"
                :subtitle="detailUser.suspension_reason"
              />
              <v-list-item
                title="Anúncios publicados"
                :subtitle="String(detailUser.items_count ?? 0)"
              />
              <v-list-item
                title="Reclamações enviadas"
                :subtitle="String(detailUser.claims_count ?? 0)"
              />
              <v-list-item
                title="Registo"
                :subtitle="formatDateTime(detailUser.created_at)"
              />
            </v-list>
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

    <v-dialog v-model="actionDialog" max-width="560" persistent>
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          {{ actionTitle }}
        </v-card-title>

        <v-card-text class="px-6">
          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ actionDescription }}
          </p>

          <v-textarea
            v-if="actionType === 'suspend'"
            v-model="actionReason"
            label="Razão da suspensão"
            placeholder="Indica o motivo, para ficar registado na auditoria."
            rows="3"
            maxlength="255"
            counter="255"
            auto-grow
            :disabled="actionSubmitting"
          />

          <v-alert
            v-if="actionType === 'role'"
            type="warning"
            variant="tonal"
            density="compact"
          >
            Esta alteração é registada na auditoria e aplica-se imediatamente.
          </v-alert>

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
            :color="actionColor"
            variant="flat"
            rounded="lg"
            :loading="actionSubmitting"
            :disabled="actionSubmitting"
            @click="submitAction"
          >
            {{ actionConfirmLabel }}
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
  changeAdminUserRole,
  fetchAdminUser,
  fetchAdminUsers,
  reactivateAdminUser,
  suspendAdminUser,
} from '@/services/admin'
import { authState } from '@/stores/auth'
import {
  accountStatusOptions,
  getAccountStatusColor,
  getAccountStatusLabel,
  getRoleColor,
  getRoleLabel,
  roleOptions,
} from '@/utils/admin'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDate, formatDateTime } from '@/utils/format'

const roleFilterOptions = [
  { title: 'Todos os papéis', value: '' },
  ...roleOptions,
]

const statusFilterOptions = [
  { title: 'Todos os estados', value: '' },
  ...accountStatusOptions,
]

const filters = reactive({
  q: '',
  role: '',
  status: '',
})

const users = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detailUser = ref(null)

const actionDialog = ref(false)
const actionType = ref('')
const actionUser = ref(null)
const actionRole = ref('')
const actionReason = ref('')
const actionSubmitting = ref(false)
const actionError = ref('')

const actionTitle = computed(() => ({
  suspend: 'Suspender conta',
  reactivate: 'Reactivar conta',
  role: 'Alterar papel',
}[actionType.value] || 'Confirmar operação'))

const actionDescription = computed(() => {
  if (!actionUser.value) return ''

  if (actionType.value === 'suspend') {
    return `A conta de ${actionUser.value.name} ficará sem acesso à plataforma e os tokens activos serão invalidados.`
  }

  if (actionType.value === 'reactivate') {
    return `A conta de ${actionUser.value.name} voltará a poder iniciar sessão.`
  }

  return `O papel de ${actionUser.value.name} passará a ${getRoleLabel(actionRole.value)}.`
})

const actionConfirmLabel = computed(() => ({
  suspend: 'Confirmar suspensão',
  reactivate: 'Reactivar conta',
  role: 'Confirmar alteração',
}[actionType.value] || 'Confirmar'))

const actionColor = computed(() => ({
  suspend: 'error',
  reactivate: 'success',
  role: 'primary',
}[actionType.value] || 'primary'))

function queryParams() {
  return {
    q: (filters.q || '').trim() || undefined,
    role: filters.role || undefined,
    status: filters.status || undefined,
  }
}

async function load(targetPage = page.value) {
  loading.value = true
  errorMessage.value = ''

  try {
    const result = await fetchAdminUsers({
      ...queryParams(),
      page: targetPage,
    })

    users.value = result.items
    meta.value = result.meta
    page.value = result.meta?.current_page ?? targetPage
  } catch (requestError) {
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os utilizadores.',
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
  filters.role = ''
  filters.status = ''
  page.value = 1
  load(1)
}

function isCurrentUser(user) {
  return Number(authState.user?.id) === Number(user?.id)
}

async function openDetails(user) {
  detailDialog.value = true
  detailLoading.value = true
  detailError.value = ''
  detailUser.value = null

  try {
    detailUser.value = await fetchAdminUser(user.id)
  } catch (requestError) {
    detailError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os detalhes do utilizador.',
    )
  } finally {
    detailLoading.value = false
  }
}

function openStatusAction(user) {
  actionType.value = user.suspended_at ? 'reactivate' : 'suspend'
  actionUser.value = user
  actionRole.value = ''
  actionReason.value = ''
  actionError.value = ''
  actionDialog.value = true
}

function openRoleAction(user) {
  actionType.value = 'role'
  actionUser.value = user
  actionRole.value = user.role === 'admin' ? 'student' : 'admin'
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
  if (actionSubmitting.value || !actionUser.value) return

  actionSubmitting.value = true
  actionError.value = ''
  successMessage.value = ''

  try {
    if (actionType.value === 'suspend') {
      if (actionReason.value.trim().length < 3) {
        actionError.value = 'Indica uma razão com pelo menos 3 caracteres.'
        return
      }

      await suspendAdminUser(actionUser.value.id, actionReason.value.trim())
      successMessage.value = 'Conta suspensa com sucesso.'
    } else if (actionType.value === 'reactivate') {
      await reactivateAdminUser(actionUser.value.id)
      successMessage.value = 'Conta reactivada com sucesso.'
    } else {
      await changeAdminUserRole(actionUser.value.id, actionRole.value)
      successMessage.value = 'Papel actualizado com sucesso.'
    }

    actionDialog.value = false
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
