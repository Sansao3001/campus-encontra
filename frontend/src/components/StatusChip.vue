<template>
  <v-chip
    :color="config.color"
    :prepend-icon="config.icon"
    :size="size"
    variant="tonal"
    label
  >
    {{ config.label }}
  </v-chip>
</template>

<script setup>
import { computed } from 'vue'
import { getStatusConfig, getTypeConfig } from '@/utils/item'

const props = defineProps({
  kind: {
    type: String,
    default: 'status',
    validator: (value) => ['type', 'status'].includes(value),
  },
  value: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'small',
  },
})

const config = computed(() => (
  props.kind === 'type'
    ? getTypeConfig(props.value)
    : getStatusConfig(props.value)
))
</script>
