<template>
  <v-container max-width="560" class="py-10 py-md-16">
    <v-btn
      variant="text"
      color="primary"
      prepend-icon="mdi-arrow-left"
      :to="{ name: 'home' }"
      class="mb-4"
    >
      Voltar ao início
    </v-btn>

    <v-card rounded="xl" elevation="0" class="auth-card">
      <v-card-text class="pa-6 pa-md-8">
        <div class="text-center mb-6">
          <v-avatar size="72" color="campus-light" class="mb-4">
            <v-icon size="38" color="primary">mdi-login</v-icon>
          </v-avatar>
          <h1 class="text-h4 font-weight-bold auth-card__title">Entrar</h1>
          <p class="text-body-2 text-medium-emphasis mt-2 mb-0">
            Inicia sessão para publicar e gerir os teus objectos.
          </p>
        </div>

        <v-alert
          v-if="statusMessage"
          :type="statusMessage.type"
          variant="tonal"
          density="compact"
          class="mb-5 text-left"
        >
          {{ statusMessage.text }}
        </v-alert>

        <v-alert
          v-if="formError"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-5 text-left"
        >
          {{ formError }}
        </v-alert>

        <form novalidate @submit.prevent="submit">
          <v-text-field
            v-model="form.email"
            label="Email"
            type="email"
            autocomplete="email"
            prepend-inner-icon="mdi-email-outline"
            variant="outlined"
            density="comfortable"
            :error-messages="fieldErrors.email ? [fieldErrors.email] : []"
            :disabled="submitting"
            @update:model-value="clearFieldError('email')"
          />

          <v-text-field
            v-model="form.password"
            label="Palavra-passe"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            prepend-inner-icon="mdi-lock-outline"
            :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
            variant="outlined"
            density="comfortable"
            :error-messages="fieldErrors.password ? [fieldErrors.password] : []"
            :disabled="submitting"
            @click:append-inner="showPassword = !showPassword"
            @update:model-value="clearFieldError('password')"
          />

          <v-btn
            type="submit"
            color="primary"
            size="large"
            rounded="lg"
            block
            class="mt-2"
            :loading="submitting"
            :disabled="submitting"
          >
            Entrar
          </v-btn>
        </form>

        <v-divider class="my-6" />

        <div class="d-flex flex-column flex-sm-row align-center justify-space-between ga-2">
          <span class="text-body-2 text-medium-emphasis">
            Ainda não tens conta?
          </span>
          <v-btn
            variant="text"
            color="primary"
            :to="registerLink"
          >
            Criar conta
          </v-btn>
        </div>

        <p class="text-caption text-medium-emphasis text-center mt-6 mb-0">
          O token da sessão fica guardado apenas no sessionStorage deste separador.
          Não guardamos palavras-passe no navegador.
        </p>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login } from '@/stores/auth'
import { getApiErrorMessage, getFieldErrors } from '@/utils/errors'
import { getSafeRedirect } from '@/utils/navigation'

const route = useRoute()
const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

const fieldErrors = ref({})
const formError = ref('')
const submitting = ref(false)
const showPassword = ref(false)

const redirectTarget = computed(() => getSafeRedirect(route.query.redirect))

const registerLink = computed(() => (
  redirectTarget.value
    ? { name: 'register', query: { redirect: redirectTarget.value } }
    : { name: 'register' }
))

const statusMessage = computed(() => {
  if (route.query.expired === '1') {
    return {
      type: 'warning',
      text: 'A tua sessão expirou. Inicia sessão novamente.',
    }
  }

  if (route.query.loggedOut === '1') {
    return {
      type: 'success',
      text: 'Sessão terminada com sucesso.',
    }
  }

  if (route.query.logoutError === '1') {
    return {
      type: 'warning',
      text: 'A sessão local foi terminada, mas não foi possível confirmar a revogação no servidor. O token pode continuar válido até ser revogado.',
    }
  }

  return null
})

function clearFieldError(field) {
  if (fieldErrors.value[field]) {
    const nextErrors = { ...fieldErrors.value }
    delete nextErrors[field]
    fieldErrors.value = nextErrors
  }

  formError.value = ''
}

function validate() {
  const errors = {}
  const email = form.email.trim()

  if (!email) {
    errors.email = 'Indica o teu email.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.email = 'Indica um email válido.'
  }

  if (!form.password) {
    errors.password = 'Indica a tua palavra-passe.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submit() {
  formError.value = ''

  if (submitting.value || !validate()) return

  submitting.value = true

  try {
    await login({
      email: form.email.trim(),
      password: form.password,
    })

    await router.replace(redirectTarget.value || { name: 'my-items' })
  } catch (error) {
    const errors = getFieldErrors(error)
    fieldErrors.value = errors
    formError.value = Object.keys(errors).length
      ? 'Verifica os dados introduzidos e tenta novamente.'
      : getApiErrorMessage(
          error,
          'Não foi possível iniciar sessão. Tenta novamente.',
        )
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.auth-card {
  border: 1px solid rgba(11, 31, 58, 0.08);
}

.auth-card__title {
  color: #0b1f3a;
  letter-spacing: -0.02em;
}
</style>
