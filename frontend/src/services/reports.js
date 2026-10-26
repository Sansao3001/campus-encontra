import api from './api'

export async function createReport(payload) {
  const response = await api.post('/reports', payload)
  return response.data?.report ?? response.data
}
