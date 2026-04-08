import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api, { apiBaseUrl } from '../services/api'
import { useAuthStore } from '../store/authStore'
import { tokenHasAbility } from '../lib/abilities'
import i18n from '../i18n'
import {
  Database,
  Plus,
  Search,
  Trash2,
  ExternalLink,
  KeyRound,
  Pencil,
  Download,
  Upload,
} from 'lucide-react'
import toast from 'react-hot-toast'
import { safeExternalHttpUrl } from '../lib/urlSafety'

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
  const abilities = useAuthStore((s) => s.user?.abilities)
  const canImportDb = tokenHasAbility(abilities, 'databases:write')
  const [search, setSearch] = useState('')
  const [showAdd, setShowAdd] = useState(false)
  const [createType, setCreateType] = useState('mysql')
  const [editAccessDb, setEditAccessDb] = useState<DbRow | null>(null)
  const [importDb, setImportDb] = useState<DbRow | null>(null)

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

  const phpmyadminUrl = uiLinksQ.data?.phpmyadmin_url?.trim() ?? ''
  const adminerUrl = uiLinksQ.data?.adminer_url?.trim() ?? ''

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

  const importSqlM = useMutation({
    mutationFn: async ({ id, file, confirmation }: { id: number; file: File; confirmation: string }) => {
      const fd = new FormData()
      fd.append('sql_file', file)
      fd.append('confirmation', confirmation)
      const { data } = await api.post<{ message?: string }>(`/databases/${id}/import`, fd)
      return data
    },
    onSuccess: (data) => {
      toast.success(data?.message ?? t('databases.import_started'))
      qc.invalidateQueries({ queryKey: ['databases'] })
      setImportDb(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const runExport = async (db: DbRow) => {
    const token = useAuthStore.getState().token
    const locale = (i18n.language || 'en').split('-')[0]
    try {
      const res = await fetch(`${apiBaseUrl}/databases/${db.id}/export`, {
        headers: {
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          Accept: '*/*',
          'X-Locale': locale,
        },
      })
      if (!res.ok) {
        const ct = res.headers.get('content-type') || ''
        let msg = t('databases.export_failed')
        if (ct.includes('json')) {
          const j = (await res.json().catch(() => ({}))) as { message?: string }
          if (j.message) msg = String(j.message)
        } else {
          const txt = await res.text().catch(() => '')
          if (txt) msg = txt.slice(0, 240)
        }
        toast.error(msg)
        return
      }
      const blob = await res.blob()
      const cd = res.headers.get('content-disposition')
      let fn = `${db.name.replace(/[^\w.-]+/g, '_')}.sql`
      const m = cd?.match(/filename\*?=(?:UTF-8'')?["']?([^"';]+)/i) ?? cd?.match(/filename="([^"]+)"/i)
      if (m?.[1]) fn = decodeURIComponent(m[1].trim())
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = fn
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.error(t('databases.export_failed'))
    }
  }

  const list: DbRow[] = databasesQ.data?.data ?? []
  const total = (databasesQ.data?.total as number | undefined) ?? list.length
  const domainOptions: { id: number; name: string }[] = domainsQ.data?.data ?? []
  const filtered = list.filter((db) => db.name.toLowerCase().includes(search.toLowerCase()))

  const openDbWebUi = (db: DbRow) => {
    const php = safeExternalHttpUrl(phpmyadminUrl)
    const adm = safeExternalHttpUrl(adminerUrl)
    if (db.type === 'mysql') {
      if (!php) {
        toast.error(
          t('databases.ui_url_missing') +
            ' PHPMYADMIN_URL (.env → hostvim.ui.phpmyadmin_url)',
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

      {importDb && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-lg w-full p-6 space-y-4 bg-white dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold text-red-700 dark:text-red-400">
              {t('databases.import_warning_title')}
            </h2>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('databases.import_warning_body')}</p>
            <p className="text-sm font-medium text-gray-800 dark:text-gray-200">
              {t('databases.import_confirm_hint', { phrase: t('databases.import_confirm_expected') })}
            </p>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const file = (fd.get('sql_file') as File | null) ?? null
                const confirmation = String(fd.get('confirmation') || '').trim()
                if (!file || file.size === 0) {
                  toast.error(t('databases.import_choose_file'))
                  return
                }
                const expected = t('databases.import_confirm_expected')
                if (confirmation !== expected) {
                  toast.error(t('databases.import_confirm_mismatch'))
                  return
                }
                importSqlM.mutate({ id: importDb.id, file, confirmation })
              }}
            >
              <div>
                <label className="label">{t('databases.import_choose_file')}</label>
                <input name="sql_file" type="file" accept=".sql,text/plain,application/sql" className="input w-full" required />
              </div>
              <div>
                <label className="label">{t('databases.import_confirm_label')}</label>
                <input name="confirmation" type="text" className="input w-full font-mono" autoComplete="off" required />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  className="btn-secondary"
                  onClick={() => setImportDb(null)}
                  disabled={importSqlM.isPending}
                >
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary bg-red-600 hover:bg-red-700 border-red-600" disabled={importSqlM.isPending}>
                  {t('databases.import_sql')}
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
                            : 'bg-secondary-50 dark:bg-secondary-900/20 text-secondary-700 dark:text-secondary-400'
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
                            title={t('databases.export_sql')}
                            onClick={() => runExport(db)}
                          >
                            <Download className="h-4 w-4" />
                          </button>
                        )}
                        {canImportDb && (db.type === 'mysql' || db.type === 'postgresql') && (
                          <button
                            type="button"
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            title={t('databases.import_sql')}
                            onClick={() => setImportDb(db)}
                          >
                            <Upload className="h-4 w-4" />
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
                        {db.type === 'mysql' && phpmyadminUrl && (
                          <button
                            type="button"
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            title={
                              t('databases.phpmyadmin')
                            }
                            onClick={() => openDbWebUi(db)}
                          >
                            <ExternalLink className="h-4 w-4" />
                          </button>
                        )}

                        {db.type === 'postgresql' && adminerUrl && (
                          <button
                            type="button"
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            title={t('databases.adminer')}
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
