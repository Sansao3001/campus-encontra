<template>
  <section aria-labelledby="admin-settings-title">
    <div class="admin-panel-header">
      <div>
        <h2 id="admin-settings-title" class="admin-panel-header__title">
          Configurações
        </h2>
        <p class="admin-panel-header__description">
          Preferências persistidas e validadas no servidor. Segredos e credenciais permanecem fora desta área.
        </p>
      </div>

      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-refresh"
        :loading="loading"
        :disabled="loading || saving"
        @click="load"
      >
        Recarregar
      </v-btn>
    </div>

    <v-alert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ errorMessage }}
    </v-alert>

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

    <div v-if="loading" aria-busy="true" aria-label="A carregar configurações">
      <v-skeleton-loader
        v-for="index in 3"
        :key="index"
        type="article"
        rounded="xl"
        class="mb-4"
      />
    </div>

    <template v-else-if="payload">
      <v-card
        v-for="group in payload.groups"
        :key="group.key"
        rounded="xl"
        elevation="0"
        class="admin-card mb-4"
      >
        <v-card-title class="admin-card__title">
          {{ group.label }}
        </v-card-title>
        <v-card-subtitle class="admin-card__subtitle">
          {{ group.description }}
        </v-card-subtitle>

        <v-card-text>
          <div v-if="group.key === 'notifications' || group.key === 'security'" class="admin-readonly-list">
            <div
              v-for="setting in group.settings"
              :key="setting.key"
              class="admin-readonly-row"
            >
              <div class="min-width-0">
                <p class="admin-readonly-row__label">{{ setting.label }}</p>
                <p class="admin-readonly-row__description">{{ setting.description }}</p>
              </div>

              <v-chip
                v-if="setting.type === 'boolean'"
                :color="setting.value ? 'success' : 'secondary'"
                variant="tonal"
                size="small"
              >
                {{ setting.value ? 'Activo' : 'Inactivo' }}
              </v-chip>
              <span v-else class="admin-readonly-row__value">
                {{ setting.value === null || setting.value === '' ? '—' : setting.value }}
              </span>
            </div>
          </div>

          <div v-else class="admin-settings-grid">
            <div
              v-for="setting in group.settings"
              :key="setting.key"
              class="admin-settings-field"
            >
              <v-select
                v-if="setting.type === 'select'"
                v-model="form[setting.key]"
                :items="setting.options ?? []"
                item-title="title"
                item-value="value"
                :label="setting.label"
                variant="outlined"
                density="comfortable"
                :hint="setting.description"
                persistent-hint
                :error-messages="settingErrors(setting.key)"
                :disabled="saving"
                @update:model-value="clearError(setting.key)"
              />

              <v-combobox
                v-else-if="setting.type === 'list'"
                v-model="form[setting.key]"
                :items="categorySuggestions"
                :label="setting.label"
                variant="outlined"
                density="comfortable"
                multiple
                chips
                closable-chips
                :hint="setting.description"
                persistent-hint
                :error-messages="settingErrors(setting.key)"
                :disabled="saving"
                @update:model-value="clearError(setting.key)"
              />

              <v-text-field
                v-else
                v-model="form[setting.key]"
                :label="setting.label"
                :type="setting.type === 'integer' ? 'number' : 'text'"
                :min="setting.type === 'integer' ? 512 : undefined"
                :max="setting.type === 'integer' ? 10240 : undefined"
                :suffix="setting.type === 'integer' ? 'KB' : undefined"
                variant="outlined"
                density="comfortable"
                :hint="setting.description"
                persistent-hint
                :error-messages="settingErrors(setting.key)"
                :disabled="saving"
                @update:model-value="clearError(setting.key)"
              />
            </div>
          </div>
        </v-card-text>
      </v-card>

      <div class="admin-settings-note">
        <v-icon size="20" color="primary">mdi-lock-outline</v-icon>
        <p>
          Tokens, palavras-passe, chaves de aplicação e credenciais de email nunca são lidos nem editados por esta interface.
          O estado das notificações e das protecções é apenas informativo.
        </p>
      </div>

      <v-card rounded="xl" elevation="0" class="admin-card admin-save-bar">
        <v-card-text class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
          <div>
            <p class="admin-save-bar__title">
              {{ dirty ? 'Existem alterações por guardar.' : 'Todas as alterações estão guardadas.' }}
            </p>
            <p class="admin-save-bar__meta">
              <template v-if="payload.updated_at">
                Última actualização: {{ formatDateTime(payload.updated_at) }}
              </template>
              <template v-else>
                Ainda não existem valores personalizados guardados.
              </template>
            </p>
          </div>

          <div class="d-flex flex-wrap ga-2">
            <v-btn
              variant="text"
              color="primary"
              rounded="lg"
              :disabled="!dirty || saving"
              @click="restoreBaseline"
            >
              Descartar
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-content-save-outline"
              :loading="saving"
              :disabled="!dirty || saving"
              @click="save"
            >
              Guardar alterações
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { fetchAdminSettings, updateAdminSettings } from '@/services/admin'
import { getApiErrorMessage, getFieldErrors } from '@/utils/errors'
import { formatDateTime } from '@/utils/format'
import { refreshPublicSettings } from '@/stores/settings'

const payload = ref(null)
const form = reactive({})
const baseline = ref({})
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref({})

const dirty = computed(() => (
  JSON.stringify(form) !== JSON.stringify(baseline.value)
))

const categorySuggestions = computed(() => {
  const values = new Set([
    'Electrónica',
    'Documentos',
    'Roupa',
    'Chaves',
    'Material escolar',
    'Outros',
  ])

  for (const category of form['operations.categories'] ?? []) {
    if (category) values.add(category)
  }

  return [...values]
})

function clearForm() {
  for (const key of Object.keys(form)) {
    delete form[key]
  }
}

function hydrate(nextPayload) {
  payload.value = nextPayload
  clearForm()
  const next = {}

  for (const group of nextPayload?.groups ?? []) {
    for (const setting of group.settings ?? []) {
      if (setting.read_only) continue

      next[setting.key] = Array.isArray(setting.value)
        ? [...setting.value]
        : setting.value
    }
  }

  Object.assign(form, next)
  baseline.value = JSON.parse(JSON.stringify(next))
}

function settingErrors(key) {
  return fieldErrors.value[key] ? [fieldErrors.value[key]] : []
}

function clearError(key) {
  if (!fieldErrors.value[key]) return

  const next = { ...fieldErrors.value }
  delete next[key]
  fieldErrors.value = next
}

function restoreBaseline() {
  clearForm()
  Object.assign(form, JSON.parse(JSON.stringify(baseline.value)))
  fieldErrors.value = {}
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    hydrate(await fetchAdminSettings())
  } catch (requestError) {
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar as configurações.',
    )
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!dirty.value || saving.value) return

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  fieldErrors.value = {}

  try {
    const result = await updateAdminSettings({ ...form })
    hydrate(result)
    successMessage.value = result?.message || 'Configurações guardadas com sucesso.'
    void refreshPublicSettings()
  } catch (requestError) {
    fieldErrors.value = getFieldErrors(requestError)
    errorMessage.value = getApiErrorMessage(
      requestError,
      'Não foi possível guardar as configurações.',
    )
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
