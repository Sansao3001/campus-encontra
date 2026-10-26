import api from './api'

function normalizeCollection(data) {
  if (Array.isArray(data)) {
    return { items: data, meta: null }
  }

  return {
    items: Array.isArray(data?.data) ? data.data : [],
    meta: data?.meta ?? null,
  }
}

export async function fetchAdminDashboard() {
  const response = await api.get('/admin/dashboard')
  return response.data
}

export async function fetchAdminUsers(params = {}) {
  const response = await api.get('/admin/users', { params })
  return normalizeCollection(response.data)
}

export async function fetchAdminUser(id) {
  const response = await api.get(`/admin/users/${encodeURIComponent(id)}`)
  return response.data?.data ?? response.data
}

export async function suspendAdminUser(id, reason) {
  const response = await api.patch(
    `/admin/users/${encodeURIComponent(id)}/suspend`,
    { reason },
  )
  return response.data
}

export async function reactivateAdminUser(id) {
  const response = await api.patch(
    `/admin/users/${encodeURIComponent(id)}/reactivate`,
  )
  return response.data
}

export async function changeAdminUserRole(id, role) {
  const response = await api.patch(
    `/admin/users/${encodeURIComponent(id)}/role`,
    { role },
  )
  return response.data
}

export async function fetchAdminItems(params = {}) {
  const response = await api.get('/admin/items', { params })
  return normalizeCollection(response.data)
}

export async function fetchAdminItem(id) {
  const response = await api.get(`/admin/items/${encodeURIComponent(id)}`)
  return response.data?.data ?? response.data
}

export async function moderateAdminItem(id, reason) {
  const response = await api.patch(
    `/admin/items/${encodeURIComponent(id)}/moderate`,
    { reason },
  )
  return response.data
}

export async function restoreAdminItem(id, reason) {
  const response = await api.patch(
    `/admin/items/${encodeURIComponent(id)}/restore`,
    { reason: reason || null },
  )
  return response.data
}

export async function fetchAdminClaims(params = {}) {
  const response = await api.get('/admin/claims', { params })
  return normalizeCollection(response.data)
}

export async function fetchAdminClaim(id) {
  const response = await api.get(`/admin/claims/${encodeURIComponent(id)}`)
  return response.data?.data ?? response.data
}

export async function fetchAdminReports(params = {}) {
  const response = await api.get('/admin/reports', { params })
  return normalizeCollection(response.data)
}

export async function fetchAdminReport(id) {
  const response = await api.get(`/admin/reports/${encodeURIComponent(id)}`)
  return response.data?.data ?? response.data
}

export async function updateAdminReport(id, payload) {
  const response = await api.patch(
    `/admin/reports/${encodeURIComponent(id)}`,
    payload,
  )
  return response.data
}

export async function fetchAdminAuditLogs(params = {}) {
  const response = await api.get('/admin/audit-logs', { params })
  return normalizeCollection(response.data)
}
