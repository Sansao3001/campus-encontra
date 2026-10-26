<template>
  <v-container max-width="1000" class="py-8 py-md-12">
    <v-btn
      variant="text"
      color="primary"
      prepend-icon="mdi-arrow-left"
      :to="{ name: 'home' }"
      class="mb-4"
    >
      Voltar aos objectos
    </v-btn>

    <div v-if="loading" aria-busy="true" aria-label="A carregar detalhes do objecto">
      <v-skeleton-loader type="card" rounded="xl" class="mb-4" />
      <v-row>
        <v-col cols="12" md="7">
          <v-skeleton-loader type="article" rounded="xl" />
        </v-col>
        <v-col cols="12" md="5">
          <v-skeleton-loader type="card" rounded="xl" />
        </v-col>
      </v-row>
    </div>

    <v-alert
      v-else-if="notFound"
      type="warning"
      variant="tonal"
      class="mb-4"
      prominent
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>O objecto que tentaste abrir não existe ou já não está disponível.</span>
        <v-btn color="warning" variant="flat" rounded="lg" :to="{ name: 'home' }">
          Ver objectos
        </v-btn>
      </div>
    </v-alert>

    <v-alert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
      prominent
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>{{ error }}</span>
        <v-btn color="error" variant="flat" rounded="lg" @click="loadItem">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <template v-else-if="item">
      <v-card rounded="xl" elevation="0" class="detail-card">
        <v-card-text class="pa-6 pa-md-8">
          <div class="d-flex flex-wrap ga-2 mb-4">
            <StatusChip kind="type" :value="item.type" />
            <StatusChip kind="status" :value="item.status" />
            <v-chip variant="outlined" prepend-icon="mdi-tag-outline">
              {{ item.category }}
            </v-chip>
          </div>

          <h1 class="text-h4 text-md-h3 font-weight-bold detail-title">
            {{ item.title }}
          </h1>

          <p class="text-body-2 text-medium-emphasis mt-2 mb-0">
            Publicado por {{ item.user?.name || 'autor não identificado' }}
          </p>
        </v-card-text>
      </v-card>

      <v-row class="mt-2">
        <v-col cols="12" md="7">
          <v-card rounded="xl" elevation="0" class="detail-card">
            <v-card-title>Descrição</v-card-title>
            <v-card-text class="text-body-1 detail-description">
              {{ item.description || 'Sem descrição disponível.' }}
            </v-card-text>
          </v-card>

          <v-card rounded="xl" elevation="0" class="detail-card mt-4">
            <v-card-title>Detalhes</v-card-title>
            <v-card-text class="pt-0">
              <v-list bg-color="transparent" density="comfortable">
                <v-list-item
                  prepend-icon="mdi-map-marker-outline"
                  title="Local"
                  :subtitle="item.location || 'Não indicado'"
                />
                <v-list-item
                  prepend-icon="mdi-calendar-blank-outline"
                  title="Data da ocorrência"
                  :subtitle="formatDate(item.occurrence_date)"
                />
                <v-list-item
                  prepend-icon="mdi-clock-outline"
                  title="Publicado em"
                  :subtitle="formatDateTime(item.created_at)"
                />
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="5">
          <v-card rounded="xl" elevation="0" class="detail-card">
            <v-card-text class="text-center py-8">
              <v-img
                v-if="item.photo_url"
                :src="item.photo_url"
                :alt="`Fotografia de ${item.title}`"
                max-height="320"
                contain
                class="detail-photo mb-4"
              />

              <v-avatar v-else size="96" color="campus-light" class="mb-4">
                <v-icon size="52" color="primary">{{ typeConfig.icon }}</v-icon>
              </v-avatar>

              <h2 class="text-h6 font-weight-bold mb-2">{{ typeConfig.label }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ item.photo_url ? 'Fotografia enviada pelo autor.' : 'Este anúncio não tem fotografia.' }}
              </p>
            </v-card-text>
          </v-card>

          <v-card rounded="xl" elevation="0" class="detail-card mt-4">
            <v-card-text>
              <v-alert
                v-if="claimSuccess"
                type="success"
                variant="tonal"
                density="compact"
                class="mb-4"
              >
                {{ claimSuccess }}
              </v-alert>

              <template v-if="isOwner">
                <v-alert
                  v-if="item.status === 'pending'"
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-4"
                >
                  A recuperação deste objecto está em curso. Confirma a devolução apenas depois da entrega.
                </v-alert>

                <v-alert
                  v-else-if="item.status === 'returned'"
                  type="success"
                  variant="tonal"
                  density="compact"
                  class="mb-4"
                >
                  Este objecto foi marcado como devolvido.
                </v-alert>

                <v-btn
                  block
                  color="primary"
                  rounded="lg"
                  prepend-icon="mdi-inbox-arrow-down-outline"
                  :to="{ name: 'claims', query: { tab: 'received' } }"
                >
                  Gerir reclamações recebidas
                </v-btn>
                <p class="text-caption text-medium-emphasis text-center mt-3 mb-0">
                  Aceita ou rejeita reclamações e confirma a devolução no final.
                </p>
              </template>

              <template v-else-if="item.status === 'open'">
                <template v-if="existingClaim">
                  <v-alert
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                  >
                    {{ existingClaimMessage }}
                  </v-alert>
                  <v-btn
                    block
                    color="primary"
                    variant="outlined"
                    rounded="lg"
                    prepend-icon="mdi-send-outline"
                    :to="{ name: 'claims', query: { tab: 'sent' } }"
                  >
                    Ver a minha reclamação
                  </v-btn>
                </template>

                <template v-else-if="authState.user">
                  <v-btn
                    block
                    color="primary"
                    rounded="lg"
                    prepend-icon="mdi-handshake-outline"
                    @click="openClaimDialog"
                  >
                    {{ claimLabels.claimCta }}
                  </v-btn>
                  <p class="text-caption text-medium-emphasis text-center mt-3 mb-0">
                    {{ claimHint }}
                  </p>
                </template>

                <template v-else>
                  <p class="text-body-2 text-medium-emphasis text-center mb-4">
                    Entra na tua conta para reclamar este objecto.
                  </p>
                  <v-btn
                    block
                    color="primary"
                    rounded="lg"
                    prepend-icon="mdi-login"
                    :to="{ name: 'login', query: { redirect: route.fullPath } }"
                  >
                    Entrar para reclamar
                  </v-btn>
                </template>
              </template>

              <v-alert
                v-else-if="item.status === 'pending'"
                type="info"
                variant="tonal"
                density="compact"
                class="mb-0"
              >
                A recuperação deste objecto está em curso. Já não aceita novas reclamações.
              </v-alert>

              <v-alert
                v-else
                type="success"
                variant="tonal"
                density="compact"
                class="mb-0"
              >
                Este objecto já foi devolvido.
              </v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <v-dialog v-model="claimDialog" max-width="560" persistent>
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          {{ claimLabels.claimTitle }}
        </v-card-title>

        <v-card-text class="px-6">
          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ claimDialogIntro }}
          </p>

          <v-textarea
            v-model="claimMessage"
            label="Mensagem (opcional)"
            placeholder="Descreve os detalhes que ajudam o responsável a confirmar a reclamação."
            prepend-inner-icon="mdi-message-text-outline"
            rows="4"
            maxlength="1000"
            counter="1000"
            auto-grow
            :disabled="claimSubmitting"
          />

          <v-alert
            v-if="claimError"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            {{ claimError }}
          </v-alert>
        </v-card-text>

        <v-card-actions class="pa-6">
          <v-spacer />

          <v-btn
            variant="text"
            color="primary"
            rounded="lg"
            :disabled="claimSubmitting"
            @click="closeClaimDialog"
          >
            Cancelar
          </v-btn>

          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            :loading="claimSubmitting"
            :disabled="claimSubmitting"
            @click="submitClaim"
          >
            Enviar reclamação
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import StatusChip from '@/components/StatusChip.vue'
import { createClaim, fetchMyClaims } from '@/services/claims'
import { fetchItem } from '@/services/items'
import { authState } from '@/stores/auth'
import { getClaimFlowLabels } from '@/utils/claim'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDate, formatDateTime } from '@/utils/format'
import { getTypeConfig } from '@/utils/item'

const props = defineProps({
  id: {
    type: [String, Number],
    required: true,
  },
})

const route = useRoute()

const item = ref(null)
const loading = ref(true)
const error = ref('')
const notFound = ref(false)
const existingClaim = ref(null)
const claimSuccess = ref('')

const claimDialog = ref(false)
const claimMessage = ref('')
const claimSubmitting = ref(false)
const claimError = ref('')

const typeConfig = computed(() => getTypeConfig(item.value?.type))
const claimLabels = computed(() => getClaimFlowLabels(item.value?.type))
const isOwner = computed(() => (
  Boolean(
    authState.user
    && item.value
    && Number(authState.user.id) === Number(item.value.user_id),
  )
))

const claimHint = computed(() => (
  item.value?.type === 'lost'
    ? 'A informação será enviada ao responsável. A recuperação só é concluída depois de aceite e confirmada.'
    : 'A reclamação será enviada ao responsável. A devolução só é concluída depois de aceite e confirmada.'
))

const claimDialogIntro = computed(() => (
  item.value?.type === 'lost'
    ? 'Indica onde e como encontraste o objecto. O responsável poderá aceitar ou rejeitar a informação.'
    : 'Descreve porque este objecto é teu. O responsável poderá aceitar ou rejeitar a reclamação.'
))

const existingClaimMessage = computed(() => {
  if (!existingClaim.value) return ''

  return existingClaim.value.status === 'accepted'
    ? 'A tua reclamação foi aceite. A recuperação está em curso.'
    : 'Já tens uma reclamação pendente para este objecto. Aguarda a resposta do responsável.'
})

async function loadExistingClaim() {
  existingClaim.value = null

  if (!item.value || !authState.user || isOwner.value) return

  try {
    const claims = await fetchMyClaims()
    existingClaim.value = claims.find((claim) => (
      Number(claim.item_id) === Number(item.value.id)
      && ['pending', 'accepted'].includes(claim.status)
    )) ?? null
  } catch {
    existingClaim.value = null
  }
}

async function loadItem() {
  loading.value = true
  error.value = ''
  notFound.value = false
  item.value = null
  existingClaim.value = null
  claimSuccess.value = ''

  try {
    item.value = await fetchItem(props.id)
    await loadExistingClaim()
  } catch (requestError) {
    if (requestError?.response?.status === 404) {
      notFound.value = true
    } else {
      error.value = getApiErrorMessage(
        requestError,
        'Não foi possível carregar este objecto. Tenta novamente.',
      )
    }
  } finally {
    loading.value = false
  }
}

function openClaimDialog() {
  claimMessage.value = ''
  claimError.value = ''
  claimDialog.value = true
}

function closeClaimDialog() {
  if (claimSubmitting.value) return

  claimDialog.value = false
  claimError.value = ''
}

async function submitClaim() {
  if (claimSubmitting.value) return

  claimSubmitting.value = true
  claimError.value = ''

  try {
    const created = await createClaim(props.id, {
      message: claimMessage.value.trim() || null,
    })

    existingClaim.value = created
    claimSuccess.value = `${claimLabels.value.claimSubject} enviada com sucesso.`
    claimDialog.value = false
    claimMessage.value = ''
  } catch (requestError) {
    claimError.value = getApiErrorMessage(
      requestError,
      'Não foi possível enviar a reclamação. Tenta novamente.',
    )
  } finally {
    claimSubmitting.value = false
  }
}

watch(() => props.id, loadItem, { immediate: true })

watch(
  () => authState.user?.id,
  () => {
    if (item.value) {
      loadExistingClaim()
    }
  },
)
</script>

<style scoped>
.detail-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}

.detail-title {
  color: #0b1f3a;
  letter-spacing: -0.02em;
}

.detail-description {
  color: #263445;
  line-height: 1.7;
  white-space: pre-line;
}

.detail-photo {
  border-radius: 14px;
  background: #f4f7fb;
}
</style>
