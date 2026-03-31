import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Shield, Plus, Trash2, RotateCcw, Play, Server } from 'lucide-react'
import toast from 'react-hot-toast'
import { useDomainsList } from '../hooks/useDomains'

type BackupRow = {
  id: number
  domain_id: number
  destination_id?: number | null
  type: string
  status: string
  created_at: string
  file_path?: string | null
  engine_backup_id?: string | null
  domain?: { name: string }
}

type DestinationRow = {
  id: number
  name: string
  driver: 'local' | 's3' | 'ftp'
  is_default?: boolean
  is_active?: boolean
}

type ScheduleRow = {
  id: number
  domain_id: number
  destination_id?: number | null
  type: string
  schedule: string
  enabled: boolean
  last_run_at?: string | null
  domain?: { id: number; name: string }
  destination?: { id: number; name: string; driver: string }
}

export default function BackupsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [showAdd, setShowAdd] = useState(false)
  const [showDest, setShowDest] = useState(false)
  const [showSchedule, setShowSchedule] = useState(false)

  const q = useQuery({
    queryKey: ['backups'],
    queryFn: async () => (await api.get('/backups')).data,
  })

  const snapQ = useQuery({
    queryKey: ['backups-engine'],
    queryFn: async () => (await api.get('/backups/engine/snapshot')).data,
  })

  const destQ = useQuery({
    queryKey: ['backup-destinations'],
    queryFn: async () => (await api.get('/backups/destinations')).data as { destinations: DestinationRow[] },
  })

  const scheduleQ = useQuery({
    queryKey: ['backup-schedules'],
    queryFn: async () => (await api.get('/backups/schedules')).data as { schedules: ScheduleRow[] },
  })

  const createM = useMutation({
    mutationFn: async (payload: { domain_id: number; type: string; destination_id?: number }) =>
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
    mutationFn: async (payload: { id: number; source?: 'engine' | 'remote'; destination_id?: number; backup_set?: string }) =>
      api.post(`/backups/${payload.id}/restore`, {
        source: payload.source ?? 'engine',
        destination_id: payload.destination_id,
        backup_set: payload.backup_set,
      }),
    onSuccess: () => {
      toast.success(t('backups.restore_started'))
      qc.invalidateQueries({ queryKey: ['backups-engine'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const syncM = useMutation({
    mutationFn: async (id: number) => api.post(`/backups/${id}/sync`),
    onSuccess: () => toast.success(t('backups.synced')),
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const createDestM = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => api.post('/backups/destinations', payload),
    onSuccess: () => {
      toast.success(t('backups.destination_saved'))
      qc.invalidateQueries({ queryKey: ['backup-destinations'] })
      setShowDest(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteDestM = useMutation({
    mutationFn: async (id: number) => api.delete(`/backups/destinations/${id}`),
    onSuccess: () => {
      toast.success(t('backups.deleted'))
      qc.invalidateQueries({ queryKey: ['backup-destinations'] })
    },
  })

  const createScheduleM = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => api.post('/backups/schedules', payload),
    onSuccess: () => {
      toast.success(t('backups.schedule_saved'))
      qc.invalidateQueries({ queryKey: ['backup-schedules'] })
      setShowSchedule(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const runScheduleM = useMutation({
    mutationFn: async (id: number) => api.post(`/backups/schedules/${id}/run`),
    onSuccess: () => {
      toast.success(t('backups.queued'))
      qc.invalidateQueries({ queryKey: ['backups'] })
      qc.invalidateQueries({ queryKey: ['backup-schedules'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const rows: BackupRow[] = q.data?.data ?? []
  const destinations = destQ.data?.destinations ?? []
  const schedules = scheduleQ.data?.schedules ?? []
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

      <div className="grid gap-3 md:grid-cols-2">
        <button type="button" className="card p-4 text-left hover:bg-gray-50 dark:hover:bg-gray-800/30" onClick={() => setShowDest(true)}>
          <div className="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <Server className="h-4 w-4 text-indigo-500" />
            {t('backups.destinations')}
          </div>
          <p className="mt-1 text-xs text-gray-500">{t('backups.destinations_hint')}</p>
        </button>
        <button type="button" className="card p-4 text-left hover:bg-gray-50 dark:hover:bg-gray-800/30" onClick={() => setShowSchedule(true)}>
          <div className="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <Play className="h-4 w-4 text-emerald-500" />
            {t('backups.schedules')}
          </div>
          <p className="mt-1 text-xs text-gray-500">{t('backups.schedules_hint')}</p>
        </button>
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold">{t('backups.new_backup')}</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  domain_id: Number(fd.get('domain_id')),
                  type: String(fd.get('type') || 'full'),
                  ...(fd.get('destination_id') ? { destination_id: Number(fd.get('destination_id')) } : {}),
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
              <div>
                <label className="label">{t('backups.destination')}</label>
                <select name="destination_id" className="input w-full">
                  <option value="">{t('common.select')}</option>
                  {destinations.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name} ({d.driver})
                    </option>
                  ))}
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

      {showDest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-2xl w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold">{t('backups.destinations')}</h2>
            <form
              className="grid gap-3 md:grid-cols-2"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const driver = String(fd.get('driver') || 'local')
                const cfg: Record<string, string> = {}
                if (driver === 's3') {
                  cfg.bucket = String(fd.get('bucket') || '')
                  cfg.region = String(fd.get('region') || '')
                  cfg.access_key = String(fd.get('access_key') || '')
                  cfg.secret_key = String(fd.get('secret_key') || '')
                } else if (driver === 'ftp') {
                  cfg.host = String(fd.get('host') || '')
                  cfg.username = String(fd.get('username') || '')
                  cfg.password = String(fd.get('password') || '')
                  cfg.path = String(fd.get('path') || '')
                } else {
                  cfg.path = String(fd.get('path') || '')
                }
                createDestM.mutate({
                  name: String(fd.get('name') || '').trim(),
                  driver,
                  is_default: fd.get('is_default') === 'on',
                  config: cfg,
                })
              }}
            >
              <input name="name" className="input w-full" placeholder={t('backups.destination_name')} required />
              <select name="driver" className="input w-full" defaultValue="local">
                <option value="local">local</option>
                <option value="s3">s3</option>
                <option value="ftp">ftp</option>
              </select>
              <input name="path" className="input w-full md:col-span-2" placeholder="Local path veya remote path" />
              <input name="bucket" className="input w-full" placeholder="S3 bucket" />
              <input name="region" className="input w-full" placeholder="S3 region" />
              <input name="access_key" className="input w-full" placeholder="S3 access key" />
              <input name="secret_key" className="input w-full" placeholder="S3 secret key" />
              <input name="host" className="input w-full" placeholder="FTP host" />
              <input name="username" className="input w-full" placeholder="FTP username" />
              <input name="password" className="input w-full" placeholder="FTP password" />
              <label className="md:col-span-2 inline-flex items-center gap-2 text-sm">
                <input name="is_default" type="checkbox" />
                {t('backups.default_destination')}
              </label>
              <div className="md:col-span-2 flex justify-end gap-2">
                <button type="button" className="btn-secondary" onClick={() => setShowDest(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createDestM.isPending}>
                  {t('common.save')}
                </button>
              </div>
            </form>
            <div className="space-y-2">
              {destinations.map((d) => (
                <div key={d.id} className="flex items-center justify-between rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                  <p className="text-sm">
                    {d.name} <span className="text-xs text-gray-500">({d.driver})</span>
                  </p>
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                    onClick={() => {
                      if (window.confirm(t('common.confirm_delete'))) deleteDestM.mutate(d.id)
                    }}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {showSchedule && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-2xl w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold">{t('backups.schedules')}</h2>
            <form
              className="grid gap-3 md:grid-cols-2"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createScheduleM.mutate({
                  domain_id: Number(fd.get('domain_id')),
                  type: String(fd.get('type') || 'full'),
                  schedule: String(fd.get('schedule') || '0 3 * * *'),
                  ...(fd.get('destination_id') ? { destination_id: Number(fd.get('destination_id')) } : {}),
                  enabled: fd.get('enabled') === 'on',
                })
              }}
            >
              <select name="domain_id" className="input w-full" required>
                <option value="">{t('domains.name')}</option>
                {(domainsQ.data ?? []).map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              <select name="destination_id" className="input w-full">
                <option value="">{t('backups.destination')}</option>
                {destinations.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              <select name="type" className="input w-full" defaultValue="full">
                <option value="full">full</option>
                <option value="files">files</option>
                <option value="database">database</option>
              </select>
              <input name="schedule" className="input w-full" defaultValue="0 3 * * *" placeholder="0 3 * * *" required />
              <label className="md:col-span-2 inline-flex items-center gap-2 text-sm">
                <input name="enabled" type="checkbox" defaultChecked />
                {t('common.status')} aktif
              </label>
              <div className="md:col-span-2 flex justify-end gap-2">
                <button type="button" className="btn-secondary" onClick={() => setShowSchedule(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createScheduleM.isPending}>
                  {t('common.save')}
                </button>
              </div>
            </form>
            <div className="space-y-2">
              {schedules.map((s) => (
                <div key={s.id} className="flex items-center justify-between rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                  <div className="text-sm">
                    <p>{s.domain?.name} · {s.type} · <code>{s.schedule}</code></p>
                    <p className="text-xs text-gray-500">{s.destination?.name ?? 'local'} · {s.enabled ? 'aktif' : 'pasif'}</p>
                  </div>
                  <div className="flex items-center gap-1">
                    <button type="button" className="p-1.5 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20" onClick={() => runScheduleM.mutate(s.id)}>
                      <Play className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
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
                      const source = window.prompt('Restore source: engine / remote', 'engine')?.trim().toLowerCase()
                      if (!source) return
                      if (source === 'remote') {
                        const didRaw = window.prompt('Destination ID', String(b.destination_id ?? ''))
                        const set = window.prompt('Backup set/path', b.file_path ?? '')
                        if (!didRaw || !set) return
                        const did = Number(didRaw)
                        if (!Number.isFinite(did) || did <= 0) {
                          toast.error(t('backups.remote_restore_missing'))
                          return
                        }
                        restoreM.mutate({ id: b.id, source: 'remote', destination_id: did, backup_set: set })
                      } else {
                        restoreM.mutate({ id: b.id, source: 'engine' })
                      }
                    }}
                  >
                    <RotateCcw className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-gray-500 mr-1"
                    title={t('backups.synced')}
                    disabled={syncM.isPending}
                    onClick={() => syncM.mutate(b.id)}
                  >
                    <Server className="h-4 w-4" />
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
          {t('backups.engine_list')}
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
