import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Store, Search, Sparkles, ShieldCheck } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'

type ModuleRow = {
  id: number
  slug: string
  name: string
  summary?: string
  category: string
  version: string
  is_paid: boolean
  price_cents: number
  currency: string
  installed: boolean
  active: boolean
  config?: { source?: string }
}

type MigrationRun = {
  id: number
  plugin: { id: number; slug: string; name: string } | null
  source_type: string
  source_host: string
  status: string
  dry_run: boolean
  progress: number
  output?: string
  error_message?: string
}
type DomainRow = { id: number; name: string; document_root?: string }
type PreflightResponse = { ok: boolean; checks: { key: string; ok: boolean; message: string }[] }
type DiscoverResponse = {
  suggested_source_path?: string | null
  path_candidates?: { path: string; ok: boolean }[]
  db_names?: string[]
  db_users?: string[]
}

export default function PluginsStorePage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState<'all' | 'migration'>('all')
  const [runModuleId, setRunModuleId] = useState<number | null>(null)
  const [sourceHost, setSourceHost] = useState('')
  const [sourcePort, setSourcePort] = useState('22')
  const [sourceUser, setSourceUser] = useState('')
  const [sourcePath, setSourcePath] = useState('')
  const [targetDomainId, setTargetDomainId] = useState<number | ''>('')
  const [authType, setAuthType] = useState<'password' | 'token' | 'ssh_key'>('ssh_key')
  const [secret, setSecret] = useState('')
  const [dbName, setDbName] = useState('')
  const [dbUser, setDbUser] = useState('')
  const [dbPass, setDbPass] = useState('')
  const [dbHost, setDbHost] = useState('127.0.0.1')
  const [dbPort, setDbPort] = useState('3306')
  const [dryRun, setDryRun] = useState(true)
  const [preflight, setPreflight] = useState<PreflightResponse | null>(null)
  const [discoverData, setDiscoverData] = useState<DiscoverResponse | null>(null)

  const q = useQuery({
    queryKey: ['plugins-store'],
    queryFn: async () => (await api.get('/plugins/store')).data as { modules: ModuleRow[] },
  })
  const runsQ = useQuery({
    queryKey: ['plugins-migration-runs'],
    queryFn: async () => (await api.get('/plugins/migrations/runs')).data as { runs: MigrationRun[] },
    refetchInterval: 5000,
  })
  const domainsQ = useQuery({
    queryKey: ['domains-lite'],
    queryFn: async () => (await api.get('/domains')).data.data as DomainRow[],
  })

  const installM = useMutation({
    mutationFn: async (id: number) => api.post(`/plugins/${id}/install`),
    onSuccess: () => {
      toast.success(t('plugins.installed'))
      qc.invalidateQueries({ queryKey: ['plugins-store'] })
    },
  })

  const activateM = useMutation({
    mutationFn: async (id: number) => api.post(`/plugins/${id}/activate`),
    onSuccess: () => {
      toast.success(t('plugins.activated'))
      qc.invalidateQueries({ queryKey: ['plugins-store'] })
    },
  })

  const deactivateM = useMutation({
    mutationFn: async (id: number) => api.post(`/plugins/${id}/deactivate`),
    onSuccess: () => {
      toast.success(t('plugins.deactivated'))
      qc.invalidateQueries({ queryKey: ['plugins-store'] })
    },
  })
  const startMigrationM = useMutation({
    mutationFn: async (moduleId: number) =>
      api.post(`/plugins/${moduleId}/migrations/start`, {
        source_host: sourceHost,
        source_port: Number(sourcePort || 22),
        source_user: sourceUser,
        source_path: sourcePath,
        target_domain_id: Number(targetDomainId),
        auth_type: authType,
        password: authType === 'password' ? secret : null,
        api_token: authType === 'token' ? secret : null,
        ssh_private_key: authType === 'ssh_key' ? secret : null,
        source_db_name: dbName || null,
        source_db_user: dbUser || null,
        source_db_password: dbPass || null,
        source_db_host: dbHost || null,
        source_db_port: Number(dbPort || 3306),
        dry_run: dryRun,
      }),
    onSuccess: () => {
      toast.success(t('plugins.migration_started'))
      setRunModuleId(null)
      setSecret('')
      qc.invalidateQueries({ queryKey: ['plugins-migration-runs'] })
    },
  })
  const preflightM = useMutation({
    mutationFn: async (moduleId: number) =>
      (await api.post(`/plugins/${moduleId}/migrations/preflight`, {
        source_host: sourceHost,
        source_port: Number(sourcePort || 22),
        source_user: sourceUser,
        source_path: sourcePath,
        target_domain_id: Number(targetDomainId),
        auth_type: authType,
        ssh_private_key: authType === 'ssh_key' ? secret : null,
        source_db_name: dbName || null,
        source_db_user: dbUser || null,
        source_db_password: dbPass || null,
        source_db_host: dbHost || null,
        source_db_port: Number(dbPort || 3306),
      })).data as PreflightResponse,
    onSuccess: (data) => {
      setPreflight(data)
      toast.success(data.ok ? t('plugins.preflight_ok') : t('plugins.preflight_warn'))
    },
  })
  const discoverM = useMutation({
    mutationFn: async (moduleId: number) =>
      (await api.post(`/plugins/${moduleId}/migrations/discover`, {
        source_host: sourceHost,
        source_port: Number(sourcePort || 22),
        source_user: sourceUser,
        auth_type: 'ssh_key',
        ssh_private_key: secret,
      })).data as DiscoverResponse,
    onSuccess: (data) => {
      setDiscoverData(data)
      if (data.suggested_source_path) setSourcePath(data.suggested_source_path)
      if ((data.db_names?.length ?? 0) > 0 && !dbName) setDbName(data.db_names![0])
      if ((data.db_users?.length ?? 0) > 0 && !dbUser) setDbUser(data.db_users![0])
      toast.success(t('plugins.discover_done'))
    },
  })

  const modules = q.data?.modules ?? []
  const filtered = useMemo(() => {
    return modules.filter((m) => {
      if (category !== 'all' && m.category !== category) return false
      const s = search.trim().toLowerCase()
      if (!s) return true
      return (
        m.name.toLowerCase().includes(s) ||
        m.slug.toLowerCase().includes(s) ||
        (m.summary || '').toLowerCase().includes(s)
      )
    })
  }, [modules, category, search])

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Store className="h-8 w-8 text-violet-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.plugins_store')}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">{t('plugins.subtitle')}</p>
        </div>
      </div>

      <div className="card p-4 flex flex-wrap gap-3 items-center">
        <div className="relative min-w-[250px] flex-1">
          <Search className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            className="input w-full pl-9"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('plugins.search')}
          />
        </div>
        <div className="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
          <button
            type="button"
            className={`px-3 py-2 text-sm ${category === 'all' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'bg-white dark:bg-gray-900'}`}
            onClick={() => setCategory('all')}
          >
            {t('plugins.category_all')}
          </button>
          <button
            type="button"
            className={`px-3 py-2 text-sm border-l border-gray-200 dark:border-gray-700 ${category === 'migration' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'bg-white dark:bg-gray-900'}`}
            onClick={() => setCategory('migration')}
          >
            {t('plugins.category_migration')}
          </button>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {filtered.map((m) => (
          <div key={m.id} className="card p-5 flex flex-col gap-3 border border-gray-200 dark:border-gray-700">
            <div className="flex items-start justify-between gap-2">
              <div>
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">{m.name}</h3>
                <p className="text-xs text-gray-500">{m.slug} · v{m.version}</p>
              </div>
              {m.is_paid ? (
                <span className="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-1 dark:bg-amber-900/30 dark:text-amber-200">
                  {(m.price_cents / 100).toFixed(2)} {m.currency}
                </span>
              ) : (
                <span className="text-xs rounded-full bg-emerald-100 text-emerald-800 px-2 py-1 dark:bg-emerald-900/30 dark:text-emerald-200">
                  Free
                </span>
              )}
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-300">{m.summary}</p>
            <div className="mt-auto flex items-center gap-2">
              {m.active ? (
                <span className="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">
                  <ShieldCheck className="h-3.5 w-3.5" />
                  {t('plugins.active')}
                </span>
              ) : m.installed ? (
                <span className="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                  <Sparkles className="h-3.5 w-3.5" />
                  {t('plugins.installed_label')}
                </span>
              ) : null}
              <div className="ml-auto flex gap-2">
                {!m.installed ? (
                  <button type="button" className="btn-primary py-1.5 text-xs" onClick={() => installM.mutate(m.id)}>
                    {t('plugins.install')}
                  </button>
                ) : m.active ? (
                  <>
                    {m.category === 'migration' && (
                      <button
                        type="button"
                        className="btn-primary py-1.5 text-xs"
                        onClick={() => {
                          setRunModuleId(m.id)
                          setSourceHost('')
                          setSourcePort('22')
                          setSourceUser('')
                          setSourcePath('')
                          setTargetDomainId('')
                          setAuthType('ssh_key')
                          setSecret('')
                          setDbName('')
                          setDbUser('')
                          setDbPass('')
                          setDbHost('127.0.0.1')
                          setDbPort('3306')
                          setDryRun(true)
                          setPreflight(null)
                          setDiscoverData(null)
                        }}
                      >
                        {t('plugins.start_migration')}
                      </button>
                    )}
                    <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => deactivateM.mutate(m.id)}>
                      {t('plugins.deactivate')}
                    </button>
                  </>
                ) : (
                  <button type="button" className="btn-primary py-1.5 text-xs" onClick={() => activateM.mutate(m.id)}>
                    {t('plugins.activate')}
                  </button>
                )}
              </div>
            </div>
            {runModuleId === m.id && (
              <div className="rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-2">
                <input
                  className="input w-full"
                  placeholder={t('plugins.source_host')}
                  value={sourceHost}
                  onChange={(e) => setSourceHost(e.target.value)}
                />
                <input
                  className="input w-full"
                  placeholder={t('plugins.source_path')}
                  value={sourcePath}
                  onChange={(e) => setSourcePath(e.target.value)}
                />
                <select className="input w-full" value={targetDomainId} onChange={(e) => setTargetDomainId(e.target.value ? Number(e.target.value) : '')}>
                  <option value="">{t('plugins.target_domain')}</option>
                  {(domainsQ.data ?? []).map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
                <div className="grid grid-cols-2 gap-2">
                  <input
                    className="input w-full"
                    placeholder={t('plugins.source_port')}
                    value={sourcePort}
                    onChange={(e) => setSourcePort(e.target.value)}
                  />
                  <input
                    className="input w-full"
                    placeholder={t('plugins.source_user')}
                    value={sourceUser}
                    onChange={(e) => setSourceUser(e.target.value)}
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <select className="input w-full" value={authType} onChange={(e) => setAuthType(e.target.value as 'password' | 'token' | 'ssh_key')}>
                    <option value="password">{t('plugins.auth_password')}</option>
                    <option value="token">{t('plugins.auth_token')}</option>
                    <option value="ssh_key">{t('plugins.auth_ssh_key')}</option>
                  </select>
                  <input
                    type="password"
                    className="input w-full"
                    placeholder={t('plugins.secret')}
                    value={secret}
                    onChange={(e) => setSecret(e.target.value)}
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <input className="input w-full" placeholder={t('plugins.source_db_name')} value={dbName} onChange={(e) => setDbName(e.target.value)} />
                  <input className="input w-full" placeholder={t('plugins.source_db_user')} value={dbUser} onChange={(e) => setDbUser(e.target.value)} />
                  <input type="password" className="input w-full" placeholder={t('plugins.source_db_password')} value={dbPass} onChange={(e) => setDbPass(e.target.value)} />
                  <input className="input w-full" placeholder={t('plugins.source_db_host')} value={dbHost} onChange={(e) => setDbHost(e.target.value)} />
                </div>
                <input className="input w-full" placeholder={t('plugins.source_db_port')} value={dbPort} onChange={(e) => setDbPort(e.target.value)} />
                <label className="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                  <input type="checkbox" checked={dryRun} onChange={(e) => setDryRun(e.target.checked)} />
                  {t('plugins.dry_run')}
                </label>
                <div className="flex gap-2">
                  <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => discoverM.mutate(m.id)}>
                    {t('plugins.discover')}
                  </button>
                  <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => preflightM.mutate(m.id)}>
                    {t('plugins.preflight')}
                  </button>
                  <button type="button" className="btn-primary py-1.5 text-xs" onClick={() => startMigrationM.mutate(m.id)}>
                    {t('plugins.start')}
                  </button>
                  <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => setRunModuleId(null)}>
                    {t('common.cancel')}
                  </button>
                </div>
                {discoverData && (
                  <div className="rounded-md border border-gray-200 dark:border-gray-700 p-2 text-xs space-y-1">
                    <div className="text-gray-700 dark:text-gray-200">{t('plugins.discover_result')}</div>
                    {discoverData.suggested_source_path && <div>Path: {discoverData.suggested_source_path}</div>}
                    {!!discoverData.path_candidates?.length && (
                      <div className="text-gray-500">
                        {discoverData.path_candidates.map((c) => `${c.ok ? 'OK' : 'NO'} ${c.path}`).join(' | ')}
                      </div>
                    )}
                  </div>
                )}
                {preflight && (
                  <div className="rounded-md border border-gray-200 dark:border-gray-700 p-2 text-xs space-y-1">
                    <div className={preflight.ok ? 'text-emerald-600' : 'text-amber-600'}>
                      {preflight.ok ? t('plugins.preflight_ok') : t('plugins.preflight_warn')}
                    </div>
                    {preflight.checks.map((c) => (
                      <div key={c.key} className={c.ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'}>
                        {c.ok ? 'OK' : 'ERR'} - {c.message}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="card p-4">
        <h3 className="text-base font-semibold text-gray-900 dark:text-white mb-3">{t('plugins.migration_runs')}</h3>
        <div className="space-y-2">
          {(runsQ.data?.runs ?? []).map((r) => (
            <div key={r.id} className="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="font-semibold">{r.plugin?.name ?? '-'}</span>
                <span className="text-gray-500">{r.source_type}</span>
                <span className="text-gray-500">{r.source_host}</span>
                <span className="ml-auto">{r.status} ({r.progress}%)</span>
              </div>
              {r.error_message && <p className="mt-1 text-xs text-red-600">{r.error_message}</p>}
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
