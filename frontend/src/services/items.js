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

export async function fetchMyItems() {
  const response = await api.get('/my-items')
  return normalizeCollection(response.data)
}

export async function createItem(payload) {
  const response = await api.post('/items', payload)
  return normalizeItem(response.data)
}

export async function updateItem(id, payload) {
  const response = await api.put(`/items/${encodeURIComponent(id)}`, payload)
  return normalizeItem(response.data)
}

export async function deleteItem(id) {
  await api.delete(`/items/${encodeURIComponent(id)}`)
}
