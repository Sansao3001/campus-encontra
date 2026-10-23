<template>
  <ComingSoonPanel
    title="Criar conta"
    phase="Fase 3"
    icon="mdi-account-plus-outline"
    description="O registo será ligado ao backend na Fase 3. Esta página ainda não cria contas nem guarda dados."
    :items="[
      'Registo com nome, email e palavra-passe',
      'Validação de dados no frontend e no backend',
      'Criação segura da conta com Laravel Sanctum',
      'Confirmação visual do resultado do registo',
    ]"
  >
    <template #actions>
      <v-btn color="primary" rounded="lg" :to="{ name: 'home' }">
        Voltar ao início
      </v-btn>
      <v-btn
        color="primary"
        variant="outlined"
        rounded="lg"
        :to="{ name: 'login' }"
      >
        Já tenho conta
      </v-btn>
    </template>
  </ComingSoonPanel>
</template>

<script setup>
import ComingSoonPanel from '@/components/ComingSoonPanel.vue'
</script>
