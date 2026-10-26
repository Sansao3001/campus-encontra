<template>
  <div v-if="items.length" class="admin-bars">
    <div
      v-for="item in normalizedItems"
      :key="item.label"
      class="admin-bars__row"
    >
      <div class="admin-bars__heading">
        <span class="admin-bars__label">{{ item.label }}</span>
        <strong class="admin-bars__value">{{ item.total }}</strong>
      </div>
      <div
        class="admin-bars__track"
        role="img"
        :aria-label="`${item.label}: ${item.total}`"
      >
        <span
          class="admin-bars__fill"
          :style="{ width: `${item.percent}%`, backgroundColor: color }"
        />
      </div>
    </div>
  </div>

  <p v-else class="admin-empty-copy">
    {{ emptyText }}
  </p>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  color: {
    type: String,
    default: '#164E80',
  },
  emptyText: {
    type: String,
    default: 'Sem dados para apresentar.',
  },
})

const normalizedItems = computed(() => {
  const max = Math.max(1, ...props.items.map((item) => Number(item.total) || 0))

  return props.items.map((item) => ({
    ...item,
    total: Number(item.total) || 0,
    percent: ((Number(item.total) || 0) / max) * 100,
  }))
})
</script>

<style scoped>
.admin-bars {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.admin-bars__heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.35rem;
}

.admin-bars__label {
  overflow: hidden;
  color: var(--campus-ink);
  font-size: 0.86rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.admin-bars__value {
  color: var(--campus-muted);
  font-size: 0.8rem;
}

.admin-bars__track {
  height: 0.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(22, 78, 128, 0.09);
}

.admin-bars__fill {
  display: block;
  height: 100%;
  min-width: 2px;
  border-radius: inherit;
  transition: width 0.35s ease;
}

.admin-empty-copy {
  margin: 0;
  color: var(--campus-muted);
  font-size: 0.86rem;
}
</style>
