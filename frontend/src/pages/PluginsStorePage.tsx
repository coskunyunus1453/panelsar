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
  const [configModule, setConfigModule] = useState<ModuleRow | null>(null)
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
  const [wizardStep, setWizardStep] = useState<1 | 2 | 3>(1)
  const [preflight, setPreflight] = useState<PreflightResponse | null>(null)
  const [discoverData, setDiscoverData] = useState<DiscoverResponse | null>(null)
  const [sourcePreset, setSourcePreset] = useState<'cpanel' | 'plesk' | 'aapanel' | 'custom'>('custom')

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
      setConfigModule(null)
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

  const step1Missing = useMemo(() => {
    const missing: string[] = []
    if (!sourceHost.trim()) missing.push(t('plugins.source_host'))
    if (!sourcePath.trim()) missing.push(t('plugins.source_path'))
    if (targetDomainId === '') missing.push(t('plugins.target_domain'))
    if (!sourceUser.trim()) missing.push(t('plugins.source_user'))
    if (!sourcePort.trim()) missing.push(t('plugins.source_port'))
    if (!secret.trim()) missing.push(t('plugins.secret'))
    return missing
  }, [secret, sourceHost, sourcePath, sourcePort, sourceUser, targetDomainId, t])

  const step2HasBlockingError = useMemo(() => preflight !== null && preflight.ok === false, [preflight])
  const requiredFieldSet = useMemo(() => new Set(step1Missing), [step1Missing])

  const goStep2 = () => {
    if (step1Missing.length > 0) {
      toast.error(`${t('plugins.required_missing')}: ${step1Missing.join(', ')}`)
      return
    }
    setWizardStep(2)
  }

  const goStep3 = () => {
    if (step1Missing.length > 0) {
      toast.error(`${t('plugins.required_missing')}: ${step1Missing.join(', ')}`)
      setWizardStep(1)
      return
    }
    if (step2HasBlockingError) {
      toast.error(t('plugins.preflight_required_ok'))
      return
    }
    setWizardStep(3)
  }

  const startMigrationNow = (moduleId: number) => {
    if (step1Missing.length > 0) {
      toast.error(`${t('plugins.required_missing')}: ${step1Missing.join(', ')}`)
      setWizardStep(1)
      return
    }
    if (step2HasBlockingError) {
      toast.error(t('plugins.preflight_required_ok'))
      setWizardStep(2)
      return
    }
    startMigrationM.mutate(moduleId)
  }

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
      <div className="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900/40 dark:bg-violet-950/20 dark:text-violet-200">
        <p className="font-semibold">{t('plugins.migration_assistant_title')}</p>
        <p className="mt-1 text-xs">{t('plugins.migration_assistant_hint')}</p>
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
            {m.category === 'migration' && (
              <p className="text-xs text-gray-500 dark:text-gray-400">{t('plugins.migration_card_hint')}</p>
            )}
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
                          setConfigModule(m)
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
                          setSourcePreset('custom')
                          setWizardStep(1)
                        }}
                      >
                        {t('plugins.configure')}
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
            
          </div>
        ))}
      </div>

      {configModule && (
        <div className="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-3xl rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div className="mb-4 flex items-start justify-between gap-3">
              <div>
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                  {t('plugins.modal_title', { name: configModule.name })}
                </h3>
                <p className="text-xs text-gray-500">{configModule.slug}</p>
              </div>
              <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => setConfigModule(null)}>
                {t('common.close')}
              </button>
            </div>
            <div className="space-y-2">
              <div className="mb-2 flex items-center gap-2 text-xs">
                <button type="button" className={`rounded-md px-2 py-1 ${wizardStep === 1 ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'}`} onClick={() => setWizardStep(1)}>{t('plugins.wizard_step_1')}</button>
                <button type="button" className={`rounded-md px-2 py-1 ${wizardStep === 2 ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'}`} onClick={() => setWizardStep(2)}>{t('plugins.wizard_step_2')}</button>
                <button type="button" className={`rounded-md px-2 py-1 ${wizardStep === 3 ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'}`} onClick={() => setWizardStep(3)}>{t('plugins.wizard_step_3')}</button>
              </div>
              {wizardStep === 1 && (
                <>
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs text-gray-500">{t('plugins.source_preset')}:</span>
                <button type="button" className={`rounded-md border px-2 py-1 text-xs ${sourcePreset === 'cpanel' ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'border-gray-200 dark:border-gray-700'}`} onClick={() => { setSourcePreset('cpanel'); setSourcePort('22'); if (!sourcePath) setSourcePath('/home/USER/public_html') }}>cPanel</button>
                <button type="button" className={`rounded-md border px-2 py-1 text-xs ${sourcePreset === 'plesk' ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'border-gray-200 dark:border-gray-700'}`} onClick={() => { setSourcePreset('plesk'); setSourcePort('22'); if (!sourcePath) setSourcePath('/var/www/vhosts/example.com/httpdocs') }}>Plesk</button>
                <button type="button" className={`rounded-md border px-2 py-1 text-xs ${sourcePreset === 'aapanel' ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'border-gray-200 dark:border-gray-700'}`} onClick={() => { setSourcePreset('aapanel'); setSourcePort('22'); if (!sourcePath) setSourcePath('/www/wwwroot/example.com') }}>aaPanel</button>
              </div>
              <input className={`input w-full ${requiredFieldSet.has(t('plugins.source_host')) ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''}`} placeholder={t('plugins.source_host')} value={sourceHost} onChange={(e) => setSourceHost(e.target.value)} />
              {requiredFieldSet.has(t('plugins.source_host')) && <p className="text-xs text-red-600">{t('plugins.required_field_hint')}</p>}
              <input className={`input w-full ${requiredFieldSet.has(t('plugins.source_path')) ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''}`} placeholder={t('plugins.source_path')} value={sourcePath} onChange={(e) => setSourcePath(e.target.value)} />
              {requiredFieldSet.has(t('plugins.source_path')) && <p className="text-xs text-red-600">{t('plugins.required_field_hint')}</p>}
              <select className={`input w-full ${requiredFieldSet.has(t('plugins.target_domain')) ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''}`} value={targetDomainId} onChange={(e) => setTargetDomainId(e.target.value ? Number(e.target.value) : '')}>
                <option value="">{t('plugins.target_domain')}</option>
                {(domainsQ.data ?? []).map((d) => (<option key={d.id} value={d.id}>{d.name}</option>))}
              </select>
              {requiredFieldSet.has(t('plugins.target_domain')) && <p className="text-xs text-red-600">{t('plugins.required_field_hint')}</p>}
              <div className="grid grid-cols-2 gap-2">
                <input className={`input w-full ${requiredFieldSet.has(t('plugins.source_port')) ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''}`} placeholder={t('plugins.source_port')} value={sourcePort} onChange={(e) => setSourcePort(e.target.value)} />
                <input className={`input w-full ${requiredFieldSet.has(t('plugins.source_user')) ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''}`} placeholder={t('plugins.source_user')} value={sourceUser} onChange={(e) => setSourceUser(e.target.value)} />
              </div>
              {(requiredFieldSet.has(t('plugins.source_port')) || requiredFieldSet.has(t('plugins.source_user'))) && (
                <p className="text-xs text-red-600">{t('plugins.required_field_hint')}</p>
              )}
              <div className="grid grid-cols-2 gap-2">
                <select className="input w-full" value={authType} onChange={(e) => setAuthType(e.target.value as 'password' | 'token' | 'ssh_key')}>
                  <option value="password">{t('plugins.auth_password')}</option>
                  <option value="token">{t('plugins.auth_token')}</option>
                  <option value="ssh_key">{t('plugins.auth_ssh_key')}</option>
                </select>
                <input type="password" className={`input w-full ${requiredFieldSet.has(t('plugins.secret')) ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''}`} placeholder={t('plugins.secret')} value={secret} onChange={(e) => setSecret(e.target.value)} />
              </div>
              {requiredFieldSet.has(t('plugins.secret')) && <p className="text-xs text-red-600">{t('plugins.required_field_hint')}</p>}
              <div className="flex justify-end">
                <button type="button" className="btn-primary py-1.5 text-xs" onClick={goStep2}>{t('plugins.next')}</button>
              </div>
                </>
              )}
              {wizardStep === 2 && (
                <>
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
              <div className="flex flex-wrap gap-2">
                <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => discoverM.mutate(configModule.id)}>{t('plugins.discover')}</button>
                <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => preflightM.mutate(configModule.id)}>{t('plugins.preflight')}</button>
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
              <div className="flex justify-between">
                <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => setWizardStep(1)}>{t('plugins.back')}</button>
                <button type="button" className="btn-primary py-1.5 text-xs" onClick={goStep3}>{t('plugins.next')}</button>
              </div>
                </>
              )}
              {wizardStep === 3 && (
                <div className="space-y-3">
                  <p className="text-xs text-gray-500">{t('plugins.wizard_review_hint')}</p>
                  <div className="rounded-md border border-gray-200 p-2 text-xs dark:border-gray-700">
                    <div>{t('plugins.source_host')}: {sourceHost || '-'}</div>
                    <div>{t('plugins.source_path')}: {sourcePath || '-'}</div>
                    <div>{t('plugins.target_domain')}: {targetDomainId || '-'}</div>
                  </div>
                  <div className="flex justify-between">
                    <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => setWizardStep(2)}>{t('plugins.back')}</button>
                    <div className="flex gap-2">
                      <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => setConfigModule(null)}>{t('common.cancel')}</button>
                      <button type="button" className="btn-primary py-1.5 text-xs" onClick={() => startMigrationNow(configModule.id)}>{t('plugins.start')}</button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

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
