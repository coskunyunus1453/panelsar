import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Users, Plus, UserX, UserCheck } from 'lucide-react'
import toast from 'react-hot-toast'

type Role = { name: string }
type AdminUser = {
  id: number
  name: string
  email: string
  status: string
  roles: Role[]
}

export default function AdminUsersPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [search, setSearch] = useState('')
  const [showAdd, setShowAdd] = useState(false)

  const q = useQuery({
    queryKey: ['admin-users', search],
    queryFn: async () =>
      (await api.get('/admin/users', { params: search ? { search } : {} })).data,
    enabled: !!isAdmin,
  })

  const createM = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => api.post('/admin/users', payload),
    onSuccess: () => {
      toast.success(t('users.created'))
      qc.invalidateQueries({ queryKey: ['admin-users'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = ax.response?.data?.errors
        ? Object.values(ax.response.data.errors)[0]?.[0]
        : undefined
      toast.error(first ?? ax.response?.data?.message ?? String(err))
    },
  })

  const suspendM = useMutation({
    mutationFn: async (id: number) => api.post(`/admin/users/${id}/suspend`),
    onSuccess: () => {
      toast.success(t('users.suspended'))
      qc.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })

  const activateM = useMutation({
    mutationFn: async (id: number) => api.post(`/admin/users/${id}/activate`),
    onSuccess: () => {
      toast.success(t('users.activated'))
      qc.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })

  const rows: AdminUser[] = q.data?.data ?? []

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Users className="h-8 w-8 text-primary-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.users')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">Admin</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={() => setShowAdd(true)}>
          <Plus className="h-4 w-4" />
          {t('common.create')}
        </button>
      </div>

      <div className="card p-4">
        <input
          type="search"
          className="input max-w-md"
          placeholder={t('common.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-lg w-full p-6 space-y-4 bg-white dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold">Kullanıcı</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  name: String(fd.get('name')),
                  email: String(fd.get('email')),
                  password: String(fd.get('password')),
                  password_confirmation: String(fd.get('password_confirmation')),
                  role: String(fd.get('role')),
                  locale: String(fd.get('locale') || 'tr'),
                })
              }}
            >
              <input name="name" className="input w-full" required placeholder="Ad" />
              <input name="email" type="email" className="input w-full" required placeholder="E-posta" />
              <input name="password" type="password" className="input w-full" required placeholder="Şifre" />
              <input
                name="password_confirmation"
                type="password"
                className="input w-full"
                required
                placeholder="Şifre tekrar"
              />
              <select name="role" className="input w-full" defaultValue="user">
                <option value="user">user</option>
                <option value="reseller">reseller</option>
                <option value="admin">admin</option>
              </select>
              <select name="locale" className="input w-full" defaultValue="tr">
                <option value="tr">tr</option>
                <option value="en">en</option>
              </select>
              <div className="flex justify-end gap-2">
                <button type="button" className="btn-secondary" onClick={() => setShowAdd(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createM.isPending}>
                  {t('common.create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">Ad</th>
              <th className="text-left px-4 py-2">E-posta</th>
              <th className="text-left px-4 py-2">Rol</th>
              <th className="text-left px-4 py-2">{t('common.status')}</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((u) => (
              <tr key={u.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2">{u.name}</td>
                <td className="px-4 py-2 font-mono text-xs">{u.email}</td>
                <td className="px-4 py-2">{u.roles?.map((r) => r.name).join(', ') ?? '—'}</td>
                <td className="px-4 py-2">{u.status}</td>
                <td className="px-4 py-2 text-right space-x-1">
                  {u.status === 'active' ? (
                    <button
                      type="button"
                      className="btn-secondary text-xs py-1"
                      onClick={() => suspendM.mutate(u.id)}
                      disabled={suspendM.isPending}
                    >
                      <UserX className="h-3 w-3 inline" /> Askı
                    </button>
                  ) : (
                    <button
                      type="button"
                      className="btn-secondary text-xs py-1"
                      onClick={() => activateM.mutate(u.id)}
                      disabled={activateM.isPending}
                    >
                      <UserCheck className="h-3 w-3 inline" /> Aktif
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {q.isLoading && <p className="p-6 text-center text-gray-500">{t('common.loading')}</p>}
        {!q.isLoading && rows.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>
    </div>
  )
}
