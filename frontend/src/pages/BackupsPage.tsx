import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Shield, Plus, Trash2, RotateCcw } from 'lucide-react'
import toast from 'react-hot-toast'
import { useDomainsList } from '../hooks/useDomains'

type BackupRow = {
  id: number
  domain_id: number
  type: string
  status: string
  created_at: string
  engine_backup_id?: string | null
  domain?: { name: string }
}

export default function BackupsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [showAdd, setShowAdd] = useState(false)

  const q = useQuery({
    queryKey: ['backups'],
    queryFn: async () => (await api.get('/backups')).data,
  })

  const snapQ = useQuery({
    queryKey: ['backups-engine'],
    queryFn: async () => (await api.get('/backups/engine/snapshot')).data,
  })

  const createM = useMutation({
    mutationFn: async (payload: { domain_id: number; type: string }) =>
      api.post('/backups', payload),
    onSuccess: () => {
      toast.success(t('backups.queued'))
      qc.invalidateQueries({ queryKey: ['backups'] })
      qc.invalidateQueries({ queryKey: ['backups-engine'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/backups/${id}`),
    onSuccess: () => {
      toast.success(t('backups.deleted'))
      qc.invalidateQueries({ queryKey: ['backups'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const restoreM = useMutation({
    mutationFn: async (id: number) => api.post(`/backups/${id}/restore`),
    onSuccess: () => {
      toast.success(t('backups.restore_started'))
      qc.invalidateQueries({ queryKey: ['backups-engine'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const rows: BackupRow[] = q.data?.data ?? []
  const engineBackups: Record<string, unknown>[] = Array.isArray(snapQ.data?.remote)
    ? (snapQ.data.remote as Record<string, unknown>[])
    : []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Shield className="h-8 w-8 text-amber-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.backups')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('backups.subtitle')}</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={() => setShowAdd(true)}>
          <Plus className="h-4 w-4" />
          {t('common.create')}
        </button>
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold">Yedek kuyruğu</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  domain_id: Number(fd.get('domain_id')),
                  type: String(fd.get('type') || 'full'),
                })
              }}
            >
              <div>
                <label className="label">{t('domains.name')}</label>
                <select name="domain_id" className="input w-full" required>
                  <option value="">{t('common.select')}</option>
                  {(domainsQ.data ?? []).map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">Tip</label>
                <select name="type" className="input w-full" defaultValue="full">
                  <option value="full">full</option>
                  <option value="files">files</option>
                  <option value="database">database</option>
                </select>
              </div>
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
              <th className="text-left px-4 py-2">Alan adı</th>
              <th className="text-left px-4 py-2">Tip</th>
              <th className="text-left px-4 py-2">{t('common.status')}</th>
              <th className="text-left px-4 py-2">Tarih</th>
              <th className="text-right px-4 py-2">Engine</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((b) => (
              <tr key={b.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2">{b.domain?.name ?? b.domain_id}</td>
                <td className="px-4 py-2 font-mono">{b.type}</td>
                <td className="px-4 py-2">{b.status}</td>
                <td className="px-4 py-2 text-gray-500">{new Date(b.created_at).toLocaleString()}</td>
                <td className="px-4 py-2 text-right font-mono text-xs text-gray-500">
                  {b.engine_backup_id?.trim() ? b.engine_backup_id : '—'}
                </td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-500 mr-1"
                    title={t('backups.restore')}
                    disabled={restoreM.isPending || !b.engine_backup_id?.trim()}
                    onClick={() => {
                      if (!b.engine_backup_id?.trim()) {
                        toast.error(t('backups.restore_unavailable'))
                        return
                      }
                      if (window.confirm(t('backups.restore') + '?')) restoreM.mutate(b.id)
                    }}
                  >
                    <RotateCcw className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                    onClick={() => {
                      if (window.confirm(t('common.confirm_delete'))) deleteM.mutate(b.id)
                    }}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {q.isLoading && (
          <p className="p-6 text-center text-gray-500">{t('common.loading')}</p>
        )}
        {!q.isLoading && rows.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>

      <div className="card overflow-hidden">
        <h3 className="px-4 py-3 text-sm font-semibold border-b border-gray-100 dark:border-gray-800 text-gray-900 dark:text-white">
          Engine yedek listesi
        </h3>
        {snapQ.isLoading ? (
          <p className="p-4 text-gray-500 text-sm">{t('common.loading')}</p>
        ) : engineBackups.length === 0 ? (
          <p className="p-4 text-gray-500 text-sm">Engine tarafında kayıt yok veya erişilemedi.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-800/80">
              <tr>
                <th className="text-left px-4 py-2">ID</th>
                <th className="text-left px-4 py-2">Alan adı</th>
                <th className="text-left px-4 py-2">Tip</th>
                <th className="text-left px-4 py-2">{t('common.status')}</th>
                <th className="text-left px-4 py-2">Kuyruk</th>
              </tr>
            </thead>
            <tbody>
              {engineBackups.map((b, i) => (
                <tr key={String(b.id ?? i)} className="border-t border-gray-100 dark:border-gray-800">
                  <td className="px-4 py-2 font-mono text-xs">{b.id != null ? String(b.id) : '—'}</td>
                  <td className="px-4 py-2 font-mono">{b.domain != null ? String(b.domain) : '—'}</td>
                  <td className="px-4 py-2 font-mono">{b.type != null ? String(b.type) : '—'}</td>
                  <td className="px-4 py-2">{b.status != null ? String(b.status) : '—'}</td>
                  <td className="px-4 py-2 text-xs text-gray-500">
                    {b.queued_at != null ? String(b.queued_at) : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
