import axios from 'axios'
import { emitUnauthorized } from './authEvents'
import { clearStoredToken, getStoredToken } from './tokenStorage'

const baseURL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'

const api = axios.create({
  baseURL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
})

function resolveOrigin(url, base) {
  try {
    const resolvedBase = new URL(base, window.location.origin)
    return new URL(url, resolvedBase).origin
  } catch {
    return null
  }
}

api.interceptors.request.use((config) => {
  const token = getStoredToken()

  if (!token) return config

  const apiOrigin = resolveOrigin('', baseURL)
  const requestOrigin = resolveOrigin(config.url ?? '', config.baseURL ?? baseURL)

  // O token só pode seguir para a API configurada. Um pedido absoluto
  // para outro origin fica sem Authorization.
  if (apiOrigin && requestOrigin === apiOrigin) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401 && getStoredToken()) {
      clearStoredToken()
      emitUnauthorized()
    }

    return Promise.reject(error)
  },
)

export default api
