<template>
  <v-card class="claim-card d-flex flex-column" rounded="xl" elevation="0" height="100%">
    <v-card-item class="pb-2">
      <div class="d-flex align-start justify-space-between ga-3">
        <div class="d-flex align-center ga-3 min-width-0">
          <v-avatar size="46" color="campus-light">
            <v-icon color="primary" size="24">
              {{ claim.item?.type === 'lost' ? 'mdi-magnify' : 'mdi-handshake-outline' }}
            </v-icon>
          </v-avatar>

          <div class="min-width-0">
            <div class="text-caption text-medium-emphasis">
              {{ labels.claimSubject }}
            </div>
            <v-card-title class="px-0 pt-1 claim-card__title">
              {{ claim.item?.title || 'Objecto indisponível' }}
            </v-card-title>
            <v-card-subtitle class="px-0 pb-0">
              {{ claim.item?.category || 'Sem categoria' }}
            </v-card-subtitle>
          </div>
        </div>

        <ClaimStatusChip :value="claim.status" />
      </div>
    </v-card-item>

    <v-card-text class="pt-2 flex-grow-1">
      <div class="d-flex flex-wrap ga-4 text-body-2 text-medium-emphasis mb-4">
        <span class="d-flex align-center ga-1">
          <v-icon size="16">mdi-account-outline</v-icon>
          {{ claim.claimant?.name || 'Utilizador' }}
        </span>
        <span class="d-flex align-center ga-1">
          <v-icon size="16">mdi-clock-outline</v-icon>
          {{ formatDateTime(claim.created_at) }}
        </span>
      </div>

      <p v-if="claim.message" class="claim-card__message mb-4">
        {{ claim.message }}
      </p>
      <p v-else class="text-body-2 text-medium-emphasis mb-4">
        Sem mensagem adicional.
      </p>

      <v-alert
        v-if="showRecoveryNote"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-0"
      >
        {{ recoveryNote }}
      </v-alert>
    </v-card-text>

    <v-card-actions v-if="hasActions" class="px-4 pb-4 pt-0">
      <v-spacer />

      <template v-if="mode === 'received' && claim.status === 'pending'">
        <v-btn
          color="error"
          variant="text"
          rounded="lg"
          :disabled="busy"
          @click="$emit('action', 'reject')"
        >
          Rejeitar
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          :disabled="busy"
          @click="$emit('action', 'accept')"
        >
          {{ labels.accept }}
        </v-btn>
      </template>

      <v-btn
        v-else-if="mode === 'received' && claim.status === 'accepted'"
        color="success"
        variant="flat"
        rounded="lg"
        :disabled="busy"
        @click="$emit('action', 'confirm-return')"
      >
        {{ labels.confirm }}
      </v-btn>

      <v-btn
        v-else-if="mode === 'sent' && claim.status === 'pending'"
        color="error"
        variant="outlined"
        rounded="lg"
        :disabled="busy"
        @click="$emit('action', 'cancel')"
      >
        Cancelar reclamação
      </v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import ClaimStatusChip from '@/components/ClaimStatusChip.vue'
import { getClaimFlowLabels } from '@/utils/claim'
import { formatDateTime } from '@/utils/format'

const props = defineProps({
  claim: {
    type: Object,
    required: true,
  },
  mode: {
    type: String,
    default: 'received',
    validator: (value) => ['received', 'sent'].includes(value),
  },
  busy: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['action'])

const labels = computed(() => getClaimFlowLabels(props.claim.item?.type))

const hasActions = computed(() => (
  (props.mode === 'received' && ['pending', 'accepted'].includes(props.claim.status))
  || (props.mode === 'sent' && props.claim.status === 'pending')
))

const showRecoveryNote = computed(() => (
  props.claim.status === 'accepted'
  || (props.mode === 'sent' && props.claim.status === 'accepted')
))

const recoveryNote = computed(() => {
  if (props.mode === 'sent') {
    return 'A reclamação foi aceite. Aguarda a confirmação final do responsável pelo objecto.'
  }

  return props.claim.item?.type === 'lost'
    ? 'Confirma a recepção apenas depois de recuperares o objecto.'
    : 'Confirma a devolução apenas depois de entregares o objecto ao reclamante.'
})
</script>

<style scoped>
.claim-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}

.claim-card__title {
  color: #0b1f3a;
  white-space: normal;
  line-height: 1.3;
}

.claim-card__message {
  color: #263445;
  line-height: 1.6;
  white-space: pre-line;
}

.min-width-0 {
  min-width: 0;
}
</style>
