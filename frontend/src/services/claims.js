import api from './api'

function normalizeCollection(data) {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

function normalizeClaim(data) {
  return data?.data ?? data ?? null
}

function paginatedResponse(data) {
  return {
    claims: normalizeCollection(data),
    meta: data?.meta ?? null,
  }
}

export async function createClaim(itemId, payload = {}) {
  const response = await api.post(
    `/items/${encodeURIComponent(itemId)}/claims`,
    payload,
  )
  return normalizeClaim(response.data)
}

export async function fetchMyClaims() {
  const response = await api.get('/my-claims')
  return normalizeCollection(response.data)
}

export async function fetchMyClaimsPaged(params = {}) {
  const response = await api.get('/my-claims', { params })
  return paginatedResponse(response.data)
}

export async function fetchReceivedClaims() {
  const response = await api.get('/received-claims')
  return normalizeCollection(response.data)
}

export async function fetchReceivedClaimsPaged(params = {}) {
  const response = await api.get('/received-claims', { params })
  return paginatedResponse(response.data)
}

export async function fetchItemClaims(itemId) {
  const response = await api.get(
    `/items/${encodeURIComponent(itemId)}/claims`,
  )
  return normalizeCollection(response.data)
}

export async function cancelClaim(claimId) {
  const response = await api.patch(
    `/claims/${encodeURIComponent(claimId)}/cancel`,
  )
  return normalizeClaim(response.data)
}

export async function acceptClaim(claimId) {
  const response = await api.patch(
    `/claims/${encodeURIComponent(claimId)}/accept`,
  )
  return normalizeClaim(response.data)
}

export async function rejectClaim(claimId) {
  const response = await api.patch(
    `/claims/${encodeURIComponent(claimId)}/reject`,
  )
  return normalizeClaim(response.data)
}

export async function reopenClaim(claimId) {
  const response = await api.patch(
    `/claims/${encodeURIComponent(claimId)}/reopen`,
  )
  return normalizeClaim(response.data)
}

export async function confirmClaimReturn(claimId) {
  const response = await api.patch(
    `/claims/${encodeURIComponent(claimId)}/confirm-return`,
  )
  return normalizeClaim(response.data)
}

export async function fetchClaimMessages(claimId) {
  const response = await api.get(
    `/claims/${encodeURIComponent(claimId)}/messages`,
  )
  return normalizeCollection(response.data)
}

export async function sendClaimMessage(claimId, body) {
  const response = await api.post(
    `/claims/${encodeURIComponent(claimId)}/messages`,
    { body },
  )
  return normalizeClaim(response.data)
}
