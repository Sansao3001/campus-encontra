export function getApiErrorMessage(
  error,
  fallback = 'Não foi possível concluir a operação. Tenta novamente.',
) {
  const data = error?.response?.data

  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message
  }

  if (data?.errors && typeof data.errors === 'object') {
    const firstError = Object.values(data.errors).flat().find(Boolean)

    if (firstError) return firstError
  }

  if (error?.code === 'ECONNABORTED') {
    return 'O servidor demorou demasiado tempo a responder. Tenta novamente.'
  }

  if (!error?.response) {
    return 'Não foi possível contactar a API. Confirma se o Laravel está ligado.'
  }

  return fallback
}

export function getFieldErrors(error) {
  const errors = error?.response?.data?.errors

  if (!errors || typeof errors !== 'object') return {}

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  )
}
