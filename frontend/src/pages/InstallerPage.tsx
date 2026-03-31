import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Download } from 'lucide-react'
import { useDomainsList } from '../hooks/useDomains'
import { notify } from '../lib/notify'

type AppRow = { id: string; name: string; version: string; automated?: boolean }
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
  { id: 'wordpress', name: 'WordPress', version: 'latest', automated: true },
  { id: 'joomla', name: 'Joomla', version: 'latest', automated: false },
  { id: 'laravel', name: 'Laravel', version: '11.x', automated: false },
  { id: 'drupal', name: 'Drupal', version: '10.x', automated: false },
  { id: 'prestashop', name: 'PrestaShop', version: '8.x', automated: false },
]

export default function InstallerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [wpDatabaseId, setWpDatabaseId] = useState<number | ''>('')
  const [tablePrefix, setTablePrefix] = useState('wp_')
  const [activeRunId, setActiveRunId] = useState<number | null>(null)
  const [seenFinalRuns, setSeenFinalRuns] = useState<number[]>([])
  const [detailRunId, setDetailRunId] = useState<number | null>(null)
  const [diagData, setDiagData] = useState<InstallerDiagnostics | null>(null)

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
    mutationFn: async (payload: { app: string; database_id?: number; table_prefix?: string }) => {
      const { data } = await api.post(`/domains/${domainId}/installer`, payload)
      return data as { message?: string; run_id?: number; background?: boolean; status?: string }
    },
    onSuccess: (data) => {
      if (typeof data.run_id === 'number') {
        setActiveRunId(data.run_id)
      }
      if (data.background === true) {
        notify('info', t('installer.started'), data.message)
      } else {
        notify('success', t('installer.done'), data.message)
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
      notify('error', 'Kurulum hatası', [msg, d?.hint].filter(Boolean).join(' — '))
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

  useEffect(() => {
    const runs = runsQ.data?.runs ?? []
    if (!activeRunId) return
    const target = runs.find((r) => r.id === activeRunId)
    if (!target) return
    if (!['success', 'failed'].includes(target.status)) return
    if (seenFinalRuns.includes(target.id)) return
    if (target.status === 'success') {
      notify('success', t('installer.done'), target.message || undefined)
    } else {
      notify('error', 'Kurulum başarısız', target.message || undefined)
    }
    setSeenFinalRuns((s) => [...s, target.id])
    setActiveRunId(null)
  }, [runsQ.data, activeRunId, seenFinalRuns, t])

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Download className="h-8 w-8 text-indigo-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.installer')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('installer.subtitle')}</p>
        </div>
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
        <button
          type="button"
          className="btn-secondary text-sm"
          disabled={diagnosticsM.isPending}
          onClick={() => diagnosticsM.mutate()}
        >
          {diagnosticsM.isPending ? t('common.loading') : t('installer.run_diagnostics')}
        </button>
      </div>

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

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {apps.map((app) => {
          const auto = app.automated === true
          const wp = app.id === 'wordpress'
          const disabled =
            !domainId ||
            installM.isPending ||
            hasRunningInstall ||
            !auto ||
            (wp && !wpDatabaseId)
          return (
            <div key={app.id} className="card p-4 flex flex-col gap-2">
              <h3 className="font-semibold text-gray-900 dark:text-white">{app.name}</h3>
              <p className="text-xs text-gray-500">{app.version}</p>
              {!auto && <p className="text-xs text-amber-600 dark:text-amber-400">{t('installer.manual_only')}</p>}
              <button
                type="button"
                className="btn-secondary text-sm mt-auto"
                disabled={disabled}
                title={!auto ? t('installer.manual_only') : undefined}
                onClick={() =>
                  installM.mutate({
                    app: app.id,
                    ...(wp && wpDatabaseId ? { database_id: wpDatabaseId as number, table_prefix: tablePrefix } : {}),
                  })
                }
              >
                {t('installer.install')}
              </button>
            </div>
          )
        })}
      </div>

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
    </div>
  )
}
