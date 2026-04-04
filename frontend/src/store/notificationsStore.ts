import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'

export type NotificationItem = {
  id: string
  title: string
  message?: string
  path?: string
  level: 'info' | 'success' | 'error'
  read: boolean
  createdAt: string
}

type NotificationsState = {
  items: NotificationItem[]
  add: (n: Omit<NotificationItem, 'id' | 'read' | 'createdAt'>) => void
  mergeFromServer: (rows: Array<{ id: string; title: string; message?: string; path?: string; level: 'info' | 'success' | 'error'; created_at?: string }>) => void
  markAllRead: () => void
  remove: (id: string) => void
  clear: () => void
}

export const useNotificationsStore = create<NotificationsState>()(
  persist(
    (set) => ({
      items: [],
      add: (n) =>
        set((s) => ({
          items: [
            {
              id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
              read: false,
              createdAt: new Date().toISOString(),
              ...n,
            },
            ...s.items,
          ].slice(0, 80),
        })),
      mergeFromServer: (rows) =>
        set((s) => {
          const map = new Map(s.items.map((i) => [i.id, i]))
          for (const r of rows) {
            const prev = map.get(r.id)
            map.set(r.id, {
              id: r.id,
              title: r.title,
              message: r.message,
              path: r.path,
              level: r.level,
              createdAt: r.created_at || new Date().toISOString(),
              read: prev?.read ?? false,
            })
          }
          const merged = Array.from(map.values()).sort((a, b) => b.createdAt.localeCompare(a.createdAt))
          return { items: merged.slice(0, 120) }
        }),
      markAllRead: () =>
        set((s) => ({ items: s.items.map((i) => ({ ...i, read: true })) })),
      remove: (id) =>
        set((s) => ({ items: s.items.filter((i) => i.id !== id) })),
      clear: () => set({ items: [] }),
    }),
    {
      name: 'hostvim-notifications',
      storage: createJSONStorage(() => localStorage),
    }
  )
)
