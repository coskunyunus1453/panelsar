export function safeExternalHttpUrl(raw: string): string | null {
  const v = String(raw || '').trim()
  if (!v) return null
  try {
    const u = new URL(v)
    if (u.protocol !== 'https:' && u.protocol !== 'http:') return null
    return u.toString()
  } catch {
    return null
  }
}

export function safeDomainUrl(hostname: string): string | null {
  const h = String(hostname || '').trim().toLowerCase()
  const re = /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/
  if (!re.test(h)) return null
  return `https://${h}`
}

export function safeWebSocketUrl(raw: string): string | null {
  const v = String(raw || '').trim()
  if (!v) return null
  try {
    const u = new URL(v)
    if (u.protocol !== 'wss:' && u.protocol !== 'ws:') return null
    if (u.protocol === 'ws:' && window.location.protocol === 'https:') return null
    if (u.host !== window.location.host) return null
    return u.toString()
  } catch {
    return null
  }
}
