import { reactive, readonly } from 'vue'
import { fetchPublicSettings } from '@/services/settings'

const state = reactive({
  settings: null,
  loading: false,
  loaded: false,
  error: '',
})

let loadPromise = null

export const publicSettings = readonly(state)

export async function loadPublicSettings({ force = false } = {}) {
  if (!force && state.loaded && state.settings) {
    return state.settings
  }

  if (loadPromise) return loadPromise

  state.loading = true
  state.error = ''

  loadPromise = (async () => {
    try {
      state.settings = await fetchPublicSettings()
      state.loaded = true
    } catch {
      state.error = 'Não foi possível carregar as configurações públicas.'
    } finally {
      state.loading = false
      loadPromise = null
    }

    return state.settings
  })()

  return loadPromise
}

export function refreshPublicSettings() {
  return loadPublicSettings({ force: true })
}
