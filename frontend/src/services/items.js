import api from './api'

function normalizeCollection(data) {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

function normalizeItem(data) {
  return data?.data ?? data ?? null
}

function isFile(value) {
  return typeof File !== 'undefined' && value instanceof File
}

function buildFormData(payload) {
  const formData = new FormData()

  Object.entries(payload ?? {}).forEach(([key, value]) => {
    if (value === undefined || value === null) return

    if (key === 'photo') {
      if (isFile(value)) formData.append('photo', value)
      return
    }

    if (key === 'removePhoto') {
      if (value) formData.append('remove_photo', '1')
      return
    }

    formData.append(key, String(value))
  })

  return formData
}

function hasUpload(payload) {
  return isFile(payload?.photo) || Boolean(payload?.removePhoto)
}

export async function fetchItems(params = {}) {
  const response = await api.get('/items', { params })
  const data = response.data

  if (Array.isArray(data)) {
    return {
      items: data,
      meta: null,
      links: null,
    }
  }

  return {
    items: normalizeCollection(data),
    meta: data?.meta ?? null,
    links: data?.links ?? null,
  }
}

export async function fetchItem(id) {
  const response = await api.get(`/items/${encodeURIComponent(id)}`)
  return normalizeItem(response.data)
}

export async function fetchMyItems() {
  const response = await api.get('/my-items')
  return normalizeCollection(response.data)
}

export async function fetchMyItemsPaged(params = {}) {
  const response = await api.get('/my-items', { params })
  const data = response.data

  return {
    items: normalizeCollection(data),
    meta: data?.meta ?? null,
  }
}

export async function createItem(payload) {
  const response = hasUpload(payload)
    ? await api.post('/items', buildFormData(payload))
    : await api.post('/items', payload)

  return normalizeItem(response.data)
}

export async function updateItem(id, payload) {
  const url = `/items/${encodeURIComponent(id)}`

  if (!hasUpload(payload)) {
    const response = await api.put(url, payload)
    return normalizeItem(response.data)
  }

  // O PHP só interpreta ficheiros em pedidos POST; usamos method spoofing
  // para manter a rota PUT/PATCH e enviar a fotografia em multipart.
  const formData = buildFormData(payload)
  formData.append('_method', 'PUT')

  const response = await api.post(url, formData)
  return normalizeItem(response.data)
}

export async function deleteItem(id) {
  await api.delete(`/items/${encodeURIComponent(id)}`)
}
