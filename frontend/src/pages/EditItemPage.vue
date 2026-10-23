<template>
  <ComingSoonPanel
    :title="'Editar objecto #' + id"
    phase="Fase 3"
    icon="mdi-pencil-outline"
    description="A edição será ligada ao endpoint de actualização e às permissões do backend na Fase 3. Nenhuma alteração é guardada nesta página."
    :items="[
      'Actualização dos dados do próprio objecto',
      'Validação de dados no frontend e no backend',
      'Confirmação de que o utilizador é o autor',
      'Protecção do estado e da autoria pelo servidor',
    ]"
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

defineProps({
  id: {
    type: [String, Number],
    required: true,
  },
})
</script>
