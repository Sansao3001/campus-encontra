<template>
  <ComingSoonPanel
    title="Os meus objectos"
    phase="Área autenticada"
    icon="mdi-bag-personal-outline"
    description="A autenticação está activa e esta rota está protegida. A listagem privada dos teus objectos será implementada numa fase posterior."
    :items="[
      'Sessão Sanctum confirmada pelo backend',
      'Rota protegida no frontend e no servidor',
      'O backend já valida a autoria nas operações de edição e remoção',
      'A listagem privada será adicionada numa fase posterior',
    ]"
    :user="authState.user"
    :session-error="authState.sessionError"
    notice="A listagem privada ainda não foi implementada. Não são mostrados dados simulados."
    @retry-session="refreshSession"
  >
    <template #actions>
      <v-btn color="primary" rounded="lg" :to="{ name: 'publish' }">
        Publicar objecto
      </v-btn>
      <v-btn
        color="primary"
        variant="outlined"
        rounded="lg"
        :to="{ name: 'home' }"
      >
        Ver objectos públicos
      </v-btn>
    </template>
  </ComingSoonPanel>
</template>

<script setup>
import ComingSoonPanel from '@/components/ComingSoonPanel.vue'
import { authState, refreshSession } from '@/stores/auth'
</script>
