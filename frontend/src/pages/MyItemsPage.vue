<template>
  <v-container class="py-8 py-md-12">
    <div class="d-flex flex-wrap align-start justify-space-between ga-4 mb-6">
      <div>
        <v-chip
          color="primary"
          variant="tonal"
          prepend-icon="mdi-bag-personal-outline"
          class="mb-3"
        >
          Área autenticada
        </v-chip>

        <h1 class="text-h4 text-md-h3 font-weight-bold mb-3">
          Os meus objectos
        </h1>

        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ countLabel }}
        </p>
      </div>

      <div class="d-flex flex-wrap ga-2">
        <v-btn
          variant="outlined"
          color="primary"
          rounded="lg"
          prepend-icon="mdi-refresh"
          :loading="loading"
          :disabled="loading"
          @click="loadItems"
        >
          Actualizar
        </v-btn>

        <v-btn
          color="primary"
          rounded="lg"
          prepend-icon="mdi-plus"
          :to="{ name: 'publish' }"
        >
          Publicar objecto
        </v-btn>
      </div>
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
      v-if="error"
      type="error"
      variant="tonal"
      prominent
      class="mb-6"
    >
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
        <span>{{ error }}</span>
        <v-btn color="error" variant="flat" rounded="lg" @click="loadItems">
          Tentar novamente
        </v-btn>
      </div>
    </v-alert>

    <div v-if="loading" aria-busy="true" aria-label="A carregar os teus objectos">
      <v-row>
        <v-col v-for="index in 3" :key="index" cols="12" md="6" lg="4">
          <v-skeleton-loader type="card" rounded="xl" />
        </v-col>
      </v-row>
    </div>

    <template v-else-if="!error">
      <v-row v-if="items.length">
        <v-col
          v-for="item in items"
          :key="item.id"
          cols="12"
          md="6"
          lg="4"
        >
          <MyItemCard :item="item" @delete="askDelete" />
        </v-col>
      </v-row>

      <EmptyState
        v-else
        icon="mdi-bag-personal-outline"
        title="Ainda não publicaste objectos"
        description="Quando publicares um objecto perdido ou encontrado, ele aparecerá aqui para poderes geri-lo."
      >
        <template #actions>
          <v-btn color="primary" rounded="lg" :to="{ name: 'publish' }">
            Publicar objecto
          </v-btn>
          <v-btn color="primary" variant="outlined" rounded="lg" :to="{ name: 'home' }">
            Ver objectos públicos
          </v-btn>
        </template>
      </EmptyState>
    </template>

    <v-dialog v-model="deleteDialog" max-width="520" persistent>
      <v-card rounded="xl">
        <v-card-title class="text-h6 font-weight-bold pt-6 px-6">
          Eliminar objecto?
        </v-card-title>

        <v-card-text class="px-6">
          <p class="mb-0">
            Tens a certeza de que queres eliminar
            <strong>{{ deleteTarget?.title }}</strong>?
            Esta acção não pode ser desfeita.
          </p>

          <v-alert
            v-if="deleteError"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            {{ deleteError }}
          </v-alert>
        </v-card-text>

        <v-card-actions class="pa-6">
          <v-spacer />

          <v-btn
            variant="text"
            color="primary"
            rounded="lg"
            :disabled="deleting"
            @click="closeDelete"
          >
            Cancelar
          </v-btn>

          <v-btn
            color="error"
            variant="flat"
            rounded="lg"
            :loading="deleting"
            :disabled="deleting"
            @click="confirmDelete"
          >
            Eliminar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import MyItemCard from '@/components/MyItemCard.vue'
import { deleteItem, fetchMyItems } from '@/services/items'
import { getApiErrorMessage } from '@/utils/errors'

const route = useRoute()
const router = useRouter()

const items = ref([])
const loading = ref(true)
const error = ref('')
const successMessage = ref('')
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const deleteError = ref('')

const countLabel = computed(() => {
  if (loading.value) return 'A carregar os teus objectos...'
  if (items.value.length === 0) return 'Nenhum objecto publicado'

  return `${items.value.length} ${items.value.length === 1 ? 'objecto' : 'objectos'}`
})

async function loadItems() {
  loading.value = true
  error.value = ''

  try {
    items.value = await fetchMyItems()
  } catch (requestError) {
    error.value = getApiErrorMessage(
      requestError,
      'Não foi possível carregar os teus objectos. Tenta novamente.',
    )
  } finally {
    loading.value = false
  }
}

function askDelete(item) {
  deleteTarget.value = item
  deleteError.value = ''
  deleteDialog.value = true
}

function closeDelete() {
  if (deleting.value) return

  deleteDialog.value = false
  deleteTarget.value = null
  deleteError.value = ''
}

async function confirmDelete() {
  if (!deleteTarget.value || deleting.value) return

  const target = deleteTarget.value

  deleting.value = true
  deleteError.value = ''

  try {
    await deleteItem(target.id)
    items.value = items.value.filter((item) => item.id !== target.id)
    successMessage.value = 'Objecto eliminado com sucesso.'
    deleteDialog.value = false
    deleteTarget.value = null
  } catch (requestError) {
    if (requestError?.response?.status === 404) {
      items.value = items.value.filter((item) => item.id !== target.id)
      successMessage.value = 'O objecto já não existia na lista.'
      deleteDialog.value = false
      deleteTarget.value = null
      return
    }

    deleteError.value = getApiErrorMessage(
      requestError,
      'Não foi possível eliminar o objecto. Tenta novamente.',
    )
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  if (route.query.created === '1') {
    successMessage.value = 'Objecto publicado com sucesso.'
  } else if (route.query.updated === '1') {
    successMessage.value = 'Objecto actualizado com sucesso.'
  }

  if (route.query.created || route.query.updated) {
    router.replace({ name: 'my-items' })
  }

  loadItems()
})
</script>
