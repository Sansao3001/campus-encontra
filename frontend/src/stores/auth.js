import { computed, reactive, readonly } from 'vue'
import api from '@/services/api'
import { onUnauthorized } from '@/services/authEvents'
import {
  clearStoredToken,
  getStoredToken,
  storeToken,
} from '@/services/tokenStorage'
import { getApiErrorMessage } from '@/utils/errors'

const state = reactive({
  token: getStoredToken(),
  user: null,
  initialized: false,
  loading: false,
  sessionError: '',
  sessionExpired: false,
})

let restorePromise = null

export const authState = readonly(state)
export const hasSession = computed(() => Boolean(state.token))
export const isAuthenticated = computed(() => Boolean(state.token && state.user))
export const isAdmin = computed(() => state.user?.role === 'admin')

function normalizeUser(data) {
  const user = data?.data ?? data

  return user && typeof user === 'object' && user.id ? user : null
}

function setSession(token, user) {
  if (!token || !user) {
    throw new Error('Resposta de autenticação inválida.')
  }

  storeToken(token)
  state.token = token
  state.user = user
  state.initialized = true
  state.sessionError = ''
  state.sessionExpired = false
}

export function clearSession() {
  clearStoredToken()
  state.token = null
  state.user = null
  state.initialized = true
  state.sessionError = ''
  state.sessionExpired = false
}

onUnauthorized(() => {
  clearSession()
  state.sessionExpired = true
})

export async function login(credentials) {
  const { data } = await api.post('/login', credentials)
  const user = normalizeUser(data?.user)

  setSession(data?.token, user)

  return user
}

export async function register(payload) {
  const { data } = await api.post('/register', payload)
  const user = normalizeUser(data?.user)

  setSession(data?.token, user)

  return user
}

export async function logout() {
  let revoked = false
  let error = null

  try {
    await api.post('/logout')
    revoked = true
  } catch (requestError) {
    error = requestError
  } finally {
    clearSession()
  }

  return { revoked, error }
}

export async function restoreSession({ force = false } = {}) {
  if (restorePromise) return restorePromise
  if (!force && state.initialized) return state.user

  if (!state.token) {
    state.initialized = true
    state.loading = false
    return null
  }

  state.loading = true
  state.sessionError = ''

  restorePromise = (async () => {
    try {
      const { data } = await api.get('/user')
      const user = normalizeUser(data)

      if (!user) {
        throw new Error('Resposta de utilizador inválida.')
      }

      state.user = user
      state.sessionExpired = false
    } catch (requestError) {
      if (requestError?.response?.status === 401) {
        clearSession()
        state.sessionExpired = true
      } else if (!state.user) {
        state.sessionError = getApiErrorMessage(
          requestError,
          'Não foi possível confirmar a sessão. Verifica a ligação e tenta novamente.',
        )
      }
    } finally {
      state.loading = false
      state.initialized = true
      restorePromise = null
    }

    return state.user
  })()

  return restorePromise
}

export function refreshSession() {
  return restoreSession({ force: true })
}
