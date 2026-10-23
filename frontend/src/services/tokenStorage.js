const TOKEN_KEY = 'campus-encontra.token'

function getStorage() {
  if (typeof window === 'undefined') return null

  try {
    return window.sessionStorage
  } catch {
    return null
  }
}

export function getStoredToken() {
  try {
    return getStorage()?.getItem(TOKEN_KEY) ?? null
  } catch {
    return null
  }
}

export function storeToken(token) {
  try {
    getStorage()?.setItem(TOKEN_KEY, token)
  } catch {
    // O token continua disponível em memória durante esta sessão.
  }
}

export function clearStoredToken() {
  try {
    getStorage()?.removeItem(TOKEN_KEY)
  } catch {
    // Não há nada a limpar se o armazenamento estiver indisponível.
  }
}

export { TOKEN_KEY }
