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
    // Prod güvenliği: host aynı olmalı.
    // Local (XAMPP): Panel genelde `localhost`, engine ise `127.0.0.1:9090` dönebildiği için
    // loopback'ler arası host uyuşmazlığını allow ediyoruz.
    if (u.host === window.location.host) return u.toString()

    const allowedLoopback = new Set(['localhost', '127.0.0.1', '::1'])
    const currentHost = String(window.location.hostname || '').toLowerCase()
    const targetHost = String(u.hostname || '').toLowerCase()

    if (allowedLoopback.has(currentHost) && allowedLoopback.has(targetHost)) {
      return u.toString()
    }

    return null
  } catch {
    return null
  }
}
