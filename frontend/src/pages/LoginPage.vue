<template>
  <ComingSoonPanel
    title="Entrar"
    phase="Fase 3"
    icon="mdi-login"
    description="A autenticação com Laravel Sanctum será ligada ao backend na Fase 3. Esta página ainda não permite iniciar sessão."
    :items="[
      'Login com email e palavra-passe',
      'Gestão segura do token de sessão',
      'Redireccionamento para a área pessoal',
      'Logout com revogação do token',
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
        :to="{ name: 'register' }"
      >
        Criar conta
      </v-btn>
    </template>
  </ComingSoonPanel>
</template>

<script setup>
import ComingSoonPanel from '@/components/ComingSoonPanel.vue'
</script>
