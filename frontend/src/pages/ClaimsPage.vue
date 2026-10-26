<template>
  <v-container class="py-8 py-md-12">
    <div class="d-flex flex-wrap align-start justify-space-between ga-4 mb-6">
      <div>
        <v-chip
          color="primary"
          variant="tonal"
          prepend-icon="mdi-handshake-outline"
          class="mb-3"
        >
          Área autenticada
        </v-chip>

        <h1 class="text-h4 text-md-h3 font-weight-bold mb-3">
          Reclamações
        </h1>

        <p class="text-body-1 text-medium-emphasis mb-0">
          Acompanha as reclamações que enviaste e as que recebeste nos teus objectos.
        </p>
      </div>

      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-refresh"
        :loading="receivedLoading || sentLoading"
        :disabled="receivedLoading || sentLoading"
        @click="loadAll"
      >
        Actualizar
      </v-btn>
    </div>

    <div class="d-flex flex-wrap ga-2 mb-6" role="group" aria-label="Filtrar reclamações">
      <v-btn
        :variant="activeTab === 'received' ? 'flat' : 'outlined'"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-inbox-arrow-down-outline"
        @click="selectTab('received')"
      >
        Recebidas
        <span v-if="!receivedLoading">
          ({{ receivedMeta?.total ?? receivedClaims.length }})
        </span>
      </v-btn>

      <v-btn
        :variant="activeTab === 'sent' ? 'flat' : 'outlined'"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-send-outline"
        @click="selectTab('sent')"
      >
        Enviadas
        <span v-if="!sentLoading">
          ({{ sentMeta?.total ?? sentClaims.length }})
        </span>
      </v-btn>
    </div>

    <v-alert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-6"
      closable
      @click:close="successMessage = ''"
    >
      {{ successMessage }}
    </v-alert>

    <v-alert
      v-if="activeError"
      type="error"
      variant="tonal"
      prominent
      class="mb-6"
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>{{ activeError }}</span>
        <v-btn color="error" variant="flat" rounded="lg" @click="retryActive">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <div v-if="activeLoading" aria-busy="true" aria-label="A carregar reclamações">
      <v-row>
        <v-col v-for="index in 2" :key="index" cols="12" md="6">
          <v-skeleton-loader type="card" rounded="xl" />
        </v-col>
      </v-row>
    </div>

    <template v-else-if="!activeError">
      <v-row v-if="activeClaims.length">
        <v-col
          v-for="claim in activeClaims"
          :key="claim.id"
          cols="12"
          md="6"
        >
          <ClaimCard
            :claim="claim"
            :mode="activeTab"
            :busy="actionLoading"
            @action="openAction($event, claim)"
            @messages="openMessages(claim)"
          />
        </v-col>
      </v-row>

      <v-pagination
        v-if="activeMeta && activeMeta.last_page > 1"
        :model-value="activeMeta.current_page"
        :length="activeMeta.last_page"
        :total-visible="5"
        rounded="circle"
        class="mt-6"
        @update:model-value="changeActivePage"
      />

      <EmptyState
        v-if="!activeClaims.length"
        :icon="activeTab === 'received' ? 'mdi-inbox-outline' : 'mdi-send-outline'"
        :title="emptyTitle"
        :description="emptyDescription"
      >
        <template #actions>
          <v-btn
            v-if="activeTab === 'received'"
            color="primary"
            rounded="lg"
            :to="{ name: 'my-items' }"
          >
            Os meus objectos
          </v-btn>
          <v-btn
            v-else
            color="primary"
            rounded="lg"
            :to="{ name: 'home' }"
          >
            Ver objectos públicos
          </v-btn>
        </template>
      </EmptyState>
    </template>

    <v-dialog v-model="actionDialog" max-width="540" persistent>
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          {{ actionConfig.title }}
        </v-card-title>

        <v-card-text class="px-6">
          <p class="mb-0">
            {{ actionConfig.text }}
          </p>

          <p v-if="actionTarget?.claim?.item?.title" class="text-body-2 text-medium-emphasis mt-3 mb-0">
            Objecto: <strong>{{ actionTarget.claim.item.title }}</strong>
          </p>

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
            :disabled="actionLoading"
            @click="closeAction"
          >
            Voltar
          </v-btn>

          <v-btn
            :color="actionConfig.color"
            variant="flat"
            rounded="lg"
            :loading="actionLoading"
            :disabled="actionLoading"
            @click="confirmAction"
          >
            {{ actionConfig.confirmLabel }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="messagesDialog" max-width="680" scrollable>
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          Mensagens privadas
        </v-card-title>

        <v-card-subtitle class="px-6 pb-2">
          {{ messagesClaim?.item?.title || 'Reclamação' }}
          · Apenas o reclamante e o responsável pelo objecto podem ver esta conversa.
        </v-card-subtitle>

        <v-divider />

        <v-card-text class="px-6 py-4" style="max-height: 55vh">
          <div v-if="messagesLoading" aria-busy="true" class="py-4">
            <v-skeleton-loader type="list-item-two-line" rounded="lg" class="mb-2" />
            <v-skeleton-loader type="list-item-two-line" rounded="lg" />
          </div>

          <v-alert
            v-else-if="messagesError"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ messagesError }}
          </v-alert>

          <div v-else-if="messages.length === 0" class="text-center py-6">
            <v-icon size="44" color="primary" class="mb-2">mdi-message-outline</v-icon>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Ainda não há mensagens. Combina os detalhes da entrega aqui.
            </p>
          </div>

          <div v-else class="d-flex flex-column ga-3">
            <div
              v-for="message in messages"
              :key="message.id"
              class="message-bubble"
              :class="{ 'message-bubble--mine': isMyMessage(message) }"
            >
              <div class="d-flex justify-space-between ga-3 mb-1">
                <strong class="text-body-2">{{ message.sender?.name || 'Utilizador' }}</strong>
                <span class="text-caption text-medium-emphasis">
                  {{ formatDateTime(message.created_at) }}
                </span>
              </div>
              <p class="text-body-2 mb-0 message-bubble__body">{{ message.body }}</p>
            </div>
          </div>
        </v-card-text>

        <v-divider />

        <v-card-text class="px-6 py-4">
          <v-textarea
            v-model="messageBody"
            label="Nova mensagem"
            placeholder="Escreve uma mensagem para a outra parte."
            rows="3"
            maxlength="2000"
            counter="2000"
            auto-grow
            :disabled="!canPostMessages || messageSending"
            :error-messages="messageFieldError ? [messageFieldError] : []"
          />

          <v-alert
            v-if="messageSendError"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-2"
          >
            {{ messageSendError }}
          </v-alert>

          <p v-if="!canPostMessages" class="text-caption text-medium-emphasis mt-2 mb-0">
            Esta reclamação já está encerrada. O histórico continua disponível para consulta.
          </p>
        </v-card-text>

        <v-card-actions class="px-6 pb-6">
          <v-btn
            variant="text"
            color="primary"
            rounded="lg"
            :disabled="messageSending"
            @click="closeMessages"
          >
            Fechar
          </v-btn>

          <v-spacer />

          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-send-outline"
            :loading="messageSending"
            :disabled="!canPostMessages || messageSending || !messageBody.trim()"
            @click="submitMessage"
          >
            Enviar mensagem
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ClaimCard from '@/components/ClaimCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import {
  acceptClaim,
  cancelClaim,
  confirmClaimReturn,
  fetchClaimMessages,
  fetchMyClaimsPaged,
  fetchReceivedClaimsPaged,
  rejectClaim,
  reopenClaim,
  sendClaimMessage,
} from '@/services/claims'
import { getClaimFlowLabels } from '@/utils/claim'
import { getApiErrorMessage, getFieldErrors } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'
import { authState } from '@/stores/auth'

const PER_PAGE = 8

const route = useRoute()
const router = useRouter()

const activeTab = ref(route.query.tab === 'sent' ? 'sent' : 'received')
const receivedClaims = ref([])
const sentClaims = ref([])
const receivedMeta = ref(null)
const sentMeta = ref(null)
const receivedPage = ref(1)
const sentPage = ref(1)
const receivedLoading = ref(true)
const sentLoading = ref(true)
const receivedError = ref('')
const sentError = ref('')
const successMessage = ref('')

const actionDialog = ref(false)
const actionTarget = ref(null)
const actionLoading = ref(false)
const actionError = ref('')

const messagesDialog = ref(false)
const messagesClaim = ref(null)
const messages = ref([])
const messagesLoading = ref(false)
const messagesError = ref('')
const messageBody = ref('')
const messageSending = ref(false)
const messageSendError = ref('')
const messageFieldError = ref('')

const activeClaims = computed(() => (
  activeTab.value === 'received' ? receivedClaims.value : sentClaims.value
))

const activeMeta = computed(() => (
  activeTab.value === 'received' ? receivedMeta.value : sentMeta.value
))

const canPostMessages = computed(() => (
  ['pending', 'accepted'].includes(messagesClaim.value?.status)
))

const authUserId = computed(() => authState.user?.id)

const activeLoading = computed(() => (
  activeTab.value === 'received' ? receivedLoading.value : sentLoading.value
))

const activeError = computed(() => (
  activeTab.value === 'received' ? receivedError.value : sentError.value
))

const emptyTitle = computed(() => (
  activeTab.value === 'received'
    ? 'Ainda não recebeste reclamações'
    : 'Ainda não enviaste reclamações'
))

const emptyDescription = computed(() => (
  activeTab.value === 'received'
    ? 'Quando alguém reclamar um dos teus objectos, a reclamação aparecerá aqui para poderes responder.'
    : 'Explora os objectos públicos e apresenta uma reclamação quando reconheceres um objecto.'
))

const actionConfig = computed(() => {
  const action = actionTarget.value?.action
  const claim = actionTarget.value?.claim
  const labels = getClaimFlowLabels(claim?.item?.type)
  const isLost = claim?.item?.type === 'lost'

  if (action === 'accept') {
    return {
      title: isLost ? 'Aceitar informação de encontro?' : 'Aceitar reclamação?',
      text: 'A aceitação inicia a recuperação. O objecto só será marcado como devolvido depois da confirmação final.',
      confirmLabel: labels.accept,
      color: 'primary',
    }
  }

  if (action === 'reject') {
    return {
      title: 'Rejeitar reclamação?',
      text: 'A reclamação será encerrada e o objecto continuará disponível para outras reclamações.',
      confirmLabel: 'Rejeitar',
      color: 'error',
    }
  }

  if (action === 'cancel') {
    return {
      title: 'Cancelar reclamação?',
      text: 'A tua reclamação será cancelada e não poderá ser recuperada.',
      confirmLabel: 'Cancelar reclamação',
      color: 'error',
    }
  }

  if (action === 'reopen') {
    return {
      title: 'Reabrir anúncio?',
      text: 'A aceitação será cancelada e o objecto voltará a estar em aberto para novas reclamações.',
      confirmLabel: 'Reabrir anúncio',
      color: 'warning',
    }
  }

  return {
    title: isLost ? 'Confirmar recepção?' : 'Confirmar devolução?',
    text: isLost
      ? 'Confirma apenas se já recuperaste o objecto. Esta acção marca o objecto como devolvido.'
      : 'Confirma apenas se o objecto já foi entregue ao reclamante. Esta acção marca o objecto como devolvido.',
    confirmLabel: labels.confirm,
    color: 'success',
  }
})

function selectTab(tab) {
  activeTab.value = tab
  router.replace({
    name: 'claims',
    query: { ...route.query, tab },
  })
}

async function loadReceived(page = receivedPage.value) {
  receivedLoading.value = true
  receivedError.value = ''

  try {
    const result = await fetchReceivedClaimsPaged({
      page,
      per_page: PER_PAGE,
    })

    receivedClaims.value = result.claims
    receivedMeta.value = result.meta
    receivedPage.value = result.meta?.current_page ?? page
  } catch (requestError) {
    receivedError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as reclamações recebidas. Tenta novamente.',
    )
  } finally {
    receivedLoading.value = false
  }
}

async function loadSent(page = sentPage.value) {
  sentLoading.value = true
  sentError.value = ''

  try {
    const result = await fetchMyClaimsPaged({
      page,
      per_page: PER_PAGE,
    })

    sentClaims.value = result.claims
    sentMeta.value = result.meta
    sentPage.value = result.meta?.current_page ?? page
  } catch (requestError) {
    sentError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as reclamações enviadas. Tenta novamente.',
    )
  } finally {
    sentLoading.value = false
  }
}

async function loadAll() {
  await Promise.all([loadReceived(), loadSent()])
}

function changeActivePage(page) {
  if (activeTab.value === 'received') {
    loadReceived(page)
  } else {
    loadSent(page)
  }
}

function retryActive() {
  if (activeTab.value === 'received') {
    loadReceived()
  } else {
    loadSent()
  }
}

async function openMessages(claim) {
  messagesClaim.value = claim
  messages.value = []
  messagesError.value = ''
  messageBody.value = ''
  messageSendError.value = ''
  messageFieldError.value = ''
  messagesDialog.value = true
  messagesLoading.value = true

  try {
    messages.value = await fetchClaimMessages(claim.id)
  } catch (requestError) {
    messagesError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as mensagens. Tenta novamente.',
    )
  } finally {
    messagesLoading.value = false
  }
}

function closeMessages() {
  if (messageSending.value) return

  messagesDialog.value = false
  messagesClaim.value = null
  messages.value = []
  messageBody.value = ''
  messageSendError.value = ''
  messageFieldError.value = ''
}

function isMyMessage(message) {
  return Number(message.sender?.id) === Number(authUserId.value)
}

async function submitMessage() {
  if (!messagesClaim.value || messageSending.value) return

  const body = messageBody.value.trim()

  messageSendError.value = ''
  messageFieldError.value = ''

  if (!body) {
    messageFieldError.value = 'Escreve uma mensagem.'
    return
  }

  messageSending.value = true

  try {
    const created = await sendClaimMessage(messagesClaim.value.id, body)
    messages.value = [...messages.value, created]
    messageBody.value = ''
  } catch (requestError) {
    const fieldErrors = getFieldErrors(requestError)

    if (fieldErrors.body) {
      messageFieldError.value = fieldErrors.body
    } else {
      messageSendError.value = getApiErrorMessage(
        requestError,
        'Não foi possível enviar a mensagem. Tenta novamente.',
      )
    }
  } finally {
    messageSending.value = false
  }
}

function openAction(action, claim) {
  actionTarget.value = { action, claim }
  actionError.value = ''
  actionDialog.value = true
}

function closeAction() {
  if (actionLoading.value) return

  actionDialog.value = false
  actionTarget.value = null
  actionError.value = ''
}

async function confirmAction() {
  if (!actionTarget.value || actionLoading.value) return

  const { action, claim } = actionTarget.value
  actionLoading.value = true
  actionError.value = ''

  try {
    if (action === 'accept') {
      await acceptClaim(claim.id)
      successMessage.value = 'Reclamação aceite. A recuperação foi iniciada.'
    } else if (action === 'reject') {
      await rejectClaim(claim.id)
      successMessage.value = 'Reclamação rejeitada.'
    } else if (action === 'cancel') {
      await cancelClaim(claim.id)
      successMessage.value = 'Reclamação cancelada.'
    } else if (action === 'reopen') {
      await reopenClaim(claim.id)
      successMessage.value = 'Anúncio reaberto. O objecto volta a aceitar reclamações.'
    } else if (action === 'confirm-return') {
      await confirmClaimReturn(claim.id)
      successMessage.value = 'Devolução confirmada. O objecto foi marcado como devolvido.'
    }

    actionDialog.value = false
    actionTarget.value = null
    receivedPage.value = 1
    sentPage.value = 1
    await loadAll()
  } catch (requestError) {
    actionError.value = getApiErrorMessage(
      requestError,
      'Não foi possível concluir a operação. Tenta novamente.',
    )
  } finally {
    actionLoading.value = false
  }
}

watch(
  () => route.query.tab,
  (tab) => {
    if (tab === 'sent' || tab === 'received') {
      activeTab.value = tab
    }
  },
)

onMounted(loadAll)
</script>

<style scoped>
.message-bubble {
  background: #f4f7fb;
  border: 1px solid rgba(11, 31, 58, 0.06);
  border-radius: 14px;
  padding: 0.75rem 0.9rem;
}

.message-bubble--mine {
  background: #e8f1fa;
  border-color: rgba(22, 78, 128, 0.14);
  margin-left: 2rem;
}

.message-bubble__body {
  white-space: pre-line;
  color: #263445;
}
</style>
