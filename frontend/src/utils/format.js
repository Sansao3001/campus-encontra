const dateFormatter = new Intl.DateTimeFormat('pt-PT', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
})

const dateTimeFormatter = new Intl.DateTimeFormat('pt-PT', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

function parseDate(value) {
  if (!value) return null

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? null : date
}

export function formatDate(value) {
  const date = parseDate(value)
  return date ? dateFormatter.format(date) : 'Data não indicada'
}

export function formatDateTime(value) {
  const date = parseDate(value)
  return date ? dateTimeFormatter.format(date) : 'Data não indicada'
}

export function truncate(text, max = 140) {
  if (!text) return ''

  const normalized = String(text).trim()

  if (normalized.length <= max) return normalized

  return `${normalized.slice(0, max).trimEnd()}…`
}
