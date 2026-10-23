export function getSafeRedirect(value, fallback = null) {
  if (typeof value !== 'string') return fallback

  const target = value.trim()

  if (!target.startsWith('/')) return fallback
  if (target.startsWith('//')) return fallback
  if (target.includes('\\')) return fallback
  if (/[\u0000-\u001f]/.test(target)) return fallback

  return target
}
