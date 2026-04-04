import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'
import type { User } from '../types'

interface AuthState {
  user: User | null
  token: string | null
  portal: 'customer' | 'vendor'
  isAuthenticated: boolean
  setAuth: (user: User, token: string, portal: 'customer' | 'vendor') => void
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
      setAuth: (user, token, portal) => set({ user, token, portal, isAuthenticated: true }),
      logout: () => set({ user: null, token: null, portal: 'customer', isAuthenticated: false }),
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
      }),
    },
  ),
)
