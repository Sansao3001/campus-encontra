const unauthorizedListeners = new Set()

export function onUnauthorized(listener) {
  unauthorizedListeners.add(listener)

  return () => {
    unauthorizedListeners.delete(listener)
  }
}

export function emitUnauthorized() {
  for (const listener of unauthorizedListeners) {
    listener()
  }
}
