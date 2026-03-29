import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Database, Plus, Search, Trash2, ExternalLink, KeyRound, Pencil } from 'lucide-react'
import toast from 'react-hot-toast'

type DbRow = {
  id: number
  name: string
  type: string
  username: string
  host: string
  grant_host?: string | null
  size_mb?: number | null
  status: string
}

const GRANT_HOST_OPTIONS = ['localhost', '127.0.0.1', '%'] as const

function grantHostSelectOptions(current?: string | null): string[] {
  const g = current?.trim()
  const base: string[] = [...GRANT_HOST_OPTIONS]
  if (g && !base.includes(g)) {
    base.push(g)
  }
  return base
}

export default function DatabasesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [showAdd, setShowAdd] = useState(false)
  const [createType, setCreateType] = useState('mysql')
  const [editAccessDb, setEditAccessDb] = useState<DbRow | null>(null)

  const databasesQ = useQuery({
    queryKey: ['databases', 'paginated'],
    queryFn: async () => (await api.get('/databases')).data,
  })

  const uiLinksQ = useQuery({
    queryKey: ['config-ui-links'],
    queryFn: async () =>
      (await api.get('/config/ui-links')).data as {
        phpmyadmin_url?: string
        adminer_url?: string
      },
  })

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const createM = useMutation({
    mutationFn: async (payload: {
      name: string
      type: string
      domain_id?: number
      grant_host?: string
    }) => {
      const { data } = await api.post('/databases', payload)
      return data as { password_plain?: string }
    },
    onSuccess: (data) => {
      const msg = data.password_plain
        ? `${t('databases.created')} — ${t('databases.password_once')}: ${data.password_plain}`
        : t('databases.created')
      toast.success(msg, { duration: data.password_plain ? 25_000 : 4000 })
      qc.invalidateQueries({ queryKey: ['databases'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const rotateM = useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post(`/databases/${id}/rotate-password`)
      return data as { password_plain?: string }
    },
    onSuccess: (data) => {
      const msg = data.password_plain
        ? `${t('databases.password_rotated')}: ${data.password_plain}`
        : t('databases.password_rotated')
      toast.success(msg, { duration: data.password_plain ? 25_000 : 4000 })
      qc.invalidateQueries({ queryKey: ['databases'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const patchAccessM = useMutation({
    mutationFn: async ({ id, grant_host }: { id: number; grant_host: string }) => {
      await api.patch(`/databases/${id}`, { grant_host })
    },
    onSuccess: () => {
      toast.success(t('databases.access_updated'))
      qc.invalidateQueries({ queryKey: ['databases'] })
      setEditAccessDb(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/databases/${id}`),
    onSuccess: () => {
      toast.success(t('databases.deleted'))
      qc.invalidateQueries({ queryKey: ['databases'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const list: DbRow[] = databasesQ.data?.data ?? []
  const total = (databasesQ.data?.total as number | undefined) ?? list.length
  const domainOptions: { id: number; name: string }[] = domainsQ.data?.data ?? []
  const filtered = list.filter((db) => db.name.toLowerCase().includes(search.toLowerCase()))

  const openDbWebUi = (db: DbRow) => {
    const php = uiLinksQ.data?.phpmyadmin_url?.trim() ?? ''
    const adm = uiLinksQ.data?.adminer_url?.trim() ?? ''
    if (db.type === 'mysql') {
      if (!php) {
        toast.error(
          t('databases.ui_url_missing') +
            ' PHPMYADMIN_URL (.env → panelsar.ui.phpmyadmin_url)',
        )
        return
      }
      window.open(php, '_blank', 'noopener,noreferrer')
      return
    }
    if (db.type === 'postgresql') {
      if (!adm) {
        toast.error(t('databases.ui_url_missing') + ' ADMINER_URL')
        return
      }
      window.open(adm, '_blank', 'noopener,noreferrer')
      return
    }
    toast.error(t('databases.no_web_ui_for_type'))
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {t('databases.title')}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            {total} {t('nav.databases').toLowerCase()}
          </p>
        </div>
        <button
          type="button"
          className="btn-primary flex items-center gap-2"
          onClick={() => {
            setCreateType('mysql')
            setShowAdd(true)
          }}
        >
          <Plus className="h-4 w-4" />
          {t('databases.add')}
        </button>
      </div>

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              {t('databases.new_title')}
            </h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const domainRaw = String(fd.get('domain_id') || '')
                const type = String(fd.get('type') || 'mysql')
                const gh = String(fd.get('grant_host') || '').trim()
                createM.mutate({
                  name: String(fd.get('name') || '').trim(),
                  type,
                  domain_id: domainRaw ? Number(domainRaw) : undefined,
                  ...(type === 'mysql' && gh ? { grant_host: gh } : {}),
                })
              }}
            >
              <div>
                <label className="label">{t('databases.name')}</label>
                <input name="name" className="input w-full" required placeholder="wordpress" />
              </div>
              <div>
                <label className="label">{t('databases.type')}</label>
                <select
                  name="type"
                  className="input w-full"
                  value={createType}
                  onChange={(e) => setCreateType(e.target.value)}
                >
                  <option value="mysql">MySQL</option>
                  <option value="postgresql">PostgreSQL</option>
                </select>
              </div>
              <div>
                <label className="label">{t('databases.optional_domain')}</label>
                <select name="domain_id" className="input w-full" defaultValue="">
                  <option value="">—</option>
                  {domainOptions.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </div>
              {createType === 'mysql' && (
                <div>
                  <label className="label">{t('databases.grant_host')}</label>
                  <select name="grant_host" className="input w-full" defaultValue="localhost">
                    {GRANT_HOST_OPTIONS.map((h) => (
                      <option key={h} value={h}>
                        {h}
                      </option>
                    ))}
                  </select>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {t('databases.grant_host_hint')}
                  </p>
                </div>
              )}
              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  className="btn-secondary"
                  onClick={() => setShowAdd(false)}
                >
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

      {editAccessDb && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              {t('databases.edit_access')}
            </h2>
            <p className="text-sm text-gray-600 dark:text-gray-400 font-mono">{editAccessDb.name}</p>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                patchAccessM.mutate({
                  id: editAccessDb.id,
                  grant_host: String(fd.get('grant_host') || ''),
                })
              }}
            >
              <div>
                <label className="label">{t('databases.grant_host')}</label>
                <select
                  name="grant_host"
                  className="input w-full"
                  defaultValue={editAccessDb.grant_host?.trim() || 'localhost'}
                >
                  {grantHostSelectOptions(editAccessDb.grant_host).map((h) => (
                    <option key={h} value={h}>
                      {h}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  className="btn-secondary"
                  onClick={() => setEditAccessDb(null)}
                >
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={patchAccessM.isPending}>
                  {t('common.save')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card">
        <div className="p-4 border-b border-gray-200 dark:border-panel-border">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('common.search')}
              className="input pl-10"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 dark:border-panel-border">
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('databases.name')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('databases.type')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('databases.username')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('databases.grant_host')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('databases.size')}
                </th>
                <th className="text-right px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('common.actions')}
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-panel-border">
              {databasesQ.isLoading && (
                <tr>
                  <td colSpan={6} className="px-6 py-8 text-center text-gray-500">
                    {t('common.loading')}
                  </td>
                </tr>
              )}
              {!databasesQ.isLoading &&
                filtered.map((db) => (
                  <tr
                    key={db.id}
                    className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <Database className="h-5 w-5 text-green-500" />
                        <span className="font-medium text-gray-900 dark:text-white">
                          {db.name}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span
                        className={`px-2.5 py-1 text-xs font-medium rounded-full ${
                          db.type === 'mysql'
                            ? 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400'
                            : 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400'
                        }`}
                      >
                        {db.type === 'mysql' ? 'MySQL' : 'PostgreSQL'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 font-mono">
                      {db.username}
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 font-mono">
                      {db.type === 'mysql' ? db.grant_host || 'localhost' : '—'}
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                      {db.size_mb != null ? `${db.size_mb} MB` : '—'}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {db.type === 'mysql' && (
                          <button
                            type="button"
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            title={t('databases.edit_access')}
                            onClick={() => setEditAccessDb(db)}
                          >
                            <Pencil className="h-4 w-4" />
                          </button>
                        )}
                        {(db.type === 'mysql' || db.type === 'postgresql') && (
                          <button
                            type="button"
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            title={t('databases.rotate_password')}
                            onClick={() => {
                              if (window.confirm(t('databases.rotate_password') + '?')) {
                                rotateM.mutate(db.id)
                              }
                            }}
                            disabled={rotateM.isPending}
                          >
                            <KeyRound className="h-4 w-4" />
                          </button>
                        )}
                        {(db.type === 'mysql' || db.type === 'postgresql') && (
                          <button
                            type="button"
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            title={
                              db.type === 'mysql'
                                ? t('databases.phpmyadmin')
                                : t('databases.adminer')
                            }
                            onClick={() => openDbWebUi(db)}
                          >
                            <ExternalLink className="h-4 w-4" />
                          </button>
                        )}
                        <button
                          type="button"
                          className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-500 hover:text-red-600"
                          onClick={() => {
                            if (window.confirm(t('common.confirm_delete'))) {
                              deleteM.mutate(db.id)
                            }
                          }}
                          disabled={deleteM.isPending}
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>

        {!databasesQ.isLoading && filtered.length === 0 && (
          <div className="text-center py-12 text-gray-500 dark:text-gray-400">
            {t('common.no_data')}
          </div>
        )}
      </div>
    </div>
  )
}
