import api from './api'
import type { User, WhiteLabelUi } from '../types'

export interface LoginResponse {
  user: User
  token: string
  expires_at: string
  enforce_admin_2fa?: boolean
  force_password_change?: boolean
  white_label?: WhiteLabelUi | null
}

export type LoginResult =
  | { ok: true; data: LoginResponse }
  | { ok: false; challenge: 'twofa_required'; message: string }

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
  ): Promise<LoginResult> => {
    const payload: any = { email, password, portal }
    if (options?.otp) payload.otp = options.otp
    if (options?.backupCode) payload.backup_code = options.backupCode

    const { data } = await api.post('/auth/login', payload)
    if (data && typeof data === 'object' && (data as { code?: string }).code === 'twofa_required') {
      return {
        ok: false,
        challenge: 'twofa_required',
        message: typeof (data as { message?: string }).message === 'string'
          ? (data as { message: string }).message
          : '',
      }
    }
    return { ok: true, data: data as LoginResponse }
  },

  logout: async (): Promise<void> => {
    await api.post('/auth/logout')
  },

  me: async (): Promise<{
    user: User
    enforce_admin_2fa?: boolean
    force_password_change?: boolean
    white_label?: WhiteLabelUi | null
  }> => {
    const { data } = await api.get('/auth/me')
    return data
  },

  refresh: async (): Promise<{ token: string }> => {
    const { data } = await api.post('/auth/refresh')
    return data
  },
}
