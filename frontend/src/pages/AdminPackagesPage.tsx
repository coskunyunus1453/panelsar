import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Package, Plus, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'

type Pkg = {
  id: number
  name: string
  slug: string
  price_monthly: string | number
  price_yearly: string | number
  currency: string
  is_active: boolean
  max_domains: number
}

export default function AdminPackagesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [showAdd, setShowAdd] = useState(false)

  const q = useQuery({
    queryKey: ['admin-packages'],
    queryFn: async () => (await api.get('/admin/packages')).data as { packages: Pkg[] },
    enabled: !!isAdmin,
  })

  const createM = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => api.post('/admin/packages', payload),
    onSuccess: () => {
      toast.success(t('packages.created'))
      qc.invalidateQueries({ queryKey: ['admin-packages'] })
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

  const patchM = useMutation({
    mutationFn: async ({ id, body }: { id: number; body: Record<string, unknown> }) =>
      api.patch(`/admin/packages/${id}`, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-packages'] })
      toast.success(t('packages.updated'))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/admin/packages/${id}`),
    onSuccess: () => {
      toast.success(t('packages.deleted'))
      qc.invalidateQueries({ queryKey: ['admin-packages'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const packages = q.data?.packages ?? []

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Package className="h-8 w-8 text-primary-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.packages')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">Admin</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={() => setShowAdd(true)}>
          <Plus className="h-4 w-4" />
          {t('common.create')}
        </button>
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-lg w-full p-6 space-y-4 bg-white dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold">Hızlı paket</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const name = String(fd.get('name') || 'Paket')
                const slug = String(fd.get('slug') || `pkg-${Date.now()}`)
                createM.mutate({
                  name,
                  slug,
                  description: String(fd.get('description') || ''),
                  disk_space_mb: Number(fd.get('disk_space_mb') || 5000),
                  bandwidth_mb: Number(fd.get('bandwidth_mb') || 50000),
                  max_domains: Number(fd.get('max_domains') || 5),
                  max_subdomains: Number(fd.get('max_subdomains') || 10),
                  max_databases: Number(fd.get('max_databases') || 5),
                  max_email_accounts: Number(fd.get('max_email_accounts') || 10),
                  max_ftp_accounts: Number(fd.get('max_ftp_accounts') || 5),
                  max_cron_jobs: Number(fd.get('max_cron_jobs') || 5),
                  price_monthly: Number(fd.get('price_monthly') || 9.99),
                  price_yearly: Number(fd.get('price_yearly') || 99),
                  currency: String(fd.get('currency') || 'USD'),
                  ssl_enabled: true,
                  backup_enabled: true,
                })
              }}
            >
              <input name="name" className="input w-full" placeholder="Ad" required />
              <input name="slug" className="input w-full font-mono text-sm" placeholder="slug (benzersiz)" />
              <textarea name="description" className="input w-full min-h-[60px]" placeholder="Açıklama" />
              <div className="grid grid-cols-2 gap-2">
                <input name="price_monthly" type="number" step="0.01" className="input w-full" placeholder="Aylık" />
                <input name="price_yearly" type="number" step="0.01" className="input w-full" placeholder="Yıllık" />
              </div>
              <input name="currency" className="input w-full" placeholder="USD" defaultValue="USD" />
              <input name="max_domains" type="number" className="input w-full" placeholder="max_domains" defaultValue={5} />
              <input name="disk_space_mb" type="number" className="input w-full" placeholder="disk MB" defaultValue={5000} />
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
              <th className="text-left px-4 py-2">Fiyat (ay/yıl)</th>
              <th className="text-left px-4 py-2">Max alan adı</th>
              <th className="text-left px-4 py-2">Aktif</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {packages.map((p) => (
              <tr key={p.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 font-medium">{p.name}</td>
                <td className="px-4 py-2">
                  {p.price_monthly} / {p.price_yearly} {p.currency}
                </td>
                <td className="px-4 py-2">{p.max_domains}</td>
                <td className="px-4 py-2">
                  <button
                    type="button"
                    className={`text-xs px-2 py-1 rounded ${p.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200'}`}
                    onClick={() => patchM.mutate({ id: p.id, body: { is_active: !p.is_active } })}
                  >
                    {p.is_active ? 'on' : 'off'}
                  </button>
                </td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-red-50 text-gray-500"
                    onClick={() => {
                      if (window.confirm(t('common.confirm_delete'))) deleteM.mutate(p.id)
                    }}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {q.isLoading && <p className="p-6 text-center text-gray-500">{t('common.loading')}</p>}
        {!q.isLoading && packages.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>
    </div>
  )
}
