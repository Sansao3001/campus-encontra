<template>
  <ComingSoonPanel
    title="Os meus objectos"
    phase="Fase 3"
    icon="mdi-bag-personal-outline"
    description="A área pessoal será activada quando a autenticação estiver ligada ao backend. Ainda não é mostrada nenhuma listagem privada."
    :items="[
      'Listagem dos objectos publicados pelo utilizador',
      'Consulta do estado actual de cada objecto',
      'Acesso à edição e remoção dos próprios objectos',
      'Preparação do fluxo de reclamações numa fase posterior',
    ]"
  >
    <template #actions>
      <v-btn color="primary" rounded="lg" :to="{ name: 'login' }">
        Entrar
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
</script>
