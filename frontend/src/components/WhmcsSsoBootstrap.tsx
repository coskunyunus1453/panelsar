import { useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import toast from 'react-hot-toast'
import api from '../services/api'
import { useAuthStore } from '../store/authStore'

/**
 * WHMCS SSO: /admin?sso=<uuid> → tek kullanımlık token ile Sanctum oturumu.
 */
export default function WhmcsSsoBootstrap() {
  const navigate = useNavigate()
  const ran = useRef(false)

  useEffect(() => {
    if (ran.current) return
    const params = new URLSearchParams(window.location.search)
    const sso = params.get('sso')
    if (!sso) return
    ran.current = true

    ;(async () => {
      try {
        const { data } = await api.post('/auth/sso/whmcs-consume', { token: sso })
        useAuthStore.getState().setAuth(data.user, data.token, 'customer', {
          enforce_admin_2fa: data.enforce_admin_2fa,
          white_label: data.white_label,
        })
        params.delete('sso')
        const q = params.toString()
        window.history.replaceState({}, '', `${window.location.pathname}${q ? `?${q}` : ''}${window.location.hash || ''}`)
        navigate('/dashboard', { replace: true })
        toast.success('Oturum açıldı')
      } catch (err: unknown) {
        const msg =
          axios.isAxiosError(err) && err.response?.data && typeof err.response.data === 'object' && 'message' in err.response.data
            ? String((err.response.data as { message?: string }).message)
            : 'SSO başarısız'
        params.delete('sso')
        const q = params.toString()
        window.history.replaceState({}, '', `${window.location.pathname}${q ? `?${q}` : ''}${window.location.hash || ''}`)
        toast.error(msg)
        navigate('/login', { replace: true })
      }
    })()
  }, [navigate])

  return null
}
