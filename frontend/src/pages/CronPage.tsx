import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Clock, Plus, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'

type CronRow = {
  id: number
  schedule: string
  command: string
  description: string | null
  status: string
  engine_job_id?: string | null
}

export default function CronPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showAdd, setShowAdd] = useState(false)

  const q = useQuery({
    queryKey: ['cron'],
    queryFn: async () => (await api.get('/cron')).data,
  })

  const createM = useMutation({
    mutationFn: async (payload: { schedule: string; command: string; description?: string }) =>
      api.post('/cron', payload),
    onSuccess: () => {
      toast.success(t('cron.created'))
      qc.invalidateQueries({ queryKey: ['cron'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/cron/${id}`),
    onSuccess: () => {
      toast.success(t('cron.deleted'))
      qc.invalidateQueries({ queryKey: ['cron'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const rows: CronRow[] = q.data?.data ?? []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Clock className="h-8 w-8 text-orange-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.cron')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('cron.subtitle')}</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={() => setShowAdd(true)}>
          <Plus className="h-4 w-4" />
          {t('common.create')}
        </button>
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-xl w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold">Cron görevi</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  schedule: String(fd.get('schedule') || '').trim(),
                  command: String(fd.get('command') || '').trim(),
                  description: String(fd.get('description') || '').trim() || undefined,
                })
              }}
            >
              <div>
                <label className="label">Zamanlama</label>
                <input
                  name="schedule"
                  className="input w-full font-mono"
                  required
                  placeholder="*/5 * * * *"
                />
              </div>
              <div>
                <label className="label">Komut</label>
                <textarea name="command" className="input w-full min-h-[80px] font-mono" required />
              </div>
              <div>
                <label className="label">Açıklama</label>
                <input name="description" className="input w-full" />
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
              <th className="text-left px-4 py-2">Zamanlama</th>
              <th className="text-left px-4 py-2">Komut</th>
              <th className="text-left px-4 py-2">Engine ID</th>
              <th className="text-left px-4 py-2">{t('common.status')}</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((job) => (
              <tr key={job.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 font-mono whitespace-nowrap">{job.schedule}</td>
                <td className="px-4 py-2 font-mono text-xs break-all max-w-md">{job.command}</td>
                <td className="px-4 py-2 font-mono text-xs text-gray-500">
                  {job.engine_job_id?.trim() ? job.engine_job_id : '—'}
                </td>
                <td className="px-4 py-2">{job.status}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                    onClick={() => {
                      if (window.confirm(t('common.confirm_delete'))) deleteM.mutate(job.id)
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
        {!q.isLoading && rows.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>
    </div>
  )
}
