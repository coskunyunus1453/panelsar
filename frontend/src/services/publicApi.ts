import axios from 'axios'
import i18n from '../i18n'

function resolvePanelApiBase(): string {
  if (typeof window !== 'undefined') {
    try {
      return new URL('index.php/api', window.location.href).href.replace(/\/+$/, '')
    } catch {
      /* fallthrough */
    }
  }
  const base = String((import.meta as any).env?.BASE_URL || '/').replace(/\/+$/, '')
  if (!base || base === '/' || base === '.' || base === './') {
    return '/index.php/api'
  }
  return `${base}/index.php/api`
}

const apiBaseUrl = resolvePanelApiBase()

/**
 * Auth gerektirmeyen panel API çağrıları; global hata toast’u yok (404 vb. sessiz).
 */
const publicApi = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

publicApi.interceptors.request.use((config) => {
  const currentLocale = (i18n.language || 'en').split('-')[0]
  ;(config.headers as any)['X-Locale'] = currentLocale
  return config
})

export default publicApi
