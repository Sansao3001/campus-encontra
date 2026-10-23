<template>
  <v-chip
    :color="config.color"
    :prepend-icon="config.icon"
    size="small"
    variant="tonal"
    label
  >
    {{ config.label }}
  </v-chip>
</template>

<script setup>
import { computed } from 'vue'
import { getClaimStatusConfig } from '@/utils/claim'

const props = defineProps({
  value: {
    type: String,
    default: '',
  },
})

const config = computed(() => getClaimStatusConfig(props.value))
</script>
