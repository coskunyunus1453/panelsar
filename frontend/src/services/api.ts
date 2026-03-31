import axios from 'axios'
import { useAuthStore } from '../store/authStore'
import { useNotificationsStore } from '../store/notificationsStore'

const api = axios.create({
  baseURL: '/api',
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
      window.location.href = '/login'
    } else {
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
