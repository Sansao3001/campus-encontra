<template>
  <div class="admin-trend">
    <div v-if="series.length" class="admin-trend__legend">
      <span
        v-for="item in series"
        :key="item.key"
        class="admin-trend__legend-item"
      >
        <span class="admin-trend__swatch" :style="{ backgroundColor: item.color }" />
        {{ item.label }}
        <strong>{{ totalFor(item) }}</strong>
      </span>
    </div>

    <div class="admin-trend__canvas">
      <svg
        class="admin-trend__svg"
        viewBox="0 0 800 280"
        role="img"
        :aria-label="ariaLabel"
      >
        <line
          v-for="tick in ticks"
          :key="`grid-${tick.value}`"
          :x1="plot.left"
          :x2="plot.right"
          :y1="tick.y"
          :y2="tick.y"
          class="admin-trend__grid"
        />

        <text
          v-for="tick in ticks"
          :key="`label-${tick.value}`"
          :x="plot.left - 10"
          :y="tick.y + 4"
          text-anchor="end"
          class="admin-trend__axis-label"
        >
          {{ tick.label }}
        </text>

        <polyline
          v-for="item in series"
          :key="item.key"
          :points="pointsFor(item.values)"
          fill="none"
          :stroke="item.color"
          stroke-width="3"
          stroke-linecap="round"
          stroke-linejoin="round"
          vector-effect="non-scaling-stroke"
        />

        <g v-if="showPoints">
          <template v-for="item in series" :key="`points-${item.key}`">
            <circle
              v-for="(point, index) in pointList(item.values)"
              :key="`${item.key}-${index}`"
              :cx="point.x"
              :cy="point.y"
              r="3.2"
              :fill="item.color"
              class="admin-trend__point"
            >
              <title>{{ pointLabel(item.label, index, item.values[index]) }}</title>
            </circle>
          </template>
        </g>

        <text
          v-for="label in xLabels"
          :key="label.index"
          :x="label.x"
          :y="plot.bottom + 24"
          :text-anchor="label.anchor"
          class="admin-trend__axis-label"
        >
          {{ label.text }}
        </text>
      </svg>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  labels: {
    type: Array,
    default: () => [],
  },
  series: {
    type: Array,
    default: () => [],
  },
  ariaLabel: {
    type: String,
    default: 'Gráfico de evolução temporal',
  },
})

const plot = {
  left: 48,
  right: 784,
  top: 18,
  bottom: 238,
}

const maxValue = computed(() => {
  const values = props.series.flatMap((item) => item.values ?? [])
  const max = Math.max(0, ...values.map((value) => Number(value) || 0))

  return max < 1 ? 1 : max
})

const ticks = computed(() => {
  const steps = 4

  return Array.from({ length: steps + 1 }, (_, index) => {
    const value = Math.round((maxValue.value / steps) * index)

    return {
      value,
      label: value.toLocaleString('pt-PT'),
      y: plot.bottom - ((plot.bottom - plot.top) * index) / steps,
    }
  })
})

const showPoints = computed(() => props.labels.length <= 31)

function pointsFor(values = []) {
  const list = Array.isArray(values) ? values : []
  const count = Math.max(props.labels.length, list.length)

  return list.map((value, index) => {
    const x = count <= 1
      ? (plot.left + plot.right) / 2
      : plot.left + ((plot.right - plot.left) * index) / (count - 1)
    const y = plot.bottom - ((plot.bottom - plot.top) * (Number(value) || 0)) / maxValue.value

    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
}

function pointList(values = []) {
  return (values ?? []).map((value, index) => {
    const count = Math.max(props.labels.length, values.length)
    const x = count <= 1
      ? (plot.left + plot.right) / 2
      : plot.left + ((plot.right - plot.left) * index) / (count - 1)
    const y = plot.bottom - ((plot.bottom - plot.top) * (Number(value) || 0)) / maxValue.value

    return { x, y }
  })
}

function shortDate(value) {
  if (!value) return ''

  const date = new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('pt-PT', {
    day: '2-digit',
    month: 'short',
  }).format(date)
}

const xLabels = computed(() => {
  const total = props.labels.length

  if (total === 0) return []

  const indexes = [...new Set([
    0,
    Math.floor((total - 1) / 2),
    total - 1,
  ])].filter((index) => index >= 0)

  return indexes.map((index) => {
    const ratio = total <= 1 ? 0.5 : index / (total - 1)
    const x = plot.left + (plot.right - plot.left) * ratio

    return {
      index,
      x,
      text: shortDate(props.labels[index]),
      anchor: index === 0 ? 'start' : index === total - 1 ? 'end' : 'middle',
    }
  })
})

function totalFor(item) {
  return (item.values ?? []).reduce((sum, value) => sum + (Number(value) || 0), 0)
}

function pointLabel(seriesLabel, index, value) {
  return `${seriesLabel} · ${props.labels[index] ?? ''}: ${Number(value) || 0}`
}
</script>

<style scoped>
.admin-trend {
  width: 100%;
}

.admin-trend__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  margin-bottom: 0.75rem;
}

.admin-trend__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--campus-muted);
  font-size: 0.82rem;
}

.admin-trend__legend-item strong {
  color: var(--campus-ink);
}

.admin-trend__swatch {
  width: 0.65rem;
  height: 0.65rem;
  border-radius: 999px;
}

.admin-trend__canvas {
  width: 100%;
  overflow: hidden;
}

.admin-trend__svg {
  display: block;
  width: 100%;
  height: auto;
  min-height: 190px;
}

.admin-trend__grid {
  stroke: rgba(22, 78, 128, 0.12);
  stroke-width: 1;
  stroke-dasharray: 3 5;
}

.admin-trend__axis-label {
  fill: var(--campus-muted);
  font-size: 12px;
}

.admin-trend__point:hover {
  r: 5;
}
</style>
