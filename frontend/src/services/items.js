import api from './api'

function normalizeCollection(data) {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

function normalizeItem(data) {
  return data?.data ?? data ?? null
}

export async function fetchItems() {
  const response = await api.get('/items')
  return normalizeCollection(response.data)
}

export async function fetchItem(id) {
  const response = await api.get(`/items/${encodeURIComponent(id)}`)
  return normalizeItem(response.data)
}
