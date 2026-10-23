<template>
  <ComingSoonPanel
    title="Publicar objecto"
    phase="Área autenticada"
    icon="mdi-plus-circle-outline"
    description="A autenticação já está activa e esta rota exige sessão iniciada. O formulário de publicação será implementado numa fase posterior, sem criar dados simulados."
    :items="[
      'Sessão Sanctum confirmada pelo backend',
      'Rota protegida no frontend e no servidor',
      'Endpoint de criação protegido por autenticação',
      'Publicação real numa fase posterior',
    ]"
    :user="authState.user"
    :session-error="authState.sessionError"
    notice="A publicação real ainda não foi implementada. Nenhum objecto é criado nesta página."
    @retry-session="refreshSession"
  >
    <template #actions>
      <v-btn color="primary" rounded="lg" :to="{ name: 'my-items' }">
        Os meus objectos
      </v-btn>
      <v-btn
        color="primary"
        variant="outlined"
        rounded="lg"
        :to="{ name: 'home' }"
      >
        Voltar aos objectos
      </v-btn>
    </template>
  </ComingSoonPanel>
</template>

<script setup>
import ComingSoonPanel from '@/components/ComingSoonPanel.vue'
import { authState, refreshSession } from '@/stores/auth'
</script>
