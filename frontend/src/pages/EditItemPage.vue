<template>
  <ComingSoonPanel
    :title="'Editar objecto #' + id"
    phase="Área autenticada"
    icon="mdi-pencil-outline"
    description="A autenticação está activa e esta rota está protegida. A edição será ligada ao endpoint real numa fase posterior."
    :items="[
      'Sessão Sanctum confirmada pelo backend',
      'Rota protegida no frontend e no servidor',
      'O backend valida a autoria do objecto',
      'Nenhuma alteração é guardada nesta página',
    ]"
    :user="authState.user"
    :session-error="authState.sessionError"
    notice="A edição real ainda não foi implementada. Nenhuma alteração é guardada."
    @retry-session="refreshSession"
  >
    <template #actions>
      <v-btn
        color="primary"
        rounded="lg"
        :to="{ name: 'item-detail', params: { id } }"
      >
        Ver detalhes públicos
      </v-btn>
      <v-btn
        color="primary"
        variant="outlined"
        rounded="lg"
        :to="{ name: 'my-items' }"
      >
        Os meus objectos
      </v-btn>
    </template>
  </ComingSoonPanel>
</template>

<script setup>
import ComingSoonPanel from '@/components/ComingSoonPanel.vue'
import { authState, refreshSession } from '@/stores/auth'

defineProps({
  id: {
    type: [String, Number],
    required: true,
  },
})
</script>
