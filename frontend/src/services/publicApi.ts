import axios from 'axios'
import i18n from '../i18n'
import { inferPublicPathPrefix } from '../lib/publicPath'

function resolvePanelApiBase(): string {
  if (typeof window !== 'undefined') {
    try {
      const inferredPrefix = inferPublicPathPrefix().replace(/\/+$/, '')
      if (inferredPrefix) {
        const appRootPath = `${inferredPrefix.replace(/\/admin$/, '')}/`
        return new URL('index.php/api', `${window.location.origin}${appRootPath}`).href.replace(/\/+$/, '')
      }
      const current = new URL(window.location.href)
      const rootPath = current.pathname.replace(/\/admin(?:\/.*)?$/, '/')
      return new URL('index.php/api', `${current.origin}${rootPath}`).href.replace(/\/+$/, '')
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
