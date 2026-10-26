<template>
  <v-container class="py-8 py-md-12">
    <div class="d-flex flex-wrap align-start justify-space-between ga-4 mb-6">
      <div>
        <v-chip
          color="primary"
          variant="tonal"
          prepend-icon="mdi-shield-account-outline"
          class="mb-3"
        >
          Área administrativa
        </v-chip>

        <h1 class="text-h4 text-md-h3 font-weight-bold mb-3">
          Administração
        </h1>

        <p class="text-body-1 text-medium-emphasis mb-0">
          Gere utilizadores, anúncios, solicitações e denúncias do CampusEncontra.
        </p>
      </div>

      <v-btn
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-refresh"
        @click="refreshPanel"
      >
        Actualizar
      </v-btn>
    </div>

    <div class="d-flex flex-wrap ga-2 mb-6" role="navigation" aria-label="Secções administrativas">
      <v-btn
        v-for="item in sections"
        :key="item.value"
        :to="{ name: 'admin', params: { section: item.value } }"
        :variant="activeSection === item.value ? 'flat' : 'outlined'"
        :color="activeSection === item.value ? 'primary' : 'primary'"
        rounded="lg"
        :prepend-icon="item.icon"
      >
        {{ item.label }}
      </v-btn>
    </div>

    <component
      :is="activeComponent"
      :key="`${activeSection}-${refreshKey}`"
    />
  </v-container>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminClaimsPanel from '@/components/admin/AdminClaimsPanel.vue'
import AdminDashboardPanel from '@/components/admin/AdminDashboardPanel.vue'
import AdminItemsPanel from '@/components/admin/AdminItemsPanel.vue'
import AdminReportsPanel from '@/components/admin/AdminReportsPanel.vue'
import AdminUsersPanel from '@/components/admin/AdminUsersPanel.vue'

const sections = [
  { value: 'dashboard', label: 'Dashboard', icon: 'mdi-view-dashboard-outline' },
  { value: 'utilizadores', label: 'Utilizadores', icon: 'mdi-account-group-outline' },
  { value: 'anuncios', label: 'Anúncios', icon: 'mdi-bag-personal-outline' },
  { value: 'solicitacoes', label: 'Solicitações', icon: 'mdi-handshake-outline' },
  { value: 'denuncias', label: 'Denúncias', icon: 'mdi-flag-outline' },
]

const components = {
  dashboard: AdminDashboardPanel,
  utilizadores: AdminUsersPanel,
  anuncios: AdminItemsPanel,
  solicitacoes: AdminClaimsPanel,
  denuncias: AdminReportsPanel,
}

const route = useRoute()
const router = useRouter()
const refreshKey = ref(0)

function validSection(value) {
  return sections.some((item) => item.value === value)
}

const activeSection = computed(() => (
  validSection(route.params.section) ? route.params.section : 'dashboard'
))

const activeComponent = computed(() => components[activeSection.value])

function refreshPanel() {
  refreshKey.value += 1
}

watch(
  () => route.params.section,
  (value) => {
    if (!validSection(value)) {
      router.replace({
        name: 'admin',
        params: { section: 'dashboard' },
      })
    }
  },
  { immediate: true },
)
</script>

<style scoped>
</style>
