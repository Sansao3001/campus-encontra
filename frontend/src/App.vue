
<template>
  <v-app>
    <v-main class="page-background">
      <v-app-bar color="secondary" elevation="2">
        <v-container class="d-flex align-center">
          <v-icon color="white" size="32">mdi-school</v-icon>

          <span class="text-h6 font-weight-bold text-white ml-2">
            CampusEncontra
          </span>

          <v-spacer />

          <v-btn color="white" variant="text">
            Entrar
          </v-btn>
        </v-container>
      </v-app-bar>

      <v-container class="py-10">
        <div class="text-center mb-8">
          <h1 class="text-h4 text-md-h3 font-weight-bold main-title">
            Encontra o que procuras.
          </h1>

          <p class="text-body-1 text-grey-darken-1 mt-4">
            Consulta os objectos perdidos e encontrados no campus.
          </p>
        </div>

        <v-text-field
          v-model="pesquisa"
          label="Pesquisar objectos..."
          placeholder="Ex.: mochila, telefone, carteira"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          bg-color="white"
          clearable
          class="mb-4"
        />

        <v-row class="mb-6" dense>
          <v-col cols="12" sm="4">
            <v-btn
              block
              :variant="filtro === 'all' ? 'flat' : 'outlined'"
              color="primary"
              @click="filtro = 'all'"
            >
              Todos
            </v-btn>
          </v-col>

          <v-col cols="12" sm="4">
            <v-btn
              block
              :variant="filtro === 'lost' ? 'flat' : 'outlined'"
              color="primary"
              @click="filtro = 'lost'"
            >
              Perdidos
            </v-btn>
          </v-col>

          <v-col cols="12" sm="4">
            <v-btn
              block
              :variant="filtro === 'found' ? 'flat' : 'outlined'"
              color="primary"
              @click="filtro = 'found'"
            >
              Encontrados
            </v-btn>
          </v-col>
        </v-row>

        <div v-if="carregando" class="text-center py-10">
          <v-progress-circular
            indeterminate
            color="primary"
            size="48"
          />
          <p class="mt-4">A carregar objectos...</p>
        </div>

        <v-alert
          v-else-if="erro"
          type="error"
          variant="tonal"
          class="mb-6"
        >
          {{ erro }}
          <v-btn
            class="ml-2"
            variant="text"
            @click="carregarObjectos"
          >
            Tentar novamente
          </v-btn>
        </v-alert>

        <template v-else>
          <h2 class="text-h5 font-weight-bold main-title mb-4">
            Objectos registados ({{ objectosFiltrados.length }})
          </h2>

          <v-row v-if="objectosFiltrados.length > 0">
            <v-col
              v-for="objecto in objectosFiltrados"
              :key="objecto.id"
              cols="12"
              sm="6"
              md="4"
            >
              <v-card
                height="100%"
                rounded="xl"
                elevation="2"
                class="object-card"
              >
                <v-card-item>
                  <div class="d-flex align-center justify-space-between mb-3">
                    <v-icon size="32" color="primary">
                      {{ objecto.type === 'lost'
                        ? 'mdi-magnify'
                        : 'mdi-check-circle-outline' }}
                    </v-icon>

                    <v-chip
                      :color="objecto.type === 'lost' ? 'error' : 'success'"
                      size="small"
                    >
                      {{ objecto.type === 'lost' ? 'Perdido' : 'Encontrado' }}
                    </v-chip>
                  </div>

                  <v-card-title class="px-0 text-wrap">
                    {{ objecto.title }}
                  </v-card-title>

                  <v-card-subtitle class="px-0">
                    {{ objecto.category }}
                  </v-card-subtitle>
                </v-card-item>

                <v-card-text>
                  <p>
                    {{ objecto.description || 'Sem descrição disponível.' }}
                  </p>

                  <div class="d-flex align-center mt-4">
                    <v-icon size="small" color="primary" class="mr-2">
                      mdi-map-marker
                    </v-icon>

                    <span class="text-body-2">
                      {{ objecto.location || 'Local não indicado' }}
                    </span>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-alert v-else type="info" variant="tonal">
            Não foram encontrados objectos para esta pesquisa.
          </v-alert>
        </template>

        <p class="text-center text-caption text-grey mt-10">
          CampusEncontra — A comunidade ajuda a encontrar.
        </p>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from './services/api'

const objectos = ref([])
const pesquisa = ref('')
const filtro = ref('all')
const carregando = ref(true)
const erro = ref('')

const objectosFiltrados = computed(() => {
  const termo = (pesquisa.value || '').trim().toLowerCase()

  return objectos.value.filter((objecto) => {
    const correspondeAoTipo =
      filtro.value === 'all' || objecto.type === filtro.value

    const texto = [
      objecto.title,
      objecto.category,
      objecto.description,
      objecto.location,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return correspondeAoTipo && texto.includes(termo)
  })
})

async function carregarObjectos() {
  carregando.value = true
  erro.value = ''

  try {
    const resposta = await api.get('/items')

    objectos.value = Array.isArray(resposta.data)
      ? resposta.data
      : (resposta.data.data ?? [])
  } catch (error) {
    console.error('Erro ao carregar objectos:', error)

    erro.value =
      'Não foi possível carregar os objectos. Verifica se o Laravel está ligado e se a API está acessível.'
  } finally {
    carregando.value = false
  }
}

onMounted(carregarObjectos)
</script>

<style scoped>
.page-background {
  min-height: 100vh;
  background-color: #F4F7FB;
}

.main-title {
  color: #0B1F3A;
}

.object-card {
  border-top: 3px solid #164E80;
}
</style>
