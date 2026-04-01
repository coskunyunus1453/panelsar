import api from './api'
import type { User } from '../types'

interface LoginResponse {
  user: User
  token: string
  expires_at: string
}

export const authService = {
  login: async (email: string, password: string, portal: 'customer' | 'vendor' = 'customer'): Promise<LoginResponse> => {
    const { data } = await api.post('/auth/login', { email, password, portal })
    return data
  },

  logout: async (): Promise<void> => {
    await api.post('/auth/logout')
  },

  me: async (): Promise<{ user: User }> => {
    const { data } = await api.get('/auth/me')
    return data
  },

  refresh: async (): Promise<{ token: string }> => {
    const { data } = await api.post('/auth/refresh')
    return data
  },
}
