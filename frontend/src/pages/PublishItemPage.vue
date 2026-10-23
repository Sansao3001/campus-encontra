<template>
  <ComingSoonPanel
    title="Publicar objecto"
    phase="Fase 3"
    icon="mdi-plus-circle-outline"
    description="O formulário de publicação será ligado ao endpoint de criação de objectos na Fase 3. Ainda não é possível publicar objectos por esta página."
    :items="[
      'Título, categoria, descrição, local, data e tipo',
      'Validação de dados no frontend e no backend',
      'Autoria determinada automaticamente pelo servidor',
      'Fotografia real numa fase posterior',
    ]"
  >
    <template #actions>
      <v-btn color="primary" rounded="lg" :to="{ name: 'login' }">
        Entrar para publicar
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
</script>
