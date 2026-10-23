<template>
  <v-container max-width="1000" class="py-8 py-md-12">
    <v-btn
      variant="text"
      color="primary"
      prepend-icon="mdi-arrow-left"
      :to="{ name: 'my-items' }"
      class="mb-4"
    >
      Os meus objectos
    </v-btn>

    <div class="d-flex flex-wrap align-start justify-space-between ga-4 mb-6">
      <div>
        <h1 class="text-h4 text-md-h3 font-weight-bold mb-3">
          Editar objecto
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          Actualiza os dados do objecto. A autoria e o estado continuam a ser controlados pelo servidor.
        </p>
      </div>

      <v-chip
        v-if="authState.user"
        color="primary"
        variant="tonal"
        prepend-icon="mdi-account-check-outline"
      >
        A editar como {{ authState.user.name }}
      </v-chip>
    </div>

    <div v-if="loading" aria-busy="true" aria-label="A carregar objecto para edição">
      <v-skeleton-loader type="card" rounded="xl" class="mb-4" />
      <v-skeleton-loader type="article" rounded="xl" />
    </div>

    <v-alert
      v-else-if="notFound"
      type="warning"
      variant="tonal"
      prominent
      class="mb-4"
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>O objecto que tentaste editar não existe ou já foi eliminado.</span>
        <v-btn color="warning" variant="flat" rounded="lg" :to="{ name: 'my-items' }">
          Os meus objectos
        </v-btn>
      </div>
    </v-alert>

    <v-alert
      v-else-if="forbidden"
      type="error"
      variant="tonal"
      prominent
      class="mb-4"
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>Não tens permissão para editar este objecto.</span>
        <v-btn color="error" variant="flat" rounded="lg" :to="{ name: 'my-items' }">
          Os meus objectos
        </v-btn>
      </div>
    </v-alert>

    <v-alert
      v-else-if="loadError"
      type="error"
      variant="tonal"
      prominent
      class="mb-4"
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>{{ loadError }}</span>
        <v-btn color="error" variant="flat" rounded="lg" @click="loadItem">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <v-card v-else-if="item" rounded="xl" elevation="0" class="form-card">
      <v-card-text class="pa-6 pa-md-8">
        <ItemForm
          :initial-item="item"
          :submitting="submitting"
          :server-field-errors="fieldErrors"
          :form-error="formError"
          submit-label="Guardar alterações"
          @submit="submit"
          @field-change="clearFieldError"
        >
          <template #actions>
            <v-btn
              variant="outlined"
              color="primary"
              size="large"
              rounded="lg"
              :to="{ name: 'my-items' }"
              :disabled="submitting"
            >
              Cancelar
            </v-btn>

            <v-btn
              variant="text"
              color="primary"
              size="large"
              rounded="lg"
              :to="{ name: 'item-detail', params: { id: item.id } }"
              :disabled="submitting"
            >
              Ver detalhes públicos
            </v-btn>
          </template>
        </ItemForm>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ItemForm from '@/components/ItemForm.vue'
import { fetchItem, updateItem } from '@/services/items'
import { authState, refreshSession } from '@/stores/auth'
import { getApiErrorMessage, getFieldErrors } from '@/utils/errors'

const props = defineProps({
  id: {
    type: [String, Number],
    required: true,
  },
})

const router = useRouter()

const item = ref(null)
const loading = ref(true)
const loadError = ref('')
const notFound = ref(false)
const forbidden = ref(false)
const submitting = ref(false)
const fieldErrors = ref({})
const formError = ref('')

function clearFieldError(field) {
  if (!fieldErrors.value[field]) return

  const next = { ...fieldErrors.value }
  delete next[field]
  fieldErrors.value = next
}

async function loadItem() {
  loading.value = true
  loadError.value = ''
  notFound.value = false
  forbidden.value = false
  item.value = null
  fieldErrors.value = {}
  formError.value = ''

  try {
    if (!authState.user) {
      await refreshSession()
    }

    if (!authState.user) {
      loadError.value = authState.sessionError
        || 'Não foi possível confirmar a sessão. Tenta novamente.'
      return
    }

    const fetched = await fetchItem(props.id)

    if (!fetched) {
      notFound.value = true
      return
    }

    if (Number(fetched.user_id) !== Number(authState.user.id)) {
      forbidden.value = true
      return
    }

    item.value = fetched
  } catch (error) {
    if (error?.response?.status === 404) {
      notFound.value = true
    } else {
      loadError.value = getApiErrorMessage(
        error,
        'Não foi possível carregar o objecto. Tenta novamente.',
      )
    }
  } finally {
    loading.value = false
  }
}

async function submit(payload) {
  submitting.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    await updateItem(props.id, payload)
    await router.push({ name: 'my-items', query: { updated: '1' } })
  } catch (error) {
    const status = error?.response?.status

    if (status === 403) {
      forbidden.value = true
      item.value = null
      return
    }

    if (status === 404) {
      notFound.value = true
      item.value = null
      return
    }

    const errors = getFieldErrors(error)
    fieldErrors.value = errors

    formError.value = Object.keys(errors).length
      ? 'Verifica os dados introduzidos e tenta novamente.'
      : getApiErrorMessage(
        error,
        'Não foi possível actualizar o objecto. Tenta novamente.',
      )
  } finally {
    submitting.value = false
  }
}

watch(() => props.id, loadItem, { immediate: true })
</script>

<style scoped>
.form-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}
</style>
