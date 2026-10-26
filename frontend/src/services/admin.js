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

export async function fetchAdminDashboard(params = {}) {
  const response = await api.get('/admin/dashboard', { params })
  return response.data
}

export async function fetchAdminReporting(params = {}) {
  const response = await api.get('/admin/reporting', { params })
  return response.data
}

export async function fetchAdminSettings() {
  const response = await api.get('/admin/settings')
  return response.data
}

export async function updateAdminSettings(settings) {
  const response = await api.put('/admin/settings', { settings })
  return response.data
}

export async function downloadAdminExport(dataset, params = {}) {
  const response = await api.get(`/admin/exports/${encodeURIComponent(dataset)}`, {
    params,
    responseType: 'blob',
    timeout: 60000,
  })

  const disposition = response.headers['content-disposition'] || ''
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
  const filename = match
    ? decodeURIComponent(match[1].trim())
    : `campus-encontra-${dataset}.csv`

  const objectUrl = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1500)

  return filename
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
