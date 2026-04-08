import axios from 'axios'
import { useAuthStore } from '../store/authStore'
import { useNotificationsStore } from '../store/notificationsStore'
import { effectiveLoginPath } from '../config/profile'
import { inferPublicPathPrefix } from '../lib/publicPath'
import i18n from '../i18n'

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

// XAMPP: `index.php/api` front controller; `.htaccess` rewrite yoksa bile çalışır.
/** Dışa aktarma (fetch) için tam API kökü. */
export const apiBaseUrl = resolvePanelApiBase()

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Backend Laravel `app()->setLocale()` için her API çağrısına dili taşır.
  const currentLocale = (i18n.language || 'en').split('-')[0]
  ;(config.headers as any)['X-Locale'] = currentLocale
  // FormData: Content-Type'ı kaldır; tarayıcı multipart boundary ile ayarlar (aksi halde Laravel 422)
  if (config.data instanceof FormData) {
    config.headers.delete('Content-Type')
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
      const loginPath = effectiveLoginPath
      const prefix = inferPublicPathPrefix().replace(/\/+$/, '')
      window.location.href = `${window.location.origin}${prefix}${loginPath}`
    } else {
      const code = error.response?.data?.code as string | undefined
      // 2FA challenge / admin 2FA token: bildirim spam’i yapma; ilgili sayfa kendi mesajını gösterir.
      if (
        error.response?.status === 423 &&
        (code === 'twofa_required' || code === 'admin_2fa_required')
      ) {
        return Promise.reject(error)
      }
      if (error.response?.status === 423 && code === 'password_change_required') {
        if (typeof window !== 'undefined') {
          window.location.href = `${window.location.origin}${inferPublicPathPrefix().replace(/\/+$/, '')}/settings?mandatoryPassword=1`
        }
        return Promise.reject(error)
      }

      const msg = error.response?.data?.message || error.message || 'API error'
      useNotificationsStore.getState().add({
        level: 'error',
        title: 'API Hatası',
        message: String(msg),
      })
    }
    return Promise.reject(error)
  }
)

export default api
