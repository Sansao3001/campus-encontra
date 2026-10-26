import api from './api'

export async function fetchPublicSettings() {
  const response = await api.get('/settings')
  return response.data?.data ?? response.data
}
