import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Rocket, Copy, Play } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import { useDomainsList } from '../hooks/useDomains'
import { useSearchParams } from 'react-router-dom'

type DeployConfig = {
  id: number
  repo_url?: string | null
  branch?: string | null
  branch_whitelist?: string[] | null
  runtime?: 'laravel' | 'node' | 'php' | string
  webhook_token?: string | null
  auto_deploy?: boolean
}

type DeployRun = {
  id: number
  trigger: string
  status: string
  commit_hash?: string | null
  output?: string | null
  started_at?: string | null
  finished_at?: string | null
}

export default function DeployPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [searchParams] = useSearchParams()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [repoUrl, setRepoUrl] = useState('')
  const [branch, setBranch] = useState('main')
  const [runtime, setRuntime] = useState<'laravel' | 'node' | 'php'>('laravel')
  const [branchWhitelist, setBranchWhitelist] = useState('')
  const [autoDeploy, setAutoDeploy] = useState(false)
  const [selectedRun, setSelectedRun] = useState<DeployRun | null>(null)
  const [wizardRuntimeTouched, setWizardRuntimeTouched] = useState(false)

  useEffect(() => {
    if (domainId !== '') return
    const raw = searchParams.get('domain')
    const n = raw ? Number(raw) : NaN
    if (Number.isFinite(n) && n > 0) {
      setDomainId(n)
    }
  }, [domainId, searchParams])

  const cfgQ = useQuery({
    queryKey: ['deploy-config', domainId],
    enabled: domainId !== '',
    queryFn: async () => (await api.get(`/domains/${domainId}/deployment`)).data as { config: DeployConfig },
  })

  const runsQ = useQuery({
    queryKey: ['deploy-runs', domainId],
    enabled: domainId !== '',
    queryFn: async () => (await api.get(`/domains/${domainId}/deployment/runs`)).data as { runs: DeployRun[] },
    refetchInterval: 8000,
  })

  const cfg = cfgQ.data?.config
  useEffect(() => {
    syncFromServer(cfg)
  }, [cfg?.id, cfg?.repo_url, cfg?.branch, cfg?.runtime, cfg?.auto_deploy])
  const webhookUrl = useMemo(() => {
    if (!domainId) return ''
    const baseUrl = (import.meta as any).env?.BASE_URL || '/'
    const cleanBase = baseUrl.replace(/\/+$/, '')
    // XAMPP local’de `/api/*` rewrite bazen çalışmayabiliyor; bu yüzden front controller olan `index.php` üzerinden çağırıyoruz.
    return `${window.location.origin}${cleanBase}/index.php/api/deployment/webhook/${domainId}`
  }, [domainId])
  const hasRepoConfigured = !!(cfg?.repo_url && cfg.repo_url.trim() !== '')
  const canRunDeploy = hasRepoConfigured && branch.trim() !== ''
  const wizardStep = useMemo(() => {
    if (domainId === '') return 1
    if (!repoUrl.trim()) return 1
    if (!branch.trim()) return 2
    if (!hasRepoConfigured) return 3
    return 4
  }, [domainId, repoUrl, branch, hasRepoConfigured])

  const syncFromServer = (next?: DeployConfig) => {
    if (!next) return
    setRepoUrl(next.repo_url ?? '')
    setBranch(next.branch ?? 'main')
    setRuntime(((next.runtime as 'laravel' | 'node' | 'php') || 'laravel'))
    setBranchWhitelist((next.branch_whitelist ?? []).join(','))
    setAutoDeploy(!!next.auto_deploy)
  }

  const saveM = useMutation({
    mutationFn: async () =>
      api.put(`/domains/${domainId}/deployment`, {
        repo_url: repoUrl.trim() || null,
        branch: branch.trim() || 'main',
        branch_whitelist: branchWhitelist
          .split(',')
          .map((x) => x.trim())
          .filter(Boolean),
        runtime,
        auto_deploy: autoDeploy,
      }),
    onSuccess: async (res) => {
      toast.success(t('deploy.saved'))
      syncFromServer((res.data as { config?: DeployConfig }).config)
      await qc.invalidateQueries({ queryKey: ['deploy-config', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const rotateM = useMutation({
    mutationFn: async () => api.put(`/domains/${domainId}/deployment`, { rotate_webhook_token: true }),
    onSuccess: async () => {
      toast.success(t('deploy.token_rotated'))
      await qc.invalidateQueries({ queryKey: ['deploy-config', domainId] })
    },
  })

  const runM = useMutation({
    mutationFn: async () => api.post(`/domains/${domainId}/deployment/run`),
    onSuccess: async () => {
      toast.success(t('deploy.started'))
      await qc.invalidateQueries({ queryKey: ['deploy-runs', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      qc.invalidateQueries({ queryKey: ['deploy-runs', domainId] })
    },
  })

  const rollbackM = useMutation({
    mutationFn: async () => api.post(`/domains/${domainId}/deployment/rollback`),
    onSuccess: async () => {
      toast.success(t('deploy.rollback_queued'))
      await qc.invalidateQueries({ queryKey: ['deploy-runs', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Rocket className="h-8 w-8 text-indigo-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.deploy')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('deploy.subtitle')}</p>
        </div>
      </div>

      <div className="card p-5 space-y-4">
        <div>
          <label className="label">{t('domains.name')}</label>
          <select
            className="input max-w-md"
            value={domainId}
            onChange={(e) => {
              const val = e.target.value ? Number(e.target.value) : ''
              setDomainId(val)
              setSelectedRun(null)
            }}
          >
            <option value="">{t('common.select')}</option>
            {(domainsQ.data ?? []).map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>

        {domainId !== '' && (
          <>
            <div className="grid gap-3 md:grid-cols-2">
              <div>
                <label className="label">{t('deploy.repo_url')}</label>
                <input className="input w-full" value={repoUrl} onChange={(e) => setRepoUrl(e.target.value)} placeholder="https://github.com/org/repo.git" />
              </div>
              <div>
                <label className="label">{t('deploy.branch')}</label>
                <input className="input w-full" value={branch} onChange={(e) => setBranch(e.target.value)} placeholder="main" />
              </div>
              <div>
                <label className="label">{t('deploy.runtime')}</label>
                <select className="input w-full" value={runtime} onChange={(e) => { setRuntime(e.target.value as 'laravel' | 'node' | 'php'); setWizardRuntimeTouched(true) }}>
                  <option value="laravel">Laravel/PHP</option>
                  <option value="node">Node</option>
                  <option value="php">PHP</option>
                </select>
              </div>
              <div>
                <label className="label">{t('deploy.branch_whitelist')}</label>
                <input className="input w-full" value={branchWhitelist} onChange={(e) => setBranchWhitelist(e.target.value)} placeholder="main,release/*" />
              </div>
              <label className="mt-7 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" checked={autoDeploy} onChange={(e) => setAutoDeploy(e.target.checked)} />
                {t('deploy.auto_deploy')}
              </label>
            </div>
            <div className="flex flex-wrap gap-2">
              <button type="button" className="btn-primary" onClick={() => saveM.mutate()} disabled={saveM.isPending || cfgQ.isLoading}>
                {t('common.save')}
              </button>
              <button type="button" className="btn-secondary inline-flex items-center gap-2" onClick={() => runM.mutate()} disabled={runM.isPending || !canRunDeploy}>
                <Play className="h-4 w-4" />
                {t('deploy.deploy_now')}
              </button>
              <button type="button" className="btn-secondary" onClick={() => rollbackM.mutate()} disabled={rollbackM.isPending}>
                {t('deploy.rollback')}
              </button>
            </div>
            <div className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-3 text-xs text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/20 dark:text-indigo-200">
              <p className="font-semibold">{t('deploy.wizard_title')}</p>
              <ol className="mt-2 list-decimal space-y-1 pl-4">
                <li className={wizardStep === 1 ? 'font-semibold' : ''}>{t('deploy.wizard_step_1')}</li>
                <li className={wizardStep === 2 ? 'font-semibold' : ''}>{t('deploy.wizard_step_2')}</li>
                <li className={wizardStep === 3 ? 'font-semibold' : ''}>{t('deploy.wizard_step_3')}</li>
                <li className={wizardStep === 4 ? 'font-semibold' : ''}>{t('deploy.wizard_step_4')}</li>
              </ol>
              {!hasRepoConfigured && (
                <p className="mt-2">{t('deploy.first_setup_hint')}</p>
              )}
              {runtime === 'laravel' && !wizardRuntimeTouched && (
                <p className="mt-2">{t('deploy.runtime_hint_laravel')}</p>
              )}
            </div>
          </>
        )}
      </div>

      {domainId !== '' && cfg && (
        <div className="card p-5 space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{t('deploy.webhook')}</h3>
            <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => rotateM.mutate()}>
              {t('deploy.rotate_token')}
            </button>
          </div>
          <div className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/50">
            <code className="text-xs break-all">{webhookUrl || '—'}</code>
          </div>
          <button
            type="button"
            className="btn-secondary inline-flex items-center gap-2 py-1.5 text-xs"
            onClick={async () => {
              if (!webhookUrl) return
              try {
                await navigator.clipboard.writeText(webhookUrl)
                toast.success(t('deploy.copied'))
              } catch {
                toast.error(t('deploy.copy_failed'))
              }
            }}
          >
            <Copy className="h-3.5 w-3.5" />
            {t('deploy.copy_webhook')}
          </button>
        </div>
      )}

      {domainId !== '' && (
        <div className="card p-5">
          <h3 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{t('deploy.runs')}</h3>
          {runsQ.isLoading ? (
            <p className="text-sm text-gray-500">{t('common.loading')}</p>
          ) : (runsQ.data?.runs ?? []).length === 0 ? (
            <p className="text-sm text-gray-500">{t('common.no_data')}</p>
          ) : (
            <div className="grid gap-3 md:grid-cols-2">
              {(runsQ.data?.runs ?? []).map((r) => (
                <button
                  key={r.id}
                  type="button"
                  className="rounded-lg border border-gray-200 p-3 text-left hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/40"
                  onClick={() => setSelectedRun(r)}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs text-gray-500">#{r.id} · {r.trigger}</span>
                    <span className={`text-xs ${r.status === 'success' ? 'text-emerald-600' : r.status === 'failed' ? 'text-red-600' : 'text-amber-600'}`}>{r.status}</span>
                  </div>
                  <p className="mt-1 text-xs text-gray-500">{r.commit_hash || '—'}</p>
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      {selectedRun && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card w-full max-w-4xl bg-white p-6 dark:bg-gray-900">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h3 className="text-sm font-semibold text-gray-900 dark:text-white">#{selectedRun.id} · {selectedRun.status}</h3>
              <button type="button" className="btn-secondary py-1.5 text-xs" onClick={() => setSelectedRun(null)}>
                {t('common.cancel')}
              </button>
            </div>
            <pre className="max-h-[65vh] overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100 whitespace-pre-wrap">
              {selectedRun.output || '—'}
            </pre>
          </div>
        </div>
      )}
    </div>
  )
}
