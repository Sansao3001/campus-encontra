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
        <span v-if="!receivedLoading">({{ receivedClaims.length }})</span>
      </v-btn>

      <v-btn
        :variant="activeTab === 'sent' ? 'flat' : 'outlined'"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-send-outline"
        @click="selectTab('sent')"
      >
        Enviadas
        <span v-if="!sentLoading">({{ sentClaims.length }})</span>
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
          />
        </v-col>
      </v-row>

      <EmptyState
        v-else
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
  fetchMyClaims,
  fetchReceivedClaims,
  rejectClaim,
} from '@/services/claims'
import { getClaimFlowLabels } from '@/utils/claim'
import { getApiErrorMessage } from '@/utils/errors'

const route = useRoute()
const router = useRouter()

const activeTab = ref(route.query.tab === 'sent' ? 'sent' : 'received')
const receivedClaims = ref([])
const sentClaims = ref([])
const receivedLoading = ref(true)
const sentLoading = ref(true)
const receivedError = ref('')
const sentError = ref('')
const successMessage = ref('')

const actionDialog = ref(false)
const actionTarget = ref(null)
const actionLoading = ref(false)
const actionError = ref('')

const activeClaims = computed(() => (
  activeTab.value === 'received' ? receivedClaims.value : sentClaims.value
))

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

async function loadReceived() {
  receivedLoading.value = true
  receivedError.value = ''

  try {
    receivedClaims.value = await fetchReceivedClaims()
  } catch (requestError) {
    receivedError.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as reclamações recebidas. Tenta novamente.',
    )
  } finally {
    receivedLoading.value = false
  }
}

async function loadSent() {
  sentLoading.value = true
  sentError.value = ''

  try {
    sentClaims.value = await fetchMyClaims()
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

function retryActive() {
  if (activeTab.value === 'received') {
    loadReceived()
  } else {
    loadSent()
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
    } else if (action === 'confirm-return') {
      await confirmClaimReturn(claim.id)
      successMessage.value = 'Devolução confirmada. O objecto foi marcado como devolvido.'
    }

    actionDialog.value = false
    actionTarget.value = null
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
