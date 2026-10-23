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
            <v-icon size="38" color="primary">mdi-account-plus-outline</v-icon>
          </v-avatar>
          <h1 class="text-h4 font-weight-bold auth-card__title">Criar conta</h1>
          <p class="text-body-2 text-medium-emphasis mt-2 mb-0">
            Regista-te para publicar e gerir os teus objectos no CampusEncontra.
          </p>
        </div>

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
            v-model="form.name"
            label="Nome"
            autocomplete="name"
            prepend-inner-icon="mdi-account-outline"
            variant="outlined"
            density="comfortable"
            :error-messages="fieldErrors.name ? [fieldErrors.name] : []"
            :disabled="submitting"
            @update:model-value="clearFieldError('name')"
          />

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
            autocomplete="new-password"
            prepend-inner-icon="mdi-lock-outline"
            :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
            variant="outlined"
            density="comfortable"
            hint="Mínimo de 8 caracteres."
            persistent-hint
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
            class="mt-4"
            :loading="submitting"
            :disabled="submitting"
          >
            Criar conta
          </v-btn>
        </form>

        <v-divider class="my-6" />

        <div class="d-flex flex-column flex-sm-row align-center justify-space-between ga-2">
          <span class="text-body-2 text-medium-emphasis">
            Já tens conta?
          </span>
          <v-btn
            variant="text"
            color="primary"
            :to="loginLink"
          >
            Entrar
          </v-btn>
        </div>

        <p class="text-caption text-medium-emphasis text-center mt-6 mb-0">
          A conta é criada no backend Laravel. A palavra-passe nunca é guardada
          no navegador.
        </p>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { register } from '@/stores/auth'
import { getApiErrorMessage, getFieldErrors } from '@/utils/errors'
import { getSafeRedirect } from '@/utils/navigation'

const route = useRoute()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
})

const fieldErrors = ref({})
const formError = ref('')
const submitting = ref(false)
const showPassword = ref(false)

const redirectTarget = computed(() => getSafeRedirect(route.query.redirect))

const loginLink = computed(() => (
  redirectTarget.value
    ? { name: 'login', query: { redirect: redirectTarget.value } }
    : { name: 'login' }
))

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
  const name = form.name.trim()
  const email = form.email.trim()

  if (!name) {
    errors.name = 'Indica o teu nome.'
  } else if (name.length > 255) {
    errors.name = 'O nome não pode ter mais de 255 caracteres.'
  }

  if (!email) {
    errors.email = 'Indica o teu email.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.email = 'Indica um email válido.'
  }

  if (!form.password) {
    errors.password = 'Indica uma palavra-passe.'
  } else if (form.password.length < 8) {
    errors.password = 'A palavra-passe deve ter pelo menos 8 caracteres.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submit() {
  formError.value = ''

  if (submitting.value || !validate()) return

  submitting.value = true

  try {
    await register({
      name: form.name.trim(),
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
          'Não foi possível criar a conta. Tenta novamente.',
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
