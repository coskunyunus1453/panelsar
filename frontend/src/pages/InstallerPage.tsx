import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Download, Loader2 } from 'lucide-react'
import { useDomainsList } from '../hooks/useDomains'
import { notify } from '../lib/notify'
import { Link } from 'react-router-dom'

type AppCategory = 'kobi' | 'agency' | 'modern' | 'other'
type AppRow = {
  id: string
  name: string
  version: string
  automated?: boolean
  category?: AppCategory
  route?: string
  supports_woocommerce?: boolean
}
type InstallerRun = {
  id: number
  app: string
  status: 'queued' | 'running' | 'success' | 'failed'
  message?: string
  created_at?: string
}
type InstallerRunDetail = InstallerRun & { output?: string; started_at?: string; finished_at?: string }
type InstallerDiagnostics = { ok: boolean; checks: { key: string; ok: boolean; message: string }[] }

const FALLBACK_APPS: AppRow[] = [
  { id: 'wordpress', name: 'WordPress', version: 'latest', automated: true, category: 'kobi', supports_woocommerce: true },
  { id: 'opencart', name: 'OpenCart', version: '4.0.x', automated: true, category: 'kobi' },
  { id: 'nodejs', name: 'Node.js', version: '', automated: false, category: 'agency', route: '/deploy' },
  { id: 'laravel', name: 'Laravel', version: '11.x', automated: false, category: 'agency', route: '/deploy' },
  { id: 'docker', name: 'Docker', version: '', automated: false, category: 'agency', route: '/site-tools' },
  { id: 'git_deploy', name: 'Git deploy', version: '', automated: false, category: 'agency', route: '/deploy' },
  { id: 'nextjs', name: 'Next.js starter', version: '', automated: false, category: 'modern', route: '/deploy' },
  { id: 'strapi', name: 'Strapi', version: '', automated: false, category: 'modern', route: '/deploy' },
  { id: 'n8n', name: 'n8n', version: '', automated: false, category: 'modern', route: '/site-tools' },
  { id: 'joomla', name: 'Joomla', version: 'latest', automated: false, category: 'other' },
  { id: 'drupal', name: 'Drupal', version: '10.x', automated: false, category: 'other' },
  { id: 'prestashop', name: 'PrestaShop', version: '8.x', automated: false, category: 'other' },
]

const CATEGORY_ORDER: AppCategory[] = ['kobi', 'agency', 'modern', 'other']

function categoryLabel(t: (k: string) => string, c: AppCategory): string {
  switch (c) {
    case 'kobi':
      return t('installer.cat_kobi')
    case 'agency':
      return t('installer.cat_agency')
    case 'modern':
      return t('installer.cat_modern')
    default:
      return t('installer.cat_other')
  }
}

export default function InstallerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [wpDatabaseId, setWpDatabaseId] = useState<number | ''>('')
  const [tablePrefix, setTablePrefix] = useState('wp_')
  const [installWoo, setInstallWoo] = useState(false)
  const [activeRunId, setActiveRunId] = useState<number | null>(null)
  const [seenFinalRuns, setSeenFinalRuns] = useState<number[]>([])
  const [detailRunId, setDetailRunId] = useState<number | null>(null)
  const [diagData, setDiagData] = useState<InstallerDiagnostics | null>(null)
  const [guideApp, setGuideApp] = useState<AppRow | null>(null)
  const [docrootVariant, setDocrootVariant] = useState<'root' | 'public'>('root')

  const databasesQ = useQuery({
    queryKey: ['databases', 'paginated'],
    queryFn: async () => (await api.get('/databases')).data as { data?: { id: number; name: string; type: string }[] },
  })

  const q = useQuery({
    queryKey: ['installer-apps'],
    queryFn: async () => (await api.get('/installer/apps')).data as { apps: AppRow[] },
  })
  const runsQ = useQuery({
    queryKey: ['installer-runs'],
    queryFn: async () => (await api.get('/installer/runs')).data as { runs: InstallerRun[] },
    refetchInterval: 3000,
  })
  const runDetailQ = useQuery({
    queryKey: ['installer-run-detail', detailRunId],
    queryFn: async () =>
      (await api.get(`/installer/runs/${detailRunId}`)).data as { run: InstallerRunDetail },
    enabled: detailRunId !== null,
  })

  const installM = useMutation({
    mutationFn: async (payload: {
      app: string
      database_id?: number
      table_prefix?: string
      install_woocommerce?: boolean
    }) => {
      const { data } = await api.post(`/domains/${domainId}/installer`, payload)
      return data as { message?: string; run_id?: number; background?: boolean; status?: string }
    },
    onSuccess: (data, variables) => {
      if (typeof data.run_id === 'number') {
        setActiveRunId(data.run_id)
      }
      const hint = automationSuccessHint(t, variables.app, variables.install_woocommerce === true)
      if (data.background === true) {
        notify('info', t('installer.started'), [data.message, hint].filter(Boolean).join(' — '))
      } else {
        notify('success', t('installer.done'), [data.message, hint].filter(Boolean).join(' — ') || data.message)
      }
      qc.invalidateQueries({ queryKey: ['domains'] })
      qc.invalidateQueries({ queryKey: ['installer-runs'] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { message?: string; hint?: string } }
      }
      const d = ax.response?.data
      const msg = d?.message ?? String(err)
      notify('error', t('installer.install_error_title'), [msg, d?.hint].filter(Boolean).join(' — '))
    },
  })
  const diagnosticsM = useMutation({
    mutationFn: async () => {
      const { data } = await api.post('/installer/diagnostics', {
        domain_id: domainId || undefined,
        database_id: wpDatabaseId || undefined,
      })
      return data as InstallerDiagnostics
    },
    onSuccess: (data) => {
      setDiagData(data)
      notify('success', t('installer.diagnostics_title'), t('installer.diagnostics_done'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: InstallerDiagnostics & { message?: string } } }
      const payload = ax.response?.data
      if (payload?.checks?.length) {
        setDiagData(payload)
      }
      notify('error', t('installer.diagnostics_title'), payload?.message ?? t('installer.diagnostics_failed'))
    },
  })

  const apps = q.data?.apps?.length ? q.data.apps : FALLBACK_APPS
  const mysqlDbs = (databasesQ.data?.data ?? []).filter((d) => d.type === 'mysql')
  const latestRun = (runsQ.data?.runs ?? [])[0]
  const activeRun =
    (runsQ.data?.runs ?? []).find((r) => r.id === activeRunId) ??
    (latestRun && ['queued', 'running'].includes(latestRun.status) ? latestRun : null)
  const hasRunningInstall = !!activeRun

  const appsByCategory = useMemo(() => {
    const buckets: Record<AppCategory, AppRow[]> = {
      kobi: [],
      agency: [],
      modern: [],
      other: [],
    }
    for (const a of apps) {
      const c = a.category
      if (c === 'kobi' || c === 'agency' || c === 'modern') {
        buckets[c].push(a)
      } else {
        buckets.other.push(a)
      }
    }
    return buckets
  }, [apps])

  useEffect(() => {
    if (!installM.isPending && !hasRunningInstall) return
    const handler = (e: BeforeUnloadEvent) => {
      e.preventDefault()
      e.returnValue = ''
    }
    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [installM.isPending, hasRunningInstall])

  useEffect(() => {
    const runs = runsQ.data?.runs ?? []
    if (!activeRunId) return
    const target = runs.find((r) => r.id === activeRunId)
    if (!target) return
    if (!['success', 'failed'].includes(target.status)) return
    if (seenFinalRuns.includes(target.id)) return
    if (target.status === 'success') {
      const msg = runSuccessMessage(t, target.app, target.message)
      notify('success', t('installer.done'), msg)
    } else {
      notify('error', t('installer.install_failed_title'), target.message || undefined)
    }
    setSeenFinalRuns((s) => [...s, target.id])
    setActiveRunId(null)
  }, [runsQ.data, activeRunId, seenFinalRuns, t])

  const selectedDomain = useMemo(
    () => (domainsQ.data ?? []).find((d) => d.id === domainId) ?? null,
    [domainsQ.data, domainId],
  )

  useEffect(() => {
    const dr = (selectedDomain as unknown as { document_root?: string } | null)?.document_root
    if (typeof dr === 'string' && dr.replace(/\\/g, '/').endsWith('/public_html/public')) {
      setDocrootVariant('public')
    } else {
      setDocrootVariant('root')
    }
  }, [selectedDomain])

  const docrootM = useMutation({
    mutationFn: async (variant: 'root' | 'public') => {
      const { data } = await api.post(`/domains/${domainId}/document-root`, { variant })
      return data as { document_root?: string; variant?: 'root' | 'public' }
    },
    onSuccess: (data) => {
      const v = data.variant === 'public' ? 'public' : 'root'
      setDocrootVariant(v)
      qc.invalidateQueries({ queryKey: ['domains'] })
      notify('success', t('installer.guide_title', { app: guideApp?.name ?? '' }), t('installer.docroot_updated'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      notify('error', t('installer.docroot_title'), ax.response?.data?.message ?? String(err))
    },
  })

  const guideSteps = (appId: string) => {
    if (appId === 'laravel') {
      return [
        t('installer.guide_laravel_step_1'),
        t('installer.guide_laravel_step_2'),
        t('installer.guide_laravel_step_3'),
        t('installer.guide_laravel_step_4'),
      ]
    }
    if (['nodejs', 'nextjs', 'git_deploy', 'strapi'].includes(appId)) {
      return [
        t('installer.guide_deploy_step_1'),
        t('installer.guide_deploy_step_2'),
        t('installer.guide_generic_step_2'),
      ]
    }
    if (appId === 'docker' || appId === 'n8n') {
      return [t('installer.guide_docker_step_1'), t('installer.guide_generic_step_2')]
    }
    return [t('installer.guide_generic_step_1'), t('installer.guide_generic_step_2')]
  }

  return (
    <div className="space-y-6">
      {installM.isPending && (
        <div className="fixed inset-0 z-[200] flex flex-col items-center justify-center gap-4 bg-black/60 p-6 text-center">
          <Loader2 className="h-10 w-10 animate-spin text-white" aria-hidden />
          <p className="max-w-md text-sm text-white">{t('installer.sync_install_overlay')}</p>
        </div>
      )}

      <div className="flex items-center gap-3">
        <Download className="h-8 w-8 text-indigo-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.installer')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('installer.subtitle')}</p>
        </div>
      </div>

      <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
        {t('installer.page_stability_note')}
      </div>

      {hasRunningInstall && (
        <div className="card p-4 border border-indigo-200 dark:border-indigo-700">
          <div className="flex items-center gap-3">
            <span className="inline-flex h-3 w-3 rounded-full bg-indigo-500 animate-ping" />
            <div>
              <p className="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                {t('installer.running_bg')}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {activeRun?.message || t('installer.waiting_status')}
              </p>
            </div>
          </div>
        </div>
      )}

      <div className="card p-4 flex flex-wrap gap-4 items-end">
        <div>
          <label className="label">{t('domains.name')}</label>
          <select
            className="input min-w-[240px]"
            value={domainId}
            onChange={(e) => setDomainId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">{t('common.select')}</option>
            {(domainsQ.data ?? []).map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="label">{t('installer.wordpress_db')}</label>
          <select
            className="input min-w-[240px]"
            value={wpDatabaseId}
            onChange={(e) => setWpDatabaseId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">{t('common.select')}</option>
            {mysqlDbs.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="label">{t('installer.table_prefix')}</label>
          <input
            className="input min-w-[120px]"
            value={tablePrefix}
            onChange={(e) => setTablePrefix(e.target.value)}
            placeholder="wp_"
          />
        </div>
        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
          <input
            type="checkbox"
            className="rounded border-gray-300"
            checked={installWoo}
            onChange={(e) => setInstallWoo(e.target.checked)}
          />
          <span>{t('installer.include_woocommerce')}</span>
        </label>
        <button
          type="button"
          className="btn-secondary text-sm"
          disabled={diagnosticsM.isPending}
          onClick={() => diagnosticsM.mutate()}
        >
          {diagnosticsM.isPending ? t('common.loading') : t('installer.run_diagnostics')}
        </button>
      </div>
      {installWoo && (
        <p className="text-xs text-gray-500 dark:text-gray-400 -mt-4 ml-1">{t('installer.woocommerce_note')}</p>
      )}

      {diagData && (
        <div className="card p-4 border border-gray-200 dark:border-gray-700">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-2">
            {t('installer.diagnostics_title')}
          </h3>
          <div className="space-y-2">
            {diagData.checks.map((check) => (
              <div key={check.key} className="text-xs flex items-start gap-2">
                <span className={check.ok ? 'text-emerald-600' : 'text-rose-600'}>{check.ok ? 'OK' : 'ERR'}</span>
                <span className="text-gray-600 dark:text-gray-300">{check.message}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {CATEGORY_ORDER.map((cat) => {
        const list = appsByCategory[cat]
        if (!list.length) return null
        return (
          <div key={cat} className="space-y-3">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{categoryLabel(t, cat)}</h2>
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {list.map((app) => {
                const auto = app.automated === true
                const wp = app.id === 'wordpress'
                const oc = app.id === 'opencart'
                const needsDb = auto && (wp || oc)
                const disabled =
                  !domainId ||
                  installM.isPending ||
                  hasRunningInstall ||
                  (needsDb && !wpDatabaseId)
                const route = app.route?.trim()

                return (
                  <div key={app.id} className="card p-4 flex flex-col gap-2">
                    <h3 className="font-semibold text-gray-900 dark:text-white">{app.name}</h3>
                    {app.version ? <p className="text-xs text-gray-500">{app.version}</p> : null}
                    {wp && (
                      <p className="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                        {t('installer.wordpress_automation_scope')}
                      </p>
                    )}
                    {oc && (
                      <p className="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                        {t('installer.opencart_automation_scope')}
                      </p>
                    )}
                    {!auto && (
                      <p className="text-xs text-amber-600 dark:text-amber-400">{t('installer.manual_only')}</p>
                    )}
                    <div className="mt-auto flex flex-col gap-2">
                      {auto && (
                        <button
                          type="button"
                          className="btn-secondary text-sm"
                          disabled={disabled}
                          onClick={() => {
                            installM.mutate({
                              app: app.id,
                              ...(needsDb && wpDatabaseId
                                ? { database_id: wpDatabaseId as number, table_prefix: tablePrefix }
                                : {}),
                              ...(wp && installWoo ? { install_woocommerce: true } : {}),
                            })
                          }}
                        >
                          {t('installer.install')}
                        </button>
                      )}
                      {!auto && route && (
                        <Link className="btn-primary text-sm text-center" to={route}>
                          {t('installer.go_to_screen')}
                        </Link>
                      )}
                      {!auto && (
                        <button
                          type="button"
                          className="btn-secondary text-sm"
                          disabled={!domainId}
                          onClick={() => setGuideApp(app)}
                        >
                          {t('installer.open_guide')}
                        </button>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )
      })}

      <div className="card p-4">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">{t('installer.recent_runs')}</h3>
        <div className="space-y-2">
          {(runsQ.data?.runs ?? []).map((r) => (
            <div key={r.id} className="rounded-md border border-gray-200 dark:border-gray-700 p-2 text-xs">
              <div className="flex items-center gap-2">
                <span className="font-medium">{r.app}</span>
                <span className="text-gray-500">#{r.id}</span>
                <span className="ml-auto">{r.status}</span>
                <button
                  type="button"
                  className="btn-secondary py-1 px-2 text-[10px]"
                  onClick={() => setDetailRunId(r.id)}
                >
                  {t('common.details')}
                </button>
              </div>
              {r.message && <p className="text-gray-500 mt-1">{r.message}</p>}
            </div>
          ))}
          {(runsQ.data?.runs ?? []).length === 0 && (
            <p className="text-xs text-gray-500">{t('installer.no_runs')}</p>
          )}
        </div>
      </div>

      {detailRunId !== null && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                Installer Run #{detailRunId}
              </h3>
              <button type="button" className="btn-secondary py-1 px-2 text-xs" onClick={() => setDetailRunId(null)}>
                {t('common.close')}
              </button>
            </div>
            <div className="text-xs text-gray-500 space-y-1 mb-3">
              <p>{runDetailQ.data?.run.status}</p>
              {runDetailQ.data?.run.message && <p>{runDetailQ.data.run.message}</p>}
            </div>
            <pre className="max-h-[360px] overflow-auto rounded-md bg-gray-50 dark:bg-gray-800 p-3 text-[11px] text-gray-700 dark:text-gray-200 whitespace-pre-wrap">
              {runDetailQ.data?.run.output || '-'}
            </pre>
          </div>
        </div>
      )}

      {guideApp && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-5">
            <div className="flex items-start justify-between gap-3 mb-3">
              <div>
                <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                  {t('installer.guide_title', { app: guideApp.name })}
                </h3>
                <p className="text-xs text-gray-500 mt-1">{t('installer.guide_subtitle')}</p>
                {selectedDomain && (
                  <p className="mt-1 text-[11px] text-gray-500">
                    {t('installer.guide_domain')}: <span className="font-mono">{selectedDomain.name}</span>
                  </p>
                )}
              </div>
              <button type="button" className="btn-secondary py-1 px-2 text-xs" onClick={() => setGuideApp(null)}>
                {t('common.close')}
              </button>
            </div>

            <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
              {t('installer.guide_note_not_automated')}
            </div>

            <div className="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
              <ul className="list-disc pl-5 space-y-1">
                {guideSteps(guideApp.id).map((s, i) => (
                  <li key={`${guideApp.id}-step-${i}`}>{s}</li>
                ))}
              </ul>
            </div>

            {guideApp.id === 'laravel' && selectedDomain && (
              <div className="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('installer.docroot_title')}</p>
                    <p className="text-xs text-gray-500 mt-1">{t('installer.docroot_hint')}</p>
                  </div>
                  <span className="text-[11px] rounded-full px-2 py-0.5 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300">
                    {docrootVariant === 'public' ? 'public/' : 'public_html/'}
                  </span>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="btn-primary text-sm"
                    disabled={domainId === '' || docrootM.isPending || docrootVariant === 'public'}
                    onClick={() => docrootM.mutate('public')}
                  >
                    {t('installer.docroot_set_public')}
                  </button>
                  <button
                    type="button"
                    className="btn-secondary text-sm"
                    disabled={domainId === '' || docrootM.isPending || docrootVariant === 'root'}
                    onClick={() => docrootM.mutate('root')}
                  >
                    {t('installer.docroot_set_root')}
                  </button>
                </div>
              </div>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
              {guideApp.route && (
                <Link className="btn-primary text-sm" to={guideApp.route}>
                  {t('installer.go_to_screen')}
                </Link>
              )}
              {domainId !== '' && (
                <Link className="btn-secondary text-sm" to={`/files?domain=${domainId}`}>
                  {t('installer.guide_open_files')}
                </Link>
              )}
              <Link className="btn-secondary text-sm" to="/site-tools">
                {t('installer.guide_open_tools')}
              </Link>
              <Link className="btn-secondary text-sm" to="/deploy">
                {t('installer.guide_open_deploy')}
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function automationSuccessHint(
  t: (k: string) => string,
  app: string,
  woo: boolean,
): string {
  if (app === 'opencart') return t('installer.opencart_automation_hint_short')
  if (app === 'wordpress' && woo) {
    return [t('installer.wordpress_automation_hint_short'), t('installer.woocommerce_note')].filter(Boolean).join(' — ')
  }
  if (app === 'wordpress') return t('installer.wordpress_automation_hint_short')
  return ''
}

function runSuccessMessage(t: (k: string) => string, runApp: string, runMessage?: string): string | undefined {
  const base = runMessage || ''
  if (runApp === 'wordpress') {
    return [base, t('installer.wordpress_automation_hint_short')].filter(Boolean).join(' — ')
  }
  if (runApp === 'wordpress_woocommerce') {
    return [base, t('installer.wordpress_automation_hint_short'), t('installer.woocommerce_note')].filter(Boolean).join(' — ')
  }
  if (runApp === 'opencart') {
    return [base, t('installer.opencart_automation_hint_short')].filter(Boolean).join(' — ')
  }
  return base || undefined
}
