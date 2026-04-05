import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'
import type { User } from '../types'

interface AuthState {
  user: User | null
  token: string | null
  portal: 'customer' | 'vendor'
  isAuthenticated: boolean
  /** Sunucu politikası: admin/vendor operatörlerde 2FA zorunlu (null = henüz /auth/me ile bilinmiyor). */
  enforceAdmin2fa: boolean | null
  setAuth: (
    user: User,
    token: string,
    portal: 'customer' | 'vendor',
    extras?: { enforce_admin_2fa?: boolean },
  ) => void
  setEnforceAdmin2fa: (v: boolean | null) => void
  logout: () => void
  updateUser: (user: Partial<User>) => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      portal: 'customer',
      isAuthenticated: false,
      enforceAdmin2fa: null,
      setAuth: (user, token, portal, extras) =>
        set({
          user,
          token,
          portal,
          isAuthenticated: true,
          enforceAdmin2fa:
            extras?.enforce_admin_2fa !== undefined ? extras.enforce_admin_2fa : null,
        }),
      setEnforceAdmin2fa: (v) => set({ enforceAdmin2fa: v }),
      logout: () =>
        set({
          user: null,
          token: null,
          portal: 'customer',
          isAuthenticated: false,
          enforceAdmin2fa: null,
        }),
      updateUser: (updates) =>
        set((state) => ({
          user: state.user ? { ...state.user, ...updates } : null,
        })),
    }),
    {
      name: 'hostvim-auth',
      storage: createJSONStorage(() => sessionStorage),
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        portal: state.portal,
        isAuthenticated: state.isAuthenticated,
        enforceAdmin2fa: state.enforceAdmin2fa,
      }),
    },
  ),
)
