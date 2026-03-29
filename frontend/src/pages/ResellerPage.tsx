import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Store, Plus } from 'lucide-react'
import toast from 'react-hot-toast'

export default function ResellerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isReseller = user?.roles?.some((r) => r.name === 'reseller')
  const [showAdd, setShowAdd] = useState(false)

  const usersQ = useQuery({
    queryKey: ['reseller-users'],
    queryFn: async () => (await api.get('/reseller/users')).data,
    enabled: !!isReseller,
  })

  const pkgsQ = useQuery({
    queryKey: ['reseller-packages'],
    queryFn: async () =>
      (await api.get('/reseller/packages')).data as { packages: { id: number; name: string; slug: string }[] },
    enabled: !!isReseller,
  })

  const createUserM = useMutation({
    mutationFn: async (payload: {
      name: string
      email: string
      password: string
      password_confirmation: string
      role: string
    }) => api.post('/reseller/users', payload),
    onSuccess: () => {
      toast.success(t('reseller.user_created'))
      qc.invalidateQueries({ queryKey: ['reseller-users'] })
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

  if (!isReseller) {
    return <Navigate to="/dashboard" replace />
  }

  const userRows = (usersQ.data?.data ?? []) as {
    id: number
    name: string
    email: string
    status: string
  }[]

  const packages = pkgsQ.data?.packages ?? []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Store className="h-8 w-8 text-primary-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.reseller')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('reseller.subtitle')}</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={() => setShowAdd(true)}>
          <Plus className="h-4 w-4" />
          {t('reseller.new_subuser')}
        </button>
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('reseller.new_subuser')}</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createUserM.mutate({
                  name: String(fd.get('name') || ''),
                  email: String(fd.get('email') || ''),
                  password: String(fd.get('password') || ''),
                  password_confirmation: String(fd.get('password_confirmation') || ''),
                  role: 'user',
                })
              }}
            >
              <div>
                <label className="label">{t('auth.email')}</label>
                <input name="email" type="email" className="input w-full" required autoComplete="off" />
              </div>
              <div>
                <label className="label">Ad</label>
                <input name="name" type="text" className="input w-full" required />
              </div>
              <div>
                <label className="label">{t('auth.password')}</label>
                <input name="password" type="password" className="input w-full" required minLength={8} />
              </div>
              <div>
                <label className="label">{t('reseller.password_again')}</label>
                <input name="password_confirmation" type="password" className="input w-full" required minLength={8} />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" className="btn-secondary" onClick={() => setShowAdd(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createUserM.isPending}>
                  {t('common.create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="grid lg:grid-cols-2 gap-6">
        <div className="card overflow-hidden">
          <h2 className="text-lg font-semibold px-4 py-3 border-b border-gray-100 dark:border-gray-800">
            {t('reseller.subusers')}
          </h2>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-800/80">
              <tr>
                <th className="text-left px-4 py-2">Ad</th>
                <th className="text-left px-4 py-2">E-posta</th>
                <th className="text-left px-4 py-2">{t('common.status')}</th>
              </tr>
            </thead>
            <tbody>
              {userRows.map((u) => (
                <tr key={u.id} className="border-t border-gray-100 dark:border-gray-800">
                  <td className="px-4 py-2">{u.name}</td>
                  <td className="px-4 py-2 font-mono text-xs">{u.email}</td>
                  <td className="px-4 py-2">{u.status}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {usersQ.isLoading && <p className="p-4 text-gray-500">{t('common.loading')}</p>}
          {!usersQ.isLoading && userRows.length === 0 && (
            <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
          )}
        </div>

        <div className="card overflow-hidden">
          <h2 className="text-lg font-semibold px-4 py-3 border-b border-gray-100 dark:border-gray-800">
            {t('reseller.catalog')}
          </h2>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-800/80">
              <tr>
                <th className="text-left px-4 py-2">Ad</th>
                <th className="text-left px-4 py-2">Slug</th>
              </tr>
            </thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id} className="border-t border-gray-100 dark:border-gray-800">
                  <td className="px-4 py-2">{p.name}</td>
                  <td className="px-4 py-2 font-mono text-xs">{p.slug}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {pkgsQ.isLoading && <p className="p-4 text-gray-500">{t('common.loading')}</p>}
          {!pkgsQ.isLoading && packages.length === 0 && (
            <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
          )}
        </div>
      </div>
    </div>
  )
}
