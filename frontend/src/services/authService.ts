import api from './api'
import type { User } from '../types'

interface LoginResponse {
  user: User
  token: string
  expires_at: string
  enforce_admin_2fa?: boolean
}

interface LoginOptions {
  otp?: string
  backupCode?: string
}

export const authService = {
  login: async (
    email: string,
    password: string,
    portal: 'customer' | 'vendor' = 'customer',
    options?: LoginOptions,
  ): Promise<LoginResponse> => {
    const payload: any = { email, password, portal }
    if (options?.otp) payload.otp = options.otp
    if (options?.backupCode) payload.backup_code = options.backupCode

    const { data } = await api.post('/auth/login', payload)
    return data
  },

  logout: async (): Promise<void> => {
    await api.post('/auth/logout')
  },

  me: async (): Promise<{ user: User; enforce_admin_2fa?: boolean }> => {
    const { data } = await api.get('/auth/me')
    return data
  },

  refresh: async (): Promise<{ token: string }> => {
    const { data } = await api.post('/auth/refresh')
    return data
  },
}
