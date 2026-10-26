<template>
  <form novalidate @submit.prevent="handleSubmit">
    <v-alert
      v-if="formError"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-5 text-left"
    >
      {{ formError }}
    </v-alert>

    <v-row>
      <v-col cols="12" md="8">
        <v-text-field
          v-model="form.title"
          label="Título"
          placeholder="Ex.: Mochila azul"
          prepend-inner-icon="mdi-format-title"
          :error-messages="messagesFor('title')"
          :disabled="submitting"
          maxlength="255"
          counter="255"
          required
          @update:model-value="clearError('title')"
        />
      </v-col>

      <v-col cols="12" md="4">
        <v-select
          v-model="form.type"
          :items="typeOptions"
          item-title="title"
          item-value="value"
          label="Tipo"
          prepend-inner-icon="mdi-tag-outline"
          :error-messages="messagesFor('type')"
          :disabled="submitting"
          required
          @update:model-value="clearError('type')"
        />
      </v-col>

      <v-col cols="12" md="6">
        <v-combobox
          v-model="form.category"
          :items="categorySuggestions"
          label="Categoria"
          placeholder="Ex.: Material escolar"
          prepend-inner-icon="mdi-shape-outline"
          :error-messages="messagesFor('category')"
          :disabled="submitting"
          :hint="categoryHint"
          persistent-hint
          maxlength="100"
          required
          @update:model-value="clearError('category')"
        />
      </v-col>

      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.location"
          label="Local"
          placeholder="Ex.: Biblioteca"
          prepend-inner-icon="mdi-map-marker-outline"
          :error-messages="messagesFor('location')"
          :disabled="submitting"
          maxlength="255"
          required
          @update:model-value="clearError('location')"
        />
      </v-col>

      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.occurrence_date"
          label="Data da ocorrência"
          type="date"
          prepend-inner-icon="mdi-calendar-blank-outline"
          :error-messages="messagesFor('occurrence_date')"
          :disabled="submitting"
          required
          @update:model-value="clearError('occurrence_date')"
        />
      </v-col>

      <v-col cols="12">
        <v-textarea
          v-model="form.description"
          label="Descrição"
          placeholder="Descreve o objecto e quaisquer detalhes úteis para o identificar."
          prepend-inner-icon="mdi-text-box-outline"
          :error-messages="messagesFor('description')"
          :disabled="submitting"
          rows="4"
          auto-grow
          required
          @update:model-value="clearError('description')"
        />
      </v-col>

      <v-col cols="12">
        <v-file-input
          v-model="photoFile"
          label="Fotografia (opcional)"
          accept="image/jpeg,image/png,image/webp"
          prepend-icon=""
          prepend-inner-icon="mdi-camera-outline"
          variant="outlined"
          density="comfortable"
          bg-color="white"
          :error-messages="messagesFor('photo')"
          :disabled="submitting"
          :hint="`JPEG, PNG ou WebP, até ${photoLimitLabel}.`"
          persistent-hint
          show-size
          @update:model-value="handlePhotoChange"
        />

        <div v-if="photoPreview" class="photo-preview mt-4">
          <v-img
            :src="photoPreview"
            alt="Pré-visualização da fotografia do objecto"
            max-height="260"
            cover
            class="rounded-lg"
          />

          <div class="d-flex justify-end mt-2">
            <v-btn
              variant="text"
              color="error"
              size="small"
              prepend-icon="mdi-delete-outline"
              :disabled="submitting"
              @click="removeSelectedPhoto"
            >
              Remover fotografia
            </v-btn>
          </div>
        </div>
      </v-col>
    </v-row>

    <div class="d-flex flex-wrap ga-3 mt-2">
      <v-btn
        type="submit"
        color="primary"
        size="large"
        rounded="lg"
        :loading="submitting"
        :disabled="submitting"
      >
        {{ submitLabel }}
      </v-btn>

      <slot name="actions" />
    </div>
  </form>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { loadPublicSettings, publicSettings } from '@/stores/settings'

const props = defineProps({
  initialItem: {
    type: Object,
    default: null,
  },
  submitting: {
    type: Boolean,
    default: false,
  },
  serverFieldErrors: {
    type: Object,
    default: () => ({}),
  },
  formError: {
    type: String,
    default: '',
  },
  submitLabel: {
    type: String,
    default: 'Guardar objecto',
  },
})

const emit = defineEmits(['submit', 'field-change'])

const typeOptions = [
  { title: 'Perdido', value: 'lost' },
  { title: 'Encontrado', value: 'found' },
]

const DEFAULT_CATEGORIES = [
  'Electrónica',
  'Documentos',
  'Roupa',
  'Chaves',
  'Material escolar',
  'Outros',
]

const categorySuggestions = computed(() => {
  const configured = publicSettings.settings?.categories

  return Array.isArray(configured) && configured.length
    ? configured
    : DEFAULT_CATEGORIES
})

const maxPhotoKb = computed(() => {
  const configured = Number(publicSettings.settings?.max_photo_kb)

  return Number.isFinite(configured) && configured >= 512 ? configured : 5120
})

const maxPhotoBytes = computed(() => maxPhotoKb.value * 1024)

const photoLimitLabel = computed(() => (
  maxPhotoKb.value % 1024 === 0
    ? `${maxPhotoKb.value / 1024} MB`
    : `${maxPhotoKb.value} KB`
))

const form = reactive({
  title: '',
  category: '',
  description: '',
  location: '',
  occurrence_date: '',
  type: '',
})

const categoryHint = computed(() => (
  categorySuggestions.value.includes(String(form.category ?? '').trim())
    ? 'Categoria sugerida pela plataforma.'
    : 'Selecciona uma categoria sugerida ou escreve uma nova.'
))

const localErrors = ref({})
const photoFile = ref(null)
const photoPreview = ref('')
const removePhoto = ref(false)

const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const ALLOWED_PHOTO_EXTENSIONS = /\.(jpe?g|png|webp)$/i

let previewObjectUrl = null

function revokePreviewUrl() {
  if (previewObjectUrl) {
    URL.revokeObjectURL(previewObjectUrl)
    previewObjectUrl = null
  }
}

function isValidPhoto(file) {
  if (!file) return true

  const typeAllowed = ALLOWED_PHOTO_TYPES.includes(file.type)
    || (!file.type && ALLOWED_PHOTO_EXTENSIONS.test(file.name))

  return typeAllowed && file.size <= maxPhotoBytes.value
}

function resetPhotoState(item) {
  revokePreviewUrl()
  photoFile.value = null
  removePhoto.value = false
  photoPreview.value = item?.photo_url || ''
}

function handlePhotoChange(value) {
  revokePreviewUrl()

  const file = Array.isArray(value) ? value[0] : value
  photoFile.value = file ?? null
  removePhoto.value = false

  if (!file) {
    photoPreview.value = props.initialItem?.photo_url || ''
    clearError('photo')
    return
  }

  if (!isValidPhoto(file)) {
    localErrors.value = {
      ...localErrors.value,
      photo: `Selecciona uma imagem JPEG, PNG ou WebP com até ${photoLimitLabel.value}.`,
    }
    photoFile.value = null
    photoPreview.value = props.initialItem?.photo_url || ''
    return
  }

  clearError('photo')
  previewObjectUrl = URL.createObjectURL(file)
  photoPreview.value = previewObjectUrl
}

function removeSelectedPhoto() {
  revokePreviewUrl()
  photoFile.value = null
  photoPreview.value = ''
  removePhoto.value = Boolean(props.initialItem?.photo_url)
  clearError('photo')
}

onBeforeUnmount(revokePreviewUrl)

onMounted(() => {
  void loadPublicSettings()
})

function toDateInput(value) {
  if (!value) return ''

  const text = String(value)

  return /^\d{4}-\d{2}-\d{2}/.test(text) ? text.slice(0, 10) : ''
}

function isValidDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false

  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day))

  return (
    date.getUTCFullYear() === year
    && date.getUTCMonth() === month - 1
    && date.getUTCDate() === day
  )
}

function hydrate(item) {
  form.title = item?.title ?? ''
  form.category = item?.category ?? ''
  form.description = item?.description ?? ''
  form.location = item?.location ?? ''
  form.occurrence_date = toDateInput(item?.occurrence_date)
  form.type = item?.type ?? ''
  resetPhotoState(item)
  localErrors.value = {}
}

watch(() => props.initialItem, hydrate, { immediate: true })

function messagesFor(field) {
  const local = localErrors.value[field]

  if (local) return [local]

  const server = props.serverFieldErrors?.[field]

  if (!server) return []

  return Array.isArray(server) ? server : [server]
}

function clearError(field) {
  if (!localErrors.value[field]) {
    emit('field-change', field)
    return
  }

  const next = { ...localErrors.value }
  delete next[field]
  localErrors.value = next
  emit('field-change', field)
}

function validate() {
  const errors = {}
  const title = form.title.trim()
  const category = String(form.category ?? '').trim()
  const description = form.description.trim()
  const location = form.location.trim()

  if (!title) {
    errors.title = 'Indica o título do objecto.'
  } else if (title.length > 255) {
    errors.title = 'O título não pode ter mais de 255 caracteres.'
  }

  if (!category) {
    errors.category = 'Indica a categoria do objecto.'
  } else if (category.length > 100) {
    errors.category = 'A categoria não pode ter mais de 100 caracteres.'
  }

  if (!description) {
    errors.description = 'Indica uma descrição do objecto.'
  }

  if (!location) {
    errors.location = 'Indica o local onde o objecto foi perdido ou encontrado.'
  } else if (location.length > 255) {
    errors.location = 'O local não pode ter mais de 255 caracteres.'
  }

  if (!form.occurrence_date) {
    errors.occurrence_date = 'Indica a data da ocorrência.'
  } else if (!isValidDate(form.occurrence_date)) {
    errors.occurrence_date = 'Indica uma data de ocorrência válida.'
  }

  if (!form.type) {
    errors.type = 'Indica se o objecto foi perdido ou encontrado.'
  } else if (!['lost', 'found'].includes(form.type)) {
    errors.type = 'Selecciona um tipo válido.'
  }

  if (photoFile.value && !isValidPhoto(photoFile.value)) {
    errors.photo = `Selecciona uma imagem JPEG, PNG ou WebP com até ${photoLimitLabel.value}.`
  }

  localErrors.value = errors

  return Object.keys(errors).length === 0
}

function handleSubmit() {
  if (props.submitting) return
  if (!validate()) return

  const payload = {
    title: form.title.trim(),
    category: String(form.category ?? '').trim(),
    description: form.description.trim(),
    location: form.location.trim(),
    occurrence_date: form.occurrence_date,
    type: form.type,
  }

  if (photoFile.value) {
    payload.photo = photoFile.value
  }

  if (removePhoto.value) {
    payload.removePhoto = true
  }

  emit('submit', payload)
}
</script>
