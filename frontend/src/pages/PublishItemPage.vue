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
          Publicar objecto
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          Regista um objecto perdido ou encontrado para que a comunidade o possa identificar.
        </p>
      </div>

      <v-chip
        v-if="authState.user"
        color="primary"
        variant="tonal"
        prepend-icon="mdi-account-check-outline"
      >
        A publicar como {{ authState.user.name }}
      </v-chip>
    </div>

    <v-card rounded="xl" elevation="0" class="form-card">
      <v-card-text class="pa-6 pa-md-8">
        <ItemForm
          :submitting="submitting"
          :server-field-errors="fieldErrors"
          :form-error="formError"
          submit-label="Publicar objecto"
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
          </template>
        </ItemForm>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import ItemForm from '@/components/ItemForm.vue'
import { createItem } from '@/services/items'
import { authState } from '@/stores/auth'
import { getApiErrorMessage, getFieldErrors } from '@/utils/errors'

const router = useRouter()

const submitting = ref(false)
const fieldErrors = ref({})
const formError = ref('')

function clearFieldError(field) {
  if (!fieldErrors.value[field]) return

  const next = { ...fieldErrors.value }
  delete next[field]
  fieldErrors.value = next
}

async function submit(payload) {
  submitting.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    await createItem(payload)
    await router.push({ name: 'my-items', query: { created: '1' } })
  } catch (error) {
    const errors = getFieldErrors(error)
    fieldErrors.value = errors

    formError.value = Object.keys(errors).length
      ? 'Verifica os dados introduzidos e tenta novamente.'
      : getApiErrorMessage(
        error,
        'Não foi possível publicar o objecto. Tenta novamente.',
      )
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}
</style>
