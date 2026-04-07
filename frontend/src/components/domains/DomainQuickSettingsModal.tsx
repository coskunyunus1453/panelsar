import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import api from '../../services/api'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import {
  X,
  Activity,
  FolderOpen,
  Rocket,
  Shield,
  ShieldOff,
  Loader2,
  CheckCircle2,
  Trash2,
} from 'lucide-react'
import DomainDeleteConfirmModal from './DomainDeleteConfirmModal'

export type DomainQuickRow = {
  id: number
  name: string
  php_version: string
  server_type: string
  status: string
  ssl_enabled?: boolean
}

const PHP_VERSIONS = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'] as const

type Props = {
  domain: DomainQuickRow | null
  open: boolean
  onClose: () => void
}

export default function DomainQuickSettingsModal({ domain, open, onClose }: Props) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [php, setPhp] = useState('')
  const [server, setServer] = useState<'nginx' | 'apache' | 'openlitespeed'>('nginx')
  const [perfMode, setPerfMode] = useState<'off' | 'standard'>('off')
  const [showDelete, setShowDelete] = useState(false)
  const [sslPhase, setSslPhase] = useState<'idle' | 'running' | 'done' | 'error'>('idle')
  const [sslStep, setSslStep] = useState(0)
  const [sslDiagnostics, setSslDiagnostics] = useState<Array<{ key: string; ok: boolean; message: string }> | null>(
    null,
  )
  const sslTimer = useRef<ReturnType<typeof setInterval> | null>(null)

  useEffect(() => {
    if (domain) {
      setPhp(domain.php_version)
      setServer(
        (domain.server_type === 'apache'
          ? 'apache'
          : domain.server_type === 'openlitespeed'
            ? 'openlitespeed'
            : 'nginx') as 'nginx' | 'apache' | 'openlitespeed',
      )
      setShowDelete(false)
      setSslPhase('idle')
      setSslStep(0)
      setSslDiagnostics(null)
      setPerfMode('off')
    }
  }, [domain])

  useEffect(() => {
    return () => {
      if (sslTimer.current) clearInterval(sslTimer.current)
    }
  }, [])

  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ['domains'] })
  }

  const healthQ = useQuery({
    queryKey: ['monitoring-health', 'domain', domain?.id ?? 0],
    enabled: open && !!domain?.id,
    queryFn: async () =>
      (
        await api.get('/monitoring/health', {
          params: { domain_id: domain?.id },
        })
      ).data as {
        score: number
        grade: 'excellent' | 'good' | 'warning' | 'critical'
        response_ms: number
        site_response_ms?: number | null
        snapshot: { cpu: number; ram: number; disk: number; error_rate: number }
        reasons: Array<{ key: string; ok: boolean; label: string; detail: string }>
      },
    staleTime: 15_000,
  })

  const deployRunsQ = useQuery({
    queryKey: ['deploy-runs', 'quick', domain?.id ?? 0],
    enabled: open && !!domain?.id,
    queryFn: async () =>
      (await api.get(`/domains/${domain?.id}/deployment/runs`)).data as {
        runs: Array<{
          id: number
          trigger: string
          status: string
          commit_hash?: string | null
          started_at?: string | null
          finished_at?: string | null
        }>
      },
    staleTime: 10_000,
  })

  const perfQ = useQuery({
    queryKey: ['domain-performance', domain?.id ?? 0],
    enabled: open && !!domain?.id,
    queryFn: async () =>
      (await api.get(`/domains/${domain?.id}/performance`)).data as {
        performance_mode: 'off' | 'standard'
        server_type?: string | null
        supported_servers?: string[]
      },
    staleTime: 15_000,
  })

  useEffect(() => {
    const m = perfQ.data?.performance_mode
    if (m === 'off' || m === 'standard') {
      setPerfMode(m)
    }
  }, [perfQ.data?.performance_mode])

  const perfSaveM = useMutation({
    mutationFn: async (mode: 'off' | 'standard') =>
      (await api.post(`/domains/${domain?.id}/performance`, { mode })).data as { performance_mode: 'off' | 'standard' },
    onSuccess: (res) => {
      toast.success(t('domains.perf_saved'))
      setPerfMode(res.performance_mode)
      void qc.invalidateQueries({ queryKey: ['domain-performance', domain?.id ?? 0] })
      invalidate()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const lastDeploy = useMemo(() => {
    const runs = deployRunsQ.data?.runs ?? []
    return runs.length > 0 ? runs[0] : null
  }, [deployRunsQ.data?.runs])

  const phpM = useMutation({
    mutationFn: async () => {
      if (!domain) return
      await api.post(`/domains/${domain.id}/php`, { php_version: php })
    },
    onSuccess: () => {
      toast.success(t('domains.php_switched'))
      invalidate()
      onClose()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const serverM = useMutation({
    mutationFn: async () => {
      if (!domain) return
      await api.post(`/domains/${domain.id}/server`, { server_type: server })
    },
    onSuccess: () => {
      toast.success(t('domains.server_switched'))
      invalidate()
      onClose()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const statusM = useMutation({
    mutationFn: async (status: 'active' | 'suspended') => {
      if (!domain) return
      await api.post(`/domains/${domain.id}/status`, { status })
    },
    onSuccess: () => {
      toast.success(t('domains.status_updated'))
      invalidate()
      onClose()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const sslIssueM = useMutation({
    mutationFn: async () => {
      if (!domain) return
      await api.post(`/domains/${domain.id}/ssl/issue`, {})
    },
    onSuccess: () => {
      setSslPhase('done')
      setSslStep(3)
      setSslDiagnostics(null)
      toast.success(t('ssl.issued'))
      invalidate()
      if (sslTimer.current) {
        clearInterval(sslTimer.current)
        sslTimer.current = null
      }
      setTimeout(() => {
        setSslPhase('idle')
        setSslStep(0)
        onClose()
      }, 1600)
    },
    onError: (err: unknown) => {
      setSslPhase('error')
      if (sslTimer.current) {
        clearInterval(sslTimer.current)
        sslTimer.current = null
      }
      const ax = err as { response?: { data?: { message?: string; diagnostics?: unknown } } }
      const diag = ax.response?.data?.diagnostics
      if (Array.isArray(diag)) {
        setSslDiagnostics(
          diag
            .map((d: any) => ({
              key: String(d?.key ?? ''),
              ok: Boolean(d?.ok),
              message: String(d?.message ?? ''),
            }))
            .filter((d) => d.key && d.message),
        )
      } else {
        setSslDiagnostics(null)
      }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const sslRevokeM = useMutation({
    mutationFn: async () => {
      if (!domain) return
      await api.post(`/domains/${domain.id}/ssl/revoke`, {})
    },
    onSuccess: () => {
      toast.success(t('ssl.revoked'))
      invalidate()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const startSslIssue = () => {
    if (!domain) return
    setSslPhase('running')
    setSslStep(0)
    if (sslTimer.current) clearInterval(sslTimer.current)
    sslTimer.current = setInterval(() => {
      setSslStep((s) => (s < 2 ? s + 1 : s))
    }, 700)
    sslIssueM.mutate()
  }

  if (!open || !domain) return null

  return (
    <>
      <DomainDeleteConfirmModal
        open={showDelete}
        domain={domain}
        onClose={() => setShowDelete(false)}
        onDeleted={() => {
          setShowDelete(false)
          onClose()
        }}
      />

    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div
        className="card max-h-[90vh] w-full max-w-lg overflow-y-auto bg-white p-6 dark:bg-gray-900"
        role="dialog"
        aria-modal="true"
      >
        <div className="mb-4 flex items-start justify-between gap-2">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              {t('domains.quick_settings')}
            </h2>
            <p className="font-mono text-sm text-primary-600 dark:text-primary-400">{domain.name}</p>
          </div>
          <button
            type="button"
            className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
            aria-label={t('common.cancel')}
            onClick={onClose}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="mb-5 grid gap-3 sm:grid-cols-3">
          <button
            type="button"
            className="btn-secondary inline-flex items-center justify-center gap-2"
            onClick={() => {
              onClose()
              navigate(`/files?domain=${domain.id}`)
            }}
          >
            <FolderOpen className="h-4 w-4" />
            {t('domains.open_files')}
          </button>
          <button
            type="button"
            className="btn-secondary inline-flex items-center justify-center gap-2"
            onClick={() => {
              onClose()
              navigate(`/deploy?domain=${domain.id}`)
            }}
          >
            <Rocket className="h-4 w-4" />
            {t('domains.go_deploy')}
          </button>
          <button
            type="button"
            className="btn-secondary inline-flex items-center justify-center gap-2"
            onClick={() => {
              onClose()
              navigate(`/monitoring?domain=${domain.id}`)
            }}
          >
            <Activity className="h-4 w-4" />
            {t('domains.go_monitoring')}
          </button>
        </div>

        <div className="mb-5 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('domains.overview_title')}</p>
              <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{t('domains.overview_subtitle')}</p>
            </div>
            <div className="text-right">
              <p className="text-xs text-gray-500">{t('domains.health_score')}</p>
              <p className="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                {healthQ.isLoading ? '…' : String(healthQ.data?.score ?? '—')}
              </p>
            </div>
          </div>

          <div className="mt-3 grid gap-2 sm:grid-cols-3">
            <div className="rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800/40">
              <p className="text-gray-500">CPU</p>
              <p className="font-mono text-gray-900 dark:text-gray-100">{healthQ.data?.snapshot?.cpu ?? '—'}%</p>
            </div>
            <div className="rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800/40">
              <p className="text-gray-500">RAM</p>
              <p className="font-mono text-gray-900 dark:text-gray-100">{healthQ.data?.snapshot?.ram ?? '—'}%</p>
            </div>
            <div className="rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800/40">
              <p className="text-gray-500">Disk</p>
              <p className="font-mono text-gray-900 dark:text-gray-100">{healthQ.data?.snapshot?.disk ?? '—'}%</p>
            </div>
          </div>

          {(healthQ.data?.reasons ?? []).slice(0, 3).length > 0 && (
            <ul className="mt-3 space-y-1 text-xs text-gray-600 dark:text-gray-300">
              {(healthQ.data?.reasons ?? []).slice(0, 3).map((r) => (
                <li key={r.key} className="flex items-start gap-2">
                  <span
                    className={clsx(
                      'mt-0.5 inline-block h-2 w-2 rounded-full',
                      r.ok ? 'bg-emerald-500' : 'bg-amber-500',
                    )}
                    aria-hidden
                  />
                  <span>
                    <span className="font-medium">{r.label}</span> — {r.detail}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="mb-5 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('domains.deploy_overview')}</p>
              <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{t('domains.deploy_overview_hint')}</p>
            </div>
            <div className="text-right text-xs">
              <p className="text-gray-500">{t('domains.last_deploy')}</p>
              <p className="font-mono text-gray-900 dark:text-gray-100">
                {deployRunsQ.isLoading ? '…' : lastDeploy ? `#${lastDeploy.id}` : '—'}
              </p>
            </div>
          </div>

          <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
            <div className="text-xs text-gray-600 dark:text-gray-300">
              <span className="font-medium">{t('domains.deploy_status')}:</span>{' '}
              <span
                className={clsx(
                  lastDeploy?.status === 'success' && 'text-emerald-600 dark:text-emerald-400',
                  lastDeploy?.status === 'failed' && 'text-red-600 dark:text-red-400',
                  !lastDeploy?.status && 'text-gray-500',
                  lastDeploy?.status && !['success', 'failed'].includes(lastDeploy.status) && 'text-amber-600 dark:text-amber-400',
                )}
              >
                {lastDeploy?.status ?? '—'}
              </span>
              {lastDeploy?.commit_hash ? (
                <span className="ml-2 font-mono text-gray-500">{lastDeploy.commit_hash}</span>
              ) : null}
            </div>
            <button
              type="button"
              className="btn-secondary py-1.5 text-xs"
              onClick={() => void deployRunsQ.refetch()}
              disabled={deployRunsQ.isFetching}
            >
              {t('domains.refresh')}
            </button>
          </div>
        </div>

        <div className="mb-5 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('domains.perf_title')}</p>
              <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{t('domains.perf_hint')}</p>
            </div>
            <span className="text-xs font-mono text-gray-500">{perfQ.isLoading ? '…' : perfMode}</span>
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              className={clsx('btn-secondary text-xs', perfMode === 'off' && 'ring-2 ring-primary-500')}
              disabled={perfSaveM.isPending || perfQ.isLoading}
              onClick={() => {
                if (!window.confirm(t('domains.perf_confirm_off'))) return
                perfSaveM.mutate('off')
              }}
            >
              {t('domains.perf_off')}
            </button>
            <button
              type="button"
              className={clsx('btn-secondary text-xs', perfMode === 'standard' && 'ring-2 ring-emerald-500')}
              disabled={perfSaveM.isPending || perfQ.isLoading || (domain.server_type ?? '').toLowerCase() !== 'nginx'}
              onClick={() => {
                if (!window.confirm(t('domains.perf_confirm_standard'))) return
                perfSaveM.mutate('standard')
              }}
            >
              {t('domains.perf_standard')}
            </button>
            {(domain.server_type ?? '').toLowerCase() !== 'nginx' && (
              <p className="self-center text-xs text-amber-700 dark:text-amber-300">{t('domains.perf_nginx_only')}</p>
            )}
          </div>
        </div>

        {sslPhase !== 'idle' && (
          <div className="mb-4 rounded-xl border border-primary-200 bg-primary-50/80 p-4 dark:border-primary-900/40 dark:bg-primary-950/30">
            <p className="mb-3 text-sm font-medium text-primary-900 dark:text-primary-100">
              {t('domains.ssl_progress_title')}
            </p>
            <ul className="space-y-2">
              {[0, 1, 2].map((i) => (
                <li
                  key={i}
                  className={clsx(
                    'flex items-center gap-2 text-sm transition-all duration-300',
                    sslStep > i || (sslPhase === 'done' && i <= 2)
                      ? 'text-green-700 dark:text-green-400'
                      : sslStep === i && sslPhase === 'running'
                        ? 'font-medium text-primary-800 dark:text-primary-200'
                        : 'text-gray-400',
                  )}
                >
                  {sslPhase === 'running' && sslStep === i ? (
                    <Loader2 className="h-4 w-4 shrink-0 animate-spin" />
                  ) : sslStep > i || sslPhase === 'done' ? (
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                  ) : (
                    <span className="inline-block h-4 w-4 shrink-0 rounded-full border-2 border-gray-300 dark:border-gray-600" />
                  )}
                  {t(`domains.ssl_step_${i + 1}`)}
                </li>
              ))}
            </ul>
            {sslPhase === 'error' && (
              <div className="mt-2 space-y-2">
                <p className="text-sm text-red-600 dark:text-red-400">{t('domains.ssl_progress_error')}</p>
                {sslDiagnostics && sslDiagnostics.length > 0 && (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
                    <p className="mb-1 font-semibold">{t('domains.ssl_diagnostics_title')}</p>
                    <ul className="space-y-1">
                      {sslDiagnostics.slice(0, 6).map((d) => (
                        <li key={d.key} className="flex items-start gap-2">
                          <span
                            className={clsx(
                              'mt-0.5 inline-block h-2 w-2 rounded-full',
                              d.ok ? 'bg-emerald-500' : 'bg-rose-500',
                            )}
                            aria-hidden
                          />
                          <span>{d.message}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        <div className="space-y-5">
          <div>
            <label className="label">{t('domains.php_version')}</label>
            <div className="flex flex-wrap gap-2">
              <select className="input flex-1 min-w-[140px]" value={php} onChange={(e) => setPhp(e.target.value)}>
                {PHP_VERSIONS.map((v) => (
                  <option key={v} value={v}>
                    PHP {v}
                  </option>
                ))}
              </select>
              <button
                type="button"
                className="btn-primary"
                disabled={php === domain.php_version || phpM.isPending}
                onClick={() => phpM.mutate()}
              >
                {phpM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('domains.apply')}
              </button>
            </div>
          </div>

          <div>
            <label className="label">{t('domains.server_type')}</label>
            <div className="flex flex-wrap gap-2">
              <select
                className="input flex-1 min-w-[140px]"
                value={server}
                onChange={(e) =>
                  setServer(e.target.value as 'nginx' | 'apache' | 'openlitespeed')
                }
              >
                <option value="nginx">nginx</option>
                <option value="apache">Apache</option>
                <option value="openlitespeed">{t('domains.server_openlitespeed')}</option>
              </select>
              <button
                type="button"
                className="btn-primary"
                disabled={server === domain.server_type || serverM.isPending}
                onClick={() => serverM.mutate()}
              >
                {serverM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('domains.apply')}
              </button>
            </div>
          </div>

          <div>
            <label className="label">{t('domains.site_status')}</label>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                className={clsx(
                  'btn-secondary flex-1',
                  domain.status === 'active' && 'ring-2 ring-primary-500',
                )}
                disabled={domain.status === 'active' || statusM.isPending}
                onClick={() => statusM.mutate('active')}
              >
                {t('common.active')}
              </button>
              <button
                type="button"
                className={clsx(
                  'btn-secondary flex-1',
                  domain.status === 'suspended' && 'ring-2 ring-amber-500',
                )}
                disabled={domain.status === 'suspended' || statusM.isPending}
                onClick={() => statusM.mutate('suspended')}
              >
                {t('domains.suspended')}
              </button>
            </div>
          </div>

          <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
            <label className="label">{t('domains.ssl_status')}</label>
            <div className="flex flex-wrap gap-2">
              {domain.ssl_enabled ? (
                <button
                  type="button"
                  className="btn-secondary inline-flex items-center gap-2"
                  disabled={sslRevokeM.isPending || sslPhase === 'running'}
                  onClick={() => {
                    if (window.confirm(t('domains.ssl_revoke_confirm'))) sslRevokeM.mutate()
                  }}
                >
                  <ShieldOff className="h-4 w-4" />
                  {t('domains.ssl_remove')}
                </button>
              ) : (
                <button
                  type="button"
                  className="btn-primary inline-flex items-center gap-2"
                  disabled={sslIssueM.isPending || sslPhase === 'running'}
                  onClick={startSslIssue}
                >
                  <Shield className="h-4 w-4" />
                  {t('domains.ssl_add_letsencrypt')}
                </button>
              )}
            </div>
          </div>

          <div className="border-t border-red-200 pt-4 dark:border-red-900/40">
            <button
              type="button"
              className="btn-secondary inline-flex items-center gap-2 border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-950/40"
              onClick={() => setShowDelete(true)}
            >
              <Trash2 className="h-4 w-4" />
              {t('domains.delete_site')}
            </button>
          </div>
        </div>
      </div>
    </div>
    </>
  )
}
