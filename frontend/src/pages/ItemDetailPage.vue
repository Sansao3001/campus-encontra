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
              <v-avatar size="96" color="campus-light" class="mb-4">
                <v-icon size="52" color="primary">{{ typeConfig.icon }}</v-icon>
              </v-avatar>

              <h2 class="text-h6 font-weight-bold mb-2">{{ typeConfig.label }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-0">
                A fotografia será suportada numa fase posterior.
              </p>
            </v-card-text>
          </v-card>

          <v-card rounded="xl" elevation="0" class="detail-card mt-4">
            <v-card-text>
              <template v-if="item.type === 'found' && item.status === 'open'">
                <v-btn
                  block
                  color="primary"
                  rounded="lg"
                  disabled
                  prepend-icon="mdi-handshake-outline"
                >
                  Pedir recuperação
                </v-btn>
                <p class="text-caption text-medium-emphasis text-center mt-3 mb-0">
                  O pedido de recuperação será activado na Fase 4.
                </p>
              </template>

              <v-alert
                v-else
                type="info"
                variant="tonal"
                density="compact"
                class="mb-0"
              >
                Este objecto não está disponível para pedido de recuperação.
              </v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import StatusChip from '@/components/StatusChip.vue'
import { fetchItem } from '@/services/items'
import { getApiErrorMessage } from '@/utils/errors'
import { formatDate, formatDateTime } from '@/utils/format'
import { getTypeConfig } from '@/utils/item'

const props = defineProps({
  id: {
    type: [String, Number],
    required: true,
  },
})

const item = ref(null)
const loading = ref(true)
const error = ref('')
const notFound = ref(false)

const typeConfig = computed(() => getTypeConfig(item.value?.type))

async function loadItem() {
  loading.value = true
  error.value = ''
  notFound.value = false
  item.value = null

  try {
    item.value = await fetchItem(props.id)
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

watch(() => props.id, loadItem, { immediate: true })
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
</style>
